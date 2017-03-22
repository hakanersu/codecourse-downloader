<?php
namespace App\Process;
use App\Exceptions\LoginException;
use Dotenv\Dotenv;
use Symfony\Component\Console\Helper\ProgressBar;

class Core
{
    protected $input;

    protected $output;

    protected $config;

    public function __construct($input, $output)
    {
        $this->input = $output;
        $this->output = $output;

        $this->remote = new Remote(new Parser);
    }

    public function setLoginInformation($username, $password)
    {
        $login = $this->remote->login($username, $password);

        if (!$login) {
            throw new LoginException("Can't do the login..");
        }
    }

    public function gatherSeriesInformation()
    {
        $files = new FileLister();
        $remotes = $this->remote->series($this->output);
        $checkLessons = [];
        echo PHP_EOL;
        $this->output->writeln("<info>Calculating downloading information.</info>");
        foreach ($remotes as $lesson => $count) {
            $locale = $files->lessons($lesson);
            $this->remote->createFolderIfNotExists($lesson, $files);
            if ($locale->count() <= $count) {
                $remoteLessons = $this->remote->getLessons($lesson);
                echo PHP_EOL;
                $this->output->writeln("<info>Downloading series: {$lesson}</info>");
                $progress = new ProgressBar($this->output, $remoteLessons->count());
                $progress->setFormat(' %current% of %max% Downloaded [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
                $progress->start();
                foreach ($remoteLessons as $item) {
                    if (!$files->file->has($lesson.'/'.$item['slug'])) {
                        $this->remote->downloadVideo($item,$lesson, $this->output,$files);
                    }
                    $progress->advance();
                }
                $progress->finish();
            }
        }
    }
}