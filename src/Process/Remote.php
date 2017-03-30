<?php
namespace App\Process;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\Console\Helper\ProgressBar;

class Remote
{
    protected $client;

    protected $cookie;

    protected $parser;

    public static $nextPage;

    public function __construct($parser)
    {
        $this->cookie = new CookieJar();
        $this->client = new Client([
            'base_uri' => getenv('BASE_URL')
        ]);
        $this->parser = $parser;
    }

    public function login($username, $password)
    {

        $response = $this->client->request('GET', getenv('LOGIN_PATH'), [
            'cookies' => $this->cookie,
            'verify' => false
        ]);

        $csrfToken = $this->parser->parse((string)$response->getBody())->getCrsfToken();

        $response = $this->client->request('POST', getenv('LOGIN_PATH'), [
            'cookies' => $this->cookie,
            'form_params'    => [
                'email'    => $username,
                'password' => $password,
                '_token'   => $csrfToken,
                'remember' => 1,
            ],
            'verify' => false
        ]);

        $html = (string) $response->getBody();
        
        if(strpos($html, "Couldn't sign you in with those details.") !== FALSE) {
            return false;
        }

        if(strpos($html, "Couldn&#039;t sign you in with those details.") !== FALSE) {
            return false;
        }

        if(strpos($html, "Could not find that account") !== FALSE) {
            return false;
        }

        return strpos($html, "Couldn't sign you in with those details.")  === FALSE;
    }

    public function series($output)
    {
        echo PHP_EOL;
        $output->writeln("<info>Collecting remote series ...</info>");
        $response = $this->client->request('GET','lessons',['verify' => false]);
        $parse  =  $this->parser->parse((string) $response->getBody());
        $series = $parse->getSeries();
        $progress = new ProgressBar($output, $parse->totalPages());
        $progress->start();
        static::$nextPage = $parse->hasNextPage();
        while(static::$nextPage) {
            $progress->advance();
            $request = $this->client->request('GET',str_replace(getenv('BASE_URL'),'',static::$nextPage), ['verify' => false])->getBody();
            $newSeries = $this->parser->parse((string) $request);
            $series = array_merge($series, $newSeries->getSeries());
            static::$nextPage = $newSeries->hasNextPage();
        }
        $progress->finish();
        return collect($series);
    }

    public function getLessons($series)
    {
        $response = $this->client->request('GET', "lessons/{$series}", [
            'cookies' => $this->cookie,
            'verify' => false
        ]);

        $content = (string)$response->getBody();
        $links  =  $this->parser->parse($content)->getLessonLinks();
        return collect($links);
    }


    public function downloadVideo($video, $lesson, $output,$file)
    {
        $downloadLink = str_replace('lessons','videos', $video['link']);
        try {
            $viemoUrl = $this->getRedirectUrl($downloadLink);
            $finalUrl = $viemoUrl[0];
            $this->client->request('GET', $finalUrl, [
                'sink' => getenv('DOWNLOAD_FOLDER').'/'.$lesson.'/'.$video['slug']
            ]);
        } catch (\Exception $e) {
            echo "Cant download '{$video['title']}'".PHP_EOL;
        }


    }

    public function createFolderIfNotExists($folder, $file)
    {
        if ($file->file->has($folder) === false) {
            $file->file->createDir($folder);
        }
    }



    private function getRedirectUrl($url)
    {
        $response = $this->client->request('GET', $url, [
            'cookies'         => $this->cookie,
            'allow_redirects' => FALSE,
            'verify' => false
        ]);

        return $response->getHeader('Location');
    }

}