<?php
namespace App;

use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

class App
{
    /**
     * @var Remote $remote
     */
    private $remote;

    /**
     * @var SymfonyStyle $io
     */
    protected $io;

    /**
     * Constructor
     *
     * @param $username
     * @param $password
     * @param SymfonyStyle $io
     */
    public function __construct($username, $password, $io)
    {
        $this->io = $io;
        $this->remote = new Remote($username, $password, $io);
    }

    /**
     * Download
     *
     * @param array $courses
     */
    public function download($courses = [])
    {
        // if we have an empty series we will fetch all series
        if (empty($courses)) {
            $remote = $this->remote->meta();
            // Meta holds information about courses.
            $meta = $remote->meta;
            // And as a first page of courses lets get first page of courses.
            $courses = collect($remote->data)->pluck('slug')->toArray();
            // Lets get the rest of the courses.
            for ($i = 2; $i <= $meta->last_page; $i++) {
                $slugs = $this->remote->page($i);
                $courses = array_merge($courses, $slugs);
            }
        }
        // Collect courses.
        $courses = collect($courses);

        $files = new FileLister();

        $courses->map(/**
         * @param $item
         * @param $key
         */
            function ($item, $key) use ($files) {
            if (!$files->exists($item)) {
                $files->file->createDir($item);
            }
            
            // fetch remote lesson on courses.
            $lessons = $this->remote->getCourse($item)->getPage();
            $this->getLessons($item, $lessons);
        });
    }

    public function getLessons($item, $lessons)
    {
        $file = new FileLister();

        foreach ($lessons as $lesson) {
            if (!$file->exists("{$item}/{$lesson->title}")) {
                // Download video.
                success("Downloading video: {$lesson->title}");

                $this->remote->downloadFile($item, $lesson);

            }
        }
    }
}
