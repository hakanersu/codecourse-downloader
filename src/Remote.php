<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Style\SymfonyStyle;

class Remote
{
    /**
     * @var SymfonyStyle
     */
    public $io;
    /**
     * @var Client
     */
    public $web;

    /**
     * @var CookieJar
     */
    public $cookie;

    /**
     * @var Parser
     */
    public $parser;

    public $api;

    public $token;

    public function __construct($username, $password, $io)
    {
        $this->cookie = new CookieJar();
        $this->web = new Client(['base_uri' => getenv('BASE_URL')]);
        $this->api = new Client(['base_uri' => getenv('API')]);
        $this->parser = new Parser();

        $this->login($username, $password);
        $this->io = $io;
    }

    /**
     * Login.
     *
     * @param $username
     * @param $password
     *
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
                ],
            ]);
            $content = json_decode($response->getBody());
            $this->token = $content->data->token;
            success('Logged in successfully, collecting courses.');
        } catch (GuzzleException $e) {
            error("Can't login to website.");
            exit;
        } catch (\Exception $e) {
            error('Error on login: ' . $e->getMessage());
            exit;
        }
    }

    public function meta()
    {
        try {
            $api = $this->api->request('GET', getenv('COURSES'), [
                'cookies' => $this->cookie,
                'base_uri' => getenv('API'),
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
        try {
            $response = $this->web->request('GET', "watch/{$slug}", [
                'cookies' => $this->cookie,
            ]);
            $html = $response->getBody()->getContents();

            return (new Parser())->parse($html);
        } catch (GuzzleException $e) {
            error("Can't fetch course url");
        }
    }

    public function page($number)
    {
        try {
            $courses = $this->api->request('GET', getenv('COURSES') . "?page={$number}", [
                'cookies' => $this->cookie,
                'base_uri' => getenv('API'),
            ]);
            $courses = json_decode($courses->getBody());
            $links = collect($courses->data);

            return $links->pluck('slug')->toArray();
        } catch (GuzzleException $e) {
            error("Can't fetch course page.");
            exit;
        }
    }

    /**
     * @param $course
     * @param string $lesson
     *
     * @throws GuzzleException
     */
    public function downloadFile($course, $lesson)
    {
        try {
            $url = $this->getRedirectUrl($lesson->link);
            $sink = getenv('DOWNLOAD_FOLDER') . "/{$course}/{$lesson->filename}";
            $this->web->request('GET', $url, ['sink' => $sink]);
        } catch (\Exception $e) {
            error("Cant download '{$lesson->title}'. Do you have active subscription?");
            exit;
        }
    }

    public function getRedirectUrl($url)
    {
        try {
            $response = $this->web->request('POST', $url, [
                'cookies' => $this->cookie,
                'headers' => [
                    'authorization' => 'Bearer ' . $this->token,
                ],
            ]);
            $content = json_decode($response->getBody(), true);

            return $content['data'];
        } catch (GuzzleException $e) {
            error("Can't fetch redirect url");
        }

        return false;
    }

    /**
     * Create folder if does't exist.
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
