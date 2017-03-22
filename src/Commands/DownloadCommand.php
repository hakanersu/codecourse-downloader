<?php

namespace App\Commands;

use App\Process\Core;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class DownloadCommand extends Command
{
    public function configure()
    {
        $this->setName('download')
            ->setDescription('Download all lessons.')
            ->addArgument('force', InputArgument::OPTIONAL);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!getenv('USERNAME')) {
            $helper = $this->getHelper('question');
            $username = new Question('Please enter your username: ');
            $username = $helper->ask($input, $output, $username);

            if ($username == '') {
                $output->writeln("<error>You have to enter username.</error>");
                exit;
            }

            $password = new Question('Please enter your password: ');
            $password = $helper->ask($input, $output, $password);

            if ($password == '') {
                $output->writeln("<error>You have to enter password.</error>");
                exit;
            }
        } else {
            $username = getenv('USERNAME');
            $password= getenv('PASSWORD');
        }
        $core = new Core($input, $output);
        $core->setLoginInformation($username, $password);
        $core->gatherSeriesInformation();
    }
}