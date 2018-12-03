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
        //dd($this->remote->meta()->meta->pagination);
        // if we have an empty series we will fetch all series
        if (empty($courses)) {
            $remote = $this->remote->meta();

            // Meta holds information about courses.
            $meta = $remote->meta->pagination;

            success("Total {$meta->total} lessons found, fetching  {$meta->total_pages} pages for courses.");

            // And as a first page of courses lets get first page of courses.
            $courses = collect($remote->data)->pluck('slug')->toArray();

            $info = collect($remote->data)->mapWithKeys(function ($item) {
                return [$item->slug => ['id' => $item->id, 'title' => $item->title]];
            });

            // Lets create symfony progress bar instance.
            $progressBar = new ProgressBar($output, $meta->total_pages);

            // We can customize progress bar with custom messages.
            $progressBar->setFormat("%status%\n%current%/%max%  [%bar%] %percent:3s%%\n");

            // Lets sets itinital message.
            $progressBar->setMessage('Gathering data...', 'status');

            // Lets get the rest of the courses.
            for ($i = 2; $i <= $meta->total_pages; ++$i) {
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
            $courseTitle = isset($info[$course], $info[$course]['id']) ? "{$info[$course]['id']}-{$info[$course]['title']}" : $course;
            // Lets check is there any directory with course slug.
            if (! $files->exists($courseTitle)) {
                // otherwise create directory
                $files->file->createDir($courseTitle);
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
                $sink = getenv('DOWNLOAD_FOLDER') . "/{$courseTitle}/{$lesson->filename}";

                // if we have file we will skip.
                // Maybe i can check file size in future versions.
                if (! $files->exists("{$courseTitle}/{$lesson->slug}")) {
                    if (! file_exists($sink)) {
                        $progressBar->setMessage("Downloading ({$courseTitle}): {$lesson->title}", 'status');
                        $progressBar->advance();

                        try {
                            $url = $this->remote->getRedirectUrl($lesson->link);

                            $this->remote->web->request('GET', $url, [
                                'sink' => $sink,
                                'progress' => function ($dl_total_size, $dl_size_so_far, $ul_total_size, $ul_size_so_far) use ($progressBar, $courseTitle, $lesson) {
                                    $total = \ByteUnits\bytes($dl_total_size)->format('MB');
                                    $sofar = \ByteUnits\bytes($dl_size_so_far)->format('MB');
                                    $percentage = $dl_total_size != '0.00' ? number_format($dl_size_so_far * 100 / $dl_total_size) : 0;
                                    $progressBar->setMessage("Downloading ({$courseTitle}): {$lesson->title} {$sofar}/{$total} ({$percentage}%)", 'status');
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
                        $progressBar->setMessage("Skipping, already downloaded ({$courseTitle}): {$lesson->title}", 'status');
                        $progressBar->advance();
                    }
                }
            }
            $progressBar->setMessage('All videos dowloaded for course: ' . $courseTitle, 'status');

            $progressBar->finish();
        }
    }
}
