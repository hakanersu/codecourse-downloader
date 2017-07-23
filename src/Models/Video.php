<?php

namespace App\Models;


use Cocur\Slugify\Slugify;

class Video
{
    /**
     * @var string $link
     */
    public $link;

    /**
     * @var string $filename
     */
    public $filename;

    /**
     * @var string $title
     */
    public $title;

    /**
     * @return string
     */
    public function getLink()
    {
        return str_replace('lessons', 'videos', $this->link);
    }

    /**
     * @param string $link
     */
    public function setLink($link)
    {
        $this->link = $link;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $name
     */
    public function setFilename($name)
    {
        $slugify = new Slugify();

        $this->filename = "{$slugify->slugify($name)}.mp4";
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }
}