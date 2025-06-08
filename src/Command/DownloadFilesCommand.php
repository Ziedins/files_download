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
    private const TMP_DIRECTORY = __DIR__ . '/../../tmp';
    private const FILES_DIRECTORY = __DIR__ . '/../../downloads';

    private const KEY_FILE_RESOURCE = 'fileResource';
    private const KEY_FILE_PATH = 'filePath';
    private const KEY_CONTINUE = 'continue';
    private const KEY_STATUS = 'status';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->prepareDirectories();

        $urls = $input->getArgument('urls');
        try {
            $results = $this->downloadUrls($urls);
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . "</error> \n");

            return Command::FAILURE;
        }

        foreach ($results as $key => $result) {
            $resultCode = $result[self::KEY_STATUS];
            $output->writeln(sprintf(
                "%d: Response code : %d | %s | %s",
                $key,
                $resultCode,
                $result[self::KEY_CONTINUE] ? 'Continued' : 'Not Continued',
                $result[self::KEY_FILE_PATH]
            ));

            if (in_array($resultCode, [200, 206, 416])) {
                $this->moveFileToCompletedFolder($result[self::KEY_FILE_PATH]);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param array $urls
     * @return array
     * @throws \Exception
     */
    private function downloadUrls(array $urls): array
    {
        $multiHandle = curl_multi_init();
        $curlHandles = new \WeakMap();

        foreach ($urls as $url) {
            $fileName = $this->getFileNameFromUrl($url);
            $filePath = self::TMP_DIRECTORY . '/' . $fileName;
            $continue = false;
            $lastByte = file_exists($filePath) ? filesize($filePath) : 0;
            $fileResource = fopen($filePath, 'a+');

            $curlHandle = curl_init();

            if ($lastByte > 0) {
                curl_setopt($curlHandle, CURLOPT_RANGE, "$lastByte-");
                $continue = true;
            }

            curl_setopt_array($curlHandle, [
                CURLOPT_URL              => $url,
                CURLOPT_FILE             => $fileResource,
                CURLOPT_CONNECTTIMEOUT   => 3,
                CURLOPT_NOPROGRESS       => false,
                CURLOPT_HEADER           => false,
                CURLOPT_PROGRESSFUNCTION => function ($curlHandle, $bytesToDownload, $bytesDownloaded) use (
                    $url,
                    $continue
                ) {
                    static $lastProgress = [];

                    if ($bytesToDownload > 0) {
                        if ($continue && !isset($lastProgress[$url])) {
                            echo basename($url) . ' Resuming Download: ' . $bytesDownloaded . '/' . $bytesToDownload . "\n";
                        }

                        $percent = round(($bytesDownloaded / $bytesToDownload) * 100);
                        if (!isset($lastProgress[$url]) || $percent - $lastProgress[$url] >= 5) {
                            echo basename($url) . ' Downloaded: ' . $percent . "%\n";
                            $lastProgress[$url] = $percent;
                        }
                    }

                    return 0;
                },
            ]);

            curl_multi_add_handle($multiHandle, $curlHandle);
            $curlHandles[$curlHandle] = [
                self::KEY_FILE_RESOURCE => $fileResource,
                self::KEY_FILE_PATH     => $filePath,
                self::KEY_CONTINUE     => $continue,
            ];
        }

        $stillRunning = 0;

        do {
            $status = curl_multi_exec($multiHandle, $stillRunning);
            if ($stillRunning) {
                curl_multi_select($multiHandle);
            }
        } while ($stillRunning && $status == CURLM_OK);

        $results = [];

        foreach ($curlHandles as $curlHandle => $handleData) {
            fclose($handleData[self::KEY_FILE_RESOURCE]);
            curl_multi_remove_handle($multiHandle, $curlHandle);
            $status = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            curl_close($curlHandle);

            $results[] = [
                self::KEY_STATUS => $status,
                self::KEY_FILE_PATH => $handleData[self::KEY_FILE_PATH],
                self::KEY_CONTINUE => $handleData[self::KEY_CONTINUE]
            ];
        }

        curl_multi_close($multiHandle);

        return $results;
    }

    private function moveFileToCompletedFolder(string $filePath): void
    {
        $fileName =  $this->getFileNameFromUrl($filePath);
        $destinationPath = self::FILES_DIRECTORY . '/' . $fileName;

        if (file_exists($destinationPath)) {
            echo "File already exists in : " . self::FILES_DIRECTORY . " Replacing : ". $fileName . "\n";
            unlink($destinationPath);
        }

        rename($filePath, $destinationPath);
    }

    /**
     * @param string $url
     * @return string
     * @throws \Exception
     */
    private function getFileNameFromUrl(string $url): string
    {
        $urlPath = parse_url($url, PHP_URL_PATH);
        if (!$urlPath) {
            throw new \Exception("Invalid url: " . $url . " The File url must be provided like so example.com/path/to/file");
        }

        return basename($urlPath) ?: md5($url);
    }

    private function prepareDirectories(): void
    {
        if (!is_dir(self::FILES_DIRECTORY)) {
            mkdir(self::FILES_DIRECTORY, 0777, true);
        }

        if (!is_dir(self::TMP_DIRECTORY)) {
            mkdir(self::TMP_DIRECTORY, 0777, true);
        }
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
