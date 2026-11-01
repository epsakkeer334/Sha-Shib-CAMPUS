<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs artisan commands for the System Settings ▸ System tab.
 *
 * Commands that build caches (config:cache, route:cache …) boot a *fresh* application, which replaces the
 * running one inside a web request (lost login, .env-only config). Those run as a separate `php artisan`
 * process — the same as typing them over SSH. Clearing commands only delete files and run in-process.
 */
class ArtisanRunner
{
    public const SEPARATE_PROCESS = ['optimize', 'config:cache', 'route:cache', 'event:cache'];

    /** @return array{0: int, 1: string} exit code and plain-text output */
    public function run(string $command): array
    {
        if (!in_array($command, static::SEPARATE_PROCESS, true)) {
            $code = Artisan::call($command);

            return [$code, $this->clean(Artisan::output())];
        }

        // PhpExecutableFinder also looks in PHP_BINDIR, so php-fpm installs (e.g. ~/.phpenv/versions/8.3) find their CLI php.
        $php = config('app.php_cli_binary') ?:(new PhpExecutableFinder())->find(false);
        if (!$php) {
            return [1, 'Could not find the PHP command-line binary. Set PHP_CLI_BINARY in .env (e.g. ~/.phpenv/versions/8.3/bin/php) or run the command over SSH.'];
        }

        $process = new Process([$php, base_path('artisan'), $command, '--no-interaction'], base_path(), null, null, 180);
        $process->run();

        return [(int) $process->getExitCode(), $this->clean($process->getOutput() . $process->getErrorOutput())];
    }

    protected function clean(string $output): string
    {
        $output = trim(preg_replace('/\e\[[\d;]*m/', '', $output));

        return preg_replace('/ \.{3,} /', ' … ', $output);
    }
}
