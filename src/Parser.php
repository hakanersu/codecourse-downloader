<?php
namespace App;

use App\Models\Video;
use App\Models\Zip;
use Illuminate\Support\Collection;
use Symfony\Component\DomCrawler\Crawler;
use Cocur\Slugify\Slugify;

class Parser
{
    /**
     * @var Crawler $crawler
     */
    public $crawler;

    /**
     * Parse HTML
     *
     * @param $html
     *
     * @return $this
     */
    public function parse($html)
    {
        $this->crawler = new Crawler($html, getenv('BASE_URL'));

        return $this;
    }

    public function page()
    {
        $nodes = $this->crawler->filter('script[type="text/javascript"]');
        $lessons = [];
        $nodes->each(function(Crawler $node) use (&$lessons) {
            $text = trim($node->text());
            $find = 'window.__NUXT__=';
            if (strpos($text, $find) !== 0) {
                return;
            }
            $end = strlen($text) - strlen($find) - 1;
            $nuxt_str = substr($text, strlen($find), $end);
            $nuxt = json_decode($nuxt_str, true);
            $lessons = $this->lesson($nuxt);
        });
        return $lessons;
    }

    public function lesson($nuxt)
    {
        $parts = $nuxt['state']['watch']['parts'];
        $lessons = [];
        foreach($parts as $i => $part) {
            $id = $part['video']['id'];
            $quality = $this->bestQuality($part);
            // TODO I left here.
            $lesson = new Lesson;
            $lesson->link = getenv('API_URL').'/api/videos/'.$id.'/download?quality='.$quality;
            $lesson->title = $part['slug'];
            $lesson->filename = sprintf('%02d', $i).'-'.$part['slug'].'.mp4';
            $lessons[] = $lesson;
        }
        return $lessons;
    }

    protected function bestQuality($part) {
        return $part['video']['download_qualities_enabled'][0]['value'];
    }
}