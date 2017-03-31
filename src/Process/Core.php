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

    public function gatherSeriesInformation($series=false)
    {
        $remotes = $this->remote->series($this->output);
        if ($series && $remotes->has($series)) {
            $remotes = collect([$series => $remotes[$series]]);
        }
        echo PHP_EOL;
        $this->output->writeln("<info>Calculating downloading information.</info>");
        $this->downloadSeries($remotes);
    }

    protected function downloadSeries($remotes)
    {
        $files = new FileLister();
        foreach ($remotes as $lesson => $count) {
            $locale = $files->lessons($lesson);
            $this->remote->createFolderIfNotExists($lesson, $files);
            $remoteLessons = $this->remote->getLessons($lesson);
            echo PHP_EOL;
            $this->output->writeln("<info>Downloading series: {$lesson}</info>");
            $progress = new ProgressBar($this->output, $remoteLessons->count());
            $progress->setFormat(' %current% of %max% Downloading: [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
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