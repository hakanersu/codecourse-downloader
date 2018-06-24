<?php

namespace App\Commands;

use App\App;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class DownloadCommand extends Command
{
    public function configure()
    {
        $this->setName('download')
            ->setDescription('Download all lessons.')
            ->addArgument('series', InputArgument::OPTIONAL, 'Comma separated series', null);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $username = getenv('CCUSERNAME');
        $password = getenv('CCPASSWORD');

        if (!$username) {
            $username = new Question('Please enter your username: ');
            $username = $helper->ask($input, $output, $username);

            if (empty($username)) {
                error("You have to enter username.");
                return;
            }
        }

        if (!$password) {
            $password = new Question('Please enter your password: ');
            $password = $helper->ask($input, $output, $password);

            if (empty($password)) {
                error("You have to enter password.");
                return;
            }
        }

        $series = [];
        if ($input->getArgument('series')) {
            $series = explode(',', $input->getArgument('series'));
        }

        $app = new App($username, $password, $io);
        $app->download($series);

        success("Finish: " . getenv('DOWNLOAD_FOLDER'));
    }
}
