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

            $re = '/id:(.*?),title:"(.*?)",slug:(.*?),(.*?)order:(.*?),(.*?)video:{data:{id:(.*?),/m';
            preg_match_all($re, $parts, $matches, PREG_SET_ORDER, 0);
            $info = [];
            $i = 1;

            $this->extractFunctionValues($text);

            foreach ($matches as $match) {
                if (strlen($match[3]) <= 3) {
                    $match[3] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $match[2])));
                }
                $test = false;
                if ($test && $match[2] == 'Fixing up failing order test') {
                    dd($match, [
                        'order' => is_string($match[5]) ? $i : $match[5],
                        'title' => $match[2],
                        'slug' => str_replace('"', '', $match[3]),
                        'video' => is_numeric($match[7]) ? $match[7] : $this->getJsValue($match[7]),
                    ]);
                }
                array_push($info, [
                    'order' => is_string($match[5]) ? $i : $match[5],
                    'title' => $match[2],
                    'slug' => str_replace('"', '', $match[3]),
                    'video' => is_numeric($match[7]) ? $match[7] : $this->getJsValue($match[7]),
                ]);
                ++$i;
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

        $this->jsfunction = (object) [
            'parameters' => $parameters,
            'values' => $values,
        ];
    }

    public function getJsValue($param)
    {
        $index = array_search($param, $this->jsfunction->parameters, true);

        return $this->jsfunction->values[$index];
    }

    public function extractValues($text)
    {
        $re = '/serverRendered:(.*)}}(.*?)\((.*?)\)/m';
        preg_match_all($re, $text, $matches, PREG_SET_ORDER, 0);

        return  str_getcsv($matches[0][3], ',', '"');
    }

    public function lesson($nuxt)
    {
        $lessons = [];
        foreach ($nuxt as $part) {
            $lesson = (object) [
                'link' => getenv('API') . '/api/videos/' . $part['video'] . '/download?quality=hd',
                'title' => $part['title'],
                'slug' => $part['slug'],
                'filename' => sprintf('%02d', (int) $part['order'] - 1) . '-' . $part['slug'] . '.mp4',
            ];
            $lessons[] = $lesson;
        }

        return $lessons;
    }
}
