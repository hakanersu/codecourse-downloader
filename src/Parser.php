<?php

namespace App;

use Symfony\Component\DomCrawler\Crawler;

class Parser
{
    /**
     * @var Crawler
     */
    public $crawler;

    public $jsfunction;

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

            $re = '/parts:{parts:([\s\S]*),paths/m';
            preg_match($re, $text, $matches, PREG_OFFSET_CAPTURE, 3);
            $parts = $matches[0][0];

            $re = '/id:(\d*?),title:"(.*?)",slug:(.*?),(.*?)order:(.*?),/m';
            preg_match_all($re, $parts, $matches, PREG_SET_ORDER, 0);
            $info = [];
            $i = 1;

            $this->extractFunctionValues($text);

            foreach ($matches as $match) {
                if (strlen($match[3]) <=3) {
                    $match[3] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $match[2])));
                }
                array_push($info, [
                    'id' => $match[1],
                    'title' => $match[2],
                    'slug' => str_replace('"', '', $match[3]),
                    'order' => is_numeric($match[5]) ? $match[5] : $this->getJsValue($match[5])
                ]);
                $i ++;
            }
            $lessons = $this->lesson($info);
        });

        return $lessons;
    }



    public function extractFunctionValues($text)
    {
        $re = '/function\((.*?)\)/m';
        preg_match_all($re, $text, $matches, PREG_SET_ORDER, 0);
        $parameters = explode(',', $matches[0][1]);
        $values = $this->extractValues($text);

        $this->jsfunction =  (object) [
            'parameters' => $parameters,
            'values' => $values
        ];
    }

    public function getJsValue($param)
    {
        $index = array_search($param, $this->jsfunction->parameters);
        return $this->jsfunction->values[$index];
    }

    public function extractValues($text)
    {
        $re = '/serverRendered:(.*)}}(.*?)\((.*?)\)/m';
        preg_match_all($re, $text, $matches, PREG_SET_ORDER, 0);
        return  str_getcsv($matches[0][3], ",", '"');
    }

    public function lesson($nuxt)
    {
        $lessons = [];
        foreach ($nuxt as $part) {
            $lesson = (object) [
                'link' => "https://codecourse.com/api/parts/" . $part['id'] . "/download",
                'title' => $part['title'],
                'slug' => $part['slug'],
                'filename' => sprintf('%02d', (int)$part['order']-1) . '-' . $part['slug'] . '.mp4',
            ];
            $lessons[] = $lesson;
        }
		
        return $lessons;
    }
}
