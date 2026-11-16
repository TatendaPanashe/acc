<?php

namespace App\Services\Fiscal;

use Symfony\Component\Process\Process;

/**
 * Runs the zimra-fdms CLI (node_modules/zimra-fdms) against one company's profile directory.
 * Bound in the container so tests can swap in a fake.
 */
class FdmsCli
{
    /**
     * @param  list<string>  $args  command and options, without --profile
     * @return array|string decoded JSON when $json, otherwise the raw output
     */
    public function run(array $args, string $profileDir, bool $json = false): array|string
    {
        $command = [config('zimra.node_binary'), config('zimra.cli'), ...$args, '--profile', $profileDir];
        if ($json) {
            $command[] = '--json';
        }

        $env = array_filter([
            'ZIMRA_BASE_URL' => config('zimra.base_url'),
            'ZIMRA_CA' => config('zimra.ca'),
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin',
        ]);

        $process = new Process($command, base_path(), $env, null, config('zimra.timeout'));
        try {
            $process->run();
        } catch (\Throwable $e) {
            throw new FdmsException('Could not run the zimra-fdms tool: '.$e->getMessage(), previous: $e);
        }

        $out = trim($process->getOutput());
        if (!$process->isSuccessful()) {
            $err = trim($process->getErrorOutput()) ?: $out ?: 'zimra-fdms exited with code '.$process->getExitCode();
            throw new FdmsException(preg_replace('/^error:\s*/', '', $err));
        }

        if (!$json) {
            return $out;
        }
        $decoded = json_decode($out, true);
        if (!is_array($decoded)) {
            throw new FdmsException('ZIMRA FDMS returned an unreadable response.');
        }

        return $decoded;
    }
}
