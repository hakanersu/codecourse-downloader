<?php
namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

class Remote
{
    /**
     * @var Client $guzzle
     */
    protected $guzzle;

    /**
     * @var  CookieJar $cookie
     */
    protected $cookie;

    /**
     * @var Parser $parser
     */
    protected $parser;

    /**
     * @var SymfonyStyle $io
     */
    private $io;

    public function __construct($username, $password, $io)
    {
        $this->cookie = new CookieJar();
        $this->guzzle = new Client(['base_uri' => getenv('BASE_URL')]);
        $this->parser = new Parser;

        $this->login($username, $password);
        $this->io = $io;
    }

    /**
     * Login
     *
     * @param $username
     * @param $password
     *
     * @throws \Exception
     */
    public function login($username, $password)
    {
        $response = $this->guzzle->request('GET', getenv('LOGIN_PATH'), [
            'cookies' => $this->cookie,
            'verify' => false
        ]);

        $csrfToken = $this->parser->parse($response->getBody()->getContents())->getCrsfToken();

        $response = $this->guzzle->request('POST', getenv('LOGIN_PATH'), [
            'cookies' => $this->cookie,
            'form_params'    => [
                'email'    => $username,
                'password' => $password,
                '_token'   => $csrfToken,
                'remember' => 1,
            ],
            'verify' => false
        ]);

        $content = $response->getBody()->getContents();

        if(strpos($content, "Couldn't sign you in with those details.") !== false) {
            throw new \Exception("Couldn't sign you in with those details.");
        }

        if(strpos($content, "Couldn&#039;t sign you in with those details.") !== false) {
            throw new \Exception("Couldn't sign you in with those details.");
        }

        if(strpos($content, "Could not find that account") !== false) {
            throw new \Exception("This account doesn't exist");
        }
    }

    /**
     * Series
     *
     * @return Collection
     */
    public function fetchSeries()
    {
        $this->io->section("Fetching all series...");
        $response = $this->guzzle->request('GET', 'lessons', ['verify' => false]);
        $parse  =  $this->parser->parse((string) $response->getBody());
        $series = $parse->getSeries();
        $progress = new ProgressBar($this->io, $parse->totalPages());
        $progress->start();
        $nextPage = $parse->getNextPage();
        while($nextPage) {
            $progress->advance();
            $request = $this->guzzle->request('GET', str_replace(getenv('BASE_URL'), '', $nextPage), ['verify' => false])->getBody();
            $nextSeries = $this->parser->parse((string) $request);
            $series = array_merge($series, $nextSeries->getSeries());
            $nextPage = $nextSeries->getNextPage();
        }
        $progress->finish();

        return collect($series);
    }

    /**
     * Get lessons
     *
     * @param $series
     * @return Collection
     */
    public function fetchLessons($series)
    {
        $response = $this->guzzle->request('GET', "lessons/{$series}", [
            'cookies' => $this->cookie,
            'verify' => false
        ]);

        $content = $response->getBody()->getContents();
        return $this->parser->parse($content)->getLessonLinks();
    }

    /**
     * @param object $file
     * @param string $lesson
     */
    public function downloadFile($file, $lesson)
    {
        try {
            $this->guzzle->request('GET', $this->getRedirectUrl($file->getLink()), [
                'sink' => getenv('DOWNLOAD_FOLDER') . "/{$lesson}/{$file->getFilename()}"
            ]);
        } catch (\Exception $e) {
            $this->io->error("Cant download '{$file->getTitle()}' ({$e->getMessage()})");
        }
    }

    private function getRedirectUrl($url)
    {
        $response = $this->guzzle->request('GET', $url, [
            'cookies'           => $this->cookie,
            'allow_redirects'   => false,
            'verify'            => false
        ]);

        return $response->getHeader('Location')[0];
    }

    /**
     * Create folder if does't exist
     *
     * @param $folder
     * @param $file
     */
    public function createFolder($folder, $file)
    {
        if ($file->file->has($folder) === false) {
            $file->file->createDir($folder);
        }
    }
}