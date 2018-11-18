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
        $parts = $nuxt['state']['parts']['parts'];
        $lessons = [];
        foreach ($parts as $i => $part) {
            $id = $part['video']['data']['id'];
            $quality = 'hd';
            $slug = $words = preg_replace('/[0-9]+/', '', $part['slug']);
            $slug = ltrim($slug, '-');
            $lesson = (object) [
                'link' => getenv('API') . '/api/videos/' . $id . '/download?quality=' . $quality,
                'title' => $part['title'],
                'slug' => $slug,
                'filename' => sprintf('%02d', $i) . '-' . $slug . '.mp4',
            ];

            $lessons[] = $lesson;
        }

        return $lessons;
    }

}
