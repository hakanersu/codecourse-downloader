<?php
namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

class Remote
{
    /**
     * @var Client $guzzle
     */
    protected $web;

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
    public $io;

    protected $api;

    protected $token;

    public function __construct($username, $password, $io)
    {
        $this->cookie = new CookieJar();
        $this->web = new Client(['base_uri' => getenv('BASE_URL')]);
        $this->api = new Client(['base_uri' => getenv('API')]);
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
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    public function login($username, $password)
    {
        try {
            $response = $this->api->request('POST', getenv('LOGIN_PATH'), [
                'cookies' => $this->cookie,
                'form_params' => [
                    'email' => $username,
                    'password' => $password,
                ]
            ]);
            $content = json_decode($response->getBody());
            $this->token = $content->data->token;
            success("Logged in successfully, collecting courses.");
        } catch (GuzzleException $e) {
            error("Can't login to website.");
            exit;
        } catch (\Exception $e) {
            error("Error on login: ".$e->getMessage());
            exit;
        }
    }



    public function meta()
    {
        try {
            $api = $this->api->request('GET', getenv('COURSES'), [
                'cookies' => $this->cookie,
                'base_uri' => getenv('API')
            ]);
            $data = json_decode($api->getBody());
            return $data;
        } catch (GuzzleException $e) {
            error("Can't fetch courses.");
            exit;
        }
    }

    public function getCourse($slug)
    {
        $response = $this->web->request('GET', "watch/{$slug}", [
            'cookies' => $this->cookie
        ]);

        $html = $response->getBody()->getContents();
      

        return (new Parser)->parse($html);
    }

    public function page($number)
    {
        try {
            $courses = $this->api->request('GET', getenv('COURSES') . "?page={$number}", [
                'cookies' => $this->cookie,
                'base_uri' => getenv('API')
            ]);
            $courses = json_decode($courses->getBody());
            $links = collect($courses->data);
            return $links->pluck('slug')->toArray();
        } catch (GuzzleException $e) {
            error("Can't fetch lessons.");
            exit;
        }
    }

    /**
     * @param object $file
     * @param string $lesson
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function downloadFile($course, $lesson)
    {
        try {
            $url = $this->getRedirectUrl($lesson->link);
            $sink = getenv('DOWNLOAD_FOLDER') . "/{$course}/{$lesson->filename}";
            $this->guzzle->request('GET', $url, ['sink' => $sink]);
        } catch (\Exception $e) {
            $this->io->error("Cant download '{$lesson->title}' ({$e->getMessage()})");
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
