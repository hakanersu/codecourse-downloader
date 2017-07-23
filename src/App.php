<?php
namespace App;

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
     * @param array $series
     */
    public function download($series = [])
    {
        $this->io->title('Start downloading series');

        $seriesCollection = $this->remote->fetchSeries();

        if (!empty($series)) {
            $seriesCollection = $seriesCollection->filter(function($value, $key) use ($series) {
                return in_array($key, $series);
            });
        }

        $this->io->section("Downloading found series...");
        $files = new FileLister();
        foreach ($seriesCollection as $lesson => $count) {
            $this->remote->createFolder($lesson, $files);
            $remoteLessons = $this->remote->fetchLessons($lesson);

            $this->io->text("Downloading series: {$lesson}");

            $progress = new ProgressBar($this->io, $remoteLessons->count());
            $progress->setFormat(' %current% of %max% Downloading: [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progress->start();
            foreach ($remoteLessons as $file) {
                if (!$files->file->has("{$lesson}/{$file->getFilename()}")) {
                    $this->remote->downloadFile($file, $lesson);
                }

                $progress->advance();
            }
            $progress->finish();
        }
    }
}