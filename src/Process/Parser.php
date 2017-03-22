<?php
namespace App\Process;
use Symfony\Component\DomCrawler\Crawler;
use Cocur\Slugify\Slugify;

class Parser
{
    public $crawler;

    public function parse($html)
    {
        $this->crawler = new Crawler($html);
        return $this;
    }

    public function getCrsfToken()
    {
        return $this->crawler->filter("input[name=_token]")->attr('value');
    }

    public function getSeries()
    {
        $array = [];
        $this->crawler->filter('.library-item')->each(function (Crawler $node) use (&$array) {
            $title = $node->filter('h2.library-item__title')->eq(0);
            if (count($title->children())>0) {
                $link = $title->children()->attr('href');
                if (preg_match('/lessons\/(.*)/', $link, $matches)) {
                    $array[$matches[1]] = 0;
                }
                $footer = $node->filter('.library-item__footer')->eq(0);
                if (count($footer->children())>0) {
                    $text = $footer->text();
                    if (preg_match('/(\d+) part[s]?/', $text, $catch)) {
                        $array[$matches[1]] = $catch[1];
                    }
                }
            }
        });
        return $array;
    }

    public  function hasNextPage()
    {
        $node = $this->crawler->filter('[rel=next]');
        if ($node->count() > 0) {
            return $node->attr('href');
        }
        return false;
    }

    public function totalPages()
    {
        $node = $this->crawler->filter('.pagination li');
        $count = $node->count();
        if ($node->count() > 0) {
            return (int) $node->eq($count-2)->text();
        }
        return 0;
    }

    public function getLessonLinks()
    {
        $links = [];
        $slugify = new Slugify();
        $nodes = $this->crawler->filter('.large-8 .container-list__link');
        if ($nodes->count()) {
            $i = 0;
            $nodes->each(function (Crawler $node) use (&$links, $slugify, &$i) {
                array_push($links, [
                    'link' =>$node->attr('href'),
                    'slug' => $slugify->slugify(sprintf("%02d", $i)."-".$node->filter('.container-list__item-header')->text()).'.mp4',
                    'title' => $node->filter('.container-list__item-header')->text()
                ]);
                $i++;
            });
        }
        return $links;
    }

}