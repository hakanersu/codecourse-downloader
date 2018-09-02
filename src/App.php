<?php

namespace App;

use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

class App
{
    /**
     * @var SymfonyStyle
     */
    protected $io;
    /**
     * @var Remote
     */
    private $remote;

    /**
     * Constructor.
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
     * Download.
     *
     * @param array $courses
     */
    public function download($output, $courses = [])
    {
        // if we have an empty series we will fetch all series
        if (empty($courses)) {
            $remote = $this->remote->meta();

            // Meta holds information about courses.
            $meta = $remote->meta;

            success("Total {$meta->total} lessons found, fetching  {$meta->last_page} pages for courses.");
            // And as a first page of courses lets get first page of courses.
            $courses = collect($remote->data)->pluck('slug')->toArray();

            // Lets create symfony progress bar instance.
            $progressBar = new ProgressBar($output, $meta->last_page);

            // We can customize progress bar with custom messages.
            $progressBar->setFormat("%status%\n%current%/%max%  [%bar%] %percent:3s%%\n");

            // Lets sets itinital message.
            $progressBar->setMessage('Gathering data...', 'status');

            // Lets get the rest of the courses.
            for ($i = 2; $i <= $meta->last_page; ++$i) {
                $progressBar->advance();
                $progressBar->setMessage("Fetching page: {$i}", 'status');
                // Getting pages from codecourse api.
                $slugs = $this->remote->page($i);
                // And lets merge all lessons to single array.
                $courses = array_merge($courses, $slugs);
            }

            $progressBar->setMessage('Fetching pages completed.', 'status');

            $progressBar->finish();
        }

        // So if courses array given by user we will use that otherwise we will download all courses.
        $courses = collect($courses);

        $files = new FileLister();

        foreach ($courses as $course) {
            // Lets check is there any directory with course slug.
            if (! $files->exists($course)) {
                // otherwise create directory
                $files->file->createDir($course);
            }

            // Get single course and get lessons from it.
            $lessons = $this->remote->getCourse($course)->getPage();

            // Progressbar
            $progressBar = new ProgressBar($output, count($lessons));
            $progressBar->setFormat("%status%\n%current%/%max%  [%bar%] %percent:3s%%\n");
            $progressBar->setMessage('Gathering course data', 'status');

            $progressBar->start();

            foreach ($lessons as $lesson) {
                // Filename with full path.
                $sink = getenv('DOWNLOAD_FOLDER') . "/{$course}/{$lesson->filename}";

                // if we have file we will skip.
                // Maybe i can check file size in future versions.
                if (! $files->exists("{$course}/{$lesson->slug}")) {
                    if (! file_exists($sink)) {
                        $progressBar->setMessage("Downloading ({$course}): {$lesson->title}", 'status');
                        $progressBar->advance();

                        try {
                            $url = $this->remote->getRedirectUrl($lesson->link);

                            $this->remote->web->request('GET', $url, [
                                'sink' => $sink,
                                'progress' => function ($dl_total_size, $dl_size_so_far, $ul_total_size, $ul_size_so_far) use ($progressBar, $course, $lesson) {
                                    $total = \ByteUnits\bytes($dl_total_size)->format('MB');
                                    $sofar = \ByteUnits\bytes($dl_size_so_far)->format('MB');
                                    $percentage = $dl_total_size != '0.00' ? number_format($dl_size_so_far * 100 / $dl_total_size) : 0;
                                    $progressBar->setMessage("Downloading ({$course}): {$lesson->title} {$sofar}/{$total} ({$percentage}%)", 'status');
                                    // It takes too much time to figure this line. Without advance() it was not update message.
                                    // With  this method i can update message.
                                    $progressBar->display();
                                },
                            ]);
                        } catch (\Exception $e) {
                            error("Cant download '{$lesson->title}'. Do you have active subscription?");
                            exit;
                        } catch (GuzzleException $e) {
                            error("Cant download '{$lesson->title}'. Do you have active subscription?");
                            exit;
                        }
                    } else {
                        $progressBar->setMessage("Skipping, already downloaded ({$course}): {$lesson->title}", 'status');
                        $progressBar->advance();
                    }
                }
            }
            $progressBar->setMessage('All videos dowloaded for course: ' . $course, 'status');

            $progressBar->finish();
        }
    }
}
