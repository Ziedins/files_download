<?php
declare(strict_types = 1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:download-files')]
class DownloadFilesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $urls = $input->getArgument('urls');

        $multiHandle = curl_multi_init();
        $curlHandles = [];

        foreach ($urls as $url) {
            $curlHandle = curl_init();
            curl_setopt_array($curlHandle, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
//                CURLOPT_FOLLOWLOCATION => true,
            ]);
            curl_multi_add_handle($multiHandle, $curlHandle);
            $curlHandles[] = $curlHandle;
        }

        $stillRunning = 0;
        do {
            $status = curl_multi_exec($multiHandle, $stillRunning);
            curl_multi_select($multiHandle);
        } while ($stillRunning > 0 && $status == CURLM_OK);

        foreach ($curlHandles as $key => $curlHandle) {
            $content = curl_multi_getcontent($curlHandle);
            $error = curl_error($curlHandle);
            $info = curl_getinfo($curlHandle);
            curl_multi_remove_handle($multiHandle, $curlHandle);
            curl_close($curlHandle);

            if ($error !== '') {
                $output->writeln("<error>[{$key}] Error: $error</error>");
            } else {
                $url = $info['url'] ?? 'Url not set';
                $output->writeln("<info>[{$key}] $url</info>");
                $output->writeln(substr($content, 0, 200) . "\n");
            }
        }

        curl_multi_close($multiHandle);

        return Command::SUCCESS;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Downloads files to downloads directory')
            ->addArgument('urls', InputArgument::IS_ARRAY|InputArgument::REQUIRED, 'URL or space seperated URLs of the file/s to downlaod')
            ->setHelp('This command allows you to download files from the provided url ...')
        ;
    }
}
