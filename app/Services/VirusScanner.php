<?php

namespace App\Services;

use App\Exceptions\VirusScanException;
use Symfony\Component\Process\Process;
use Throwable;

class VirusScanner
{
    public function scan(string $path): void
    {
        if (! config('virus-scanner.enabled')) {
            return;
        }

        $process = new Process([
            config('virus-scanner.binary'),
            '--no-summary',
            '--infected',
            $path,
        ]);
        $process->setTimeout(config('virus-scanner.timeout'));

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new VirusScanException('The antivirus scanner could not be started.', false, $exception);
        }

        if ($process->getExitCode() === 0) {
            return;
        }

        if ($process->getExitCode() === 1) {
            throw new VirusScanException('ClamAV detected malware in the uploaded assessment form.', true);
        }

        throw new VirusScanException(
            'The antivirus scan failed: '.trim($process->getErrorOutput() ?: $process->getOutput())
        );
    }
}
