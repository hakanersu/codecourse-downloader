<?php

namespace App;

use Symfony\Component\DomCrawler\Crawler;

class Parser
{
    /**
     * @var Crawler
     */
    public $crawler;

    /**
     * Parse HTML.
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

    public function getPage()
    {
        $nodes = $this->crawler->filter('script');

        $lessons = [];
        $nodes->each(function (Crawler $node) use (&$lessons) {
            $text = trim($node->text());
            $find = 'window.__NUXT__=';

            if (strpos($text, $find) !== 0) {
                //throw new \Exception("Can't parse lesson page, page structure changed.");
                return;
            }
            $re = '/parts:({parts:(\[(.*)\])}?),paths/m';
            preg_match($re, $text, $matches, PREG_OFFSET_CAPTURE, 3);
            $parts = $matches[3][0];

            $re = '/id:(.*?),title:"(.*?)",slug:(.*?),(.*?)order:(.*?),(.*?)video:{data:{id:(.*?),/m';
            preg_match_all($re, $parts, $matches, PREG_SET_ORDER, 0);
            $info = [];
            $i = 1;
            foreach ($matches as $match) {
                if (strlen($match[3]) <=3) {
                    $match[3] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $match[2])));
                }
                array_push($info, [
                    'order' => is_string($match[5]) ? $i : $match[5] ,
                    'title' => $match[2],
                    'slug' => str_replace('"', '', $match[3]),
                    'video' => $match[7]
                ]);
                $i ++;
            }

            $lessons = $this->lesson($info);
        });

        return $lessons;
    }

    public function lesson($nuxt)
    {
        $lessons = [];
        foreach ($nuxt as $part) {
            $lesson = (object) [
                'link' => getenv('API') . '/api/videos/' . $part['video'] . '/download?quality=hd',
                'title' => $part['title'],
                'slug' => $part['slug'],
                'filename' => sprintf('%02d', (int)$part['order']-1) . '-' . $part['slug'] . '.mp4',
            ];
            $lessons[] = $lesson;
        }
        return $lessons;
    }
}
