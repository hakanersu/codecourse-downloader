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
            $progressBar = new ProgressBar($output, $meta->last_page);

            $progressBar->setFormat("%status%\n%current%/%max%  [%bar%] %percent:3s%%\n");
            $progressBar->setMessage('Gathering data...', 'status');

            // Lets get the rest of the courses.
            for ($i = 2; $i <= $meta->last_page; $i++) {
                $progressBar->advance();
                $progressBar->setMessage("Fetching page: {$i}", 'status');

                $slugs = $this->remote->page($i);
                $courses = array_merge($courses, $slugs);
            }
            $progressBar->setMessage('Fetching pages complated.', 'status');

            $progressBar->finish();
        }
        // Collect courses.
        $courses = collect($courses);

        $files = new FileLister();

        foreach ($courses as $course) {
            if (! $files->exists($course)) {
                $files->file->createDir($course);
            }
            $lessons = $this->remote->getCourse($course)->getPage();
            $progressBar = new ProgressBar($output, count($lessons));

            $progressBar->setFormat("%status%\n%current%/%max%  [%bar%] %percent:3s%%\n");
            $progressBar->setMessage('Gathering course data', 'status');

            $progressBar->start();

            foreach ($lessons as $lesson) {
                $sink = getenv('DOWNLOAD_FOLDER')."/{$course}/{$lesson->filename}";
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
                                    $progressBar->setMessage("Downloading ({$course}): {$lesson->title} {$sofar}/{$total}", 'status');
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
            $progressBar->setMessage('All videos dowloaded for course: '.$course, 'status');

            $progressBar->finish();
        }
    }
}
