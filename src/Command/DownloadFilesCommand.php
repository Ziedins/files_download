<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(name: 'app:download-files')]
class DownloadFilesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $client = HttpClient::create();
        try {
            $response = $client->request('GET', $url);
            $output->writeln([$url, $response->getStatusCode()]);
            return Command::SUCCESS;
        } catch (TransportExceptionInterface $e) {
            $output->writeln('<error>Error : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Downloads files to downloads directory')
            ->addArgument('url', InputArgument::REQUIRED, 'URL of the file')
            ->setHelp('This command allows you to download files from the specified url ...')
        ;
    }
}
