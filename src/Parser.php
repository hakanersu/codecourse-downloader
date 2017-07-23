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

    /**
     * Get CRSF token
     *
     * @return null | string
     */
    public function getCrsfToken()
    {
        return $this->crawler->filter("input[name=_token]")->attr('value');
    }

    /**
     * Get series
     *
     * @return array
     */
    public function getSeries()
    {
        $series = [];
        $this->crawler->filter('.library-item')->each(function(Crawler $node) use (&$series) {
            $title = $node->filter('h2.library-item__title')->eq(0);

            if (count($title->children()) > 0) {
                $link = $title->children()->attr('href');
                if (preg_match('/lessons\/(.*)/', $link, $matches)) {
                    $series[$matches[1]] = 0;
                }

                $footer = $node->filter('.library-item__footer')->eq(0);
                if (count($footer->children()) > 0) {
                    $text = $footer->text();
                    if (preg_match('/(\d+) part[s]?/', $text, $catch)) {
                        $series[$matches[1]] = $catch[1];
                    }
                }
            }
        });

        return $series;
    }

    /**
     * Get next page
     *
     * @return bool|null|string
     */
    public  function getNextPage()
    {
        $node = $this->crawler->filter('[rel=next]');
        if ($node->count() > 0) {
            return $node->attr('href');
        }

        return false;
    }

    /**
     * Total pages
     *
     * @return int
     */
    public function totalPages()
    {
        $node = $this->crawler->filter('.pagination li');
        $count = $node->count();

        if ($node->count() > 0) {
            return (int) $node->eq($count-2)->text();
        }

        return 0;
    }

    /**
     * Get lesson links
     *
     * @return Collection
     */
    public function getLessonLinks()
    {
        $slugify = new Slugify();
        $filesCollection = new Collection();

        $nodes = $this->crawler->filter('.large-8 .container-list__link');

        if ($nodes->count()) {
            $linkCrawler = $this->crawler->selectLink('Download full code');
            if ($linkCrawler->count()) {
                $zip = new Zip();
                $zip->setLink($linkCrawler->link()->getUri());
                $zip->setFilename("code");
                $zip->setTitle("Full code");

                $filesCollection->push($zip);
            }

            $nodes->each(function(Crawler $node, $i) use ($filesCollection, $slugify) {
                $video = new Video();
                $video->setLink($node->attr('href'));
                $video->setFilename(sprintf("%02d", $i)."-".$node->filter('.container-list__item-header')->text());
                $video->setTitle($node->filter('.container-list__item-header')->text());

                $filesCollection->push($video);
            });
        }

        return $filesCollection;
    }
}