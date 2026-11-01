<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Runs artisan commands for the System Settings ▸ System tab.
 *
 * Commands that build caches (config:cache, route:cache …) boot a *fresh* application, which replaces the
 * running one inside a web request (lost login, .env-only config). Those run as a separate `php artisan`
 * process — the same as typing them over SSH — using the PHP CLI found by PhpCliFinder. Clearing commands
 * only delete files and run in-process.
 *
 * Only the commands in ALLOWED can run (no migrate / db / seed from here). Output is cleaned of colour codes
 * and of any secret values (APP_KEY, DB / mail passwords …) before it is shown or logged.
 */
class ArtisanRunner
{
    public const SEPARATE_PROCESS = ['optimize', 'config:cache', 'route:cache', 'event:cache'];

    public const ALLOWED = ['cache:clear', 'view:clear', 'optimize:clear', 'optimize', 'config:cache', 'route:cache', 'event:cache'];

    /** Seconds before a separate `php artisan` process is stopped. */
    public const TIMEOUT = 180;

    /** @return array{0: int, 1: string} exit code and plain-text output */
    public function run(string $command): array
    {
        if (!in_array($command, static::ALLOWED, true)) {
            return [1, "Error: \"{$command}\" can't be run from System Settings."];
        }

        if (!in_array($command, static::SEPARATE_PROCESS, true)) {
            $code = Artisan::call($command);

            return [$code, $this->clean(Artisan::output())];
        }

        return $this->runSeparately($command);
    }

    protected function runSeparately(string $command): array
    {
        if (!PhpCliFinder::processesAllowed()) {
            return [1, "Error: this server's PHP settings don't allow starting a process (proc_open is in disable_functions).\n"
                . "# Fix: Plesk ▸ Domains ▸ your domain ▸ PHP Settings ▸ disable_functions — remove proc_open,\n"
                . "# or run over SSH:  php artisan {$command}"];
        }

        $cacheDir = base_path('bootstrap/cache');
        if (!is_writable($cacheDir)) {
            return [1, "Error: bootstrap/cache is not writable by the website user, so the cache files can't be saved.\n"
                . '# Fix over SSH (as the site owner): chmod -R ug+rwX bootstrap/cache storage'];
        }

        $php = app(PhpCliFinder::class)->find();
        if (!$php['binary']) {
            $lines = ['Error: no PHP ' . PhpCliFinder::MIN_VERSION . '+ command-line binary could be run from the website. Checked:'];
            foreach ($php['tried'] as [$path, $result]) {
                $lines[] = "#   {$path} — {$result}";
            }
            $lines[] = '# Fix: add the CLI path to .env, e.g.  PHP_CLI_BINARY=/opt/plesk/php/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '/bin/php';
            $lines[] = '#      then press "Clear all" here (or run php artisan config:clear) so the new .env value is read.';
            Log::warning('System Settings: PHP CLI not found for "' . $command . '"', ['tried' => $php['tried']]);

            return [1, implode("\n", $lines)];
        }

        $intro = "# using PHP {$php['version']} CLI at {$php['binary']} ({$php['source']})";

        $process = new Process(
            [$php['binary'], base_path('artisan'), $command, '--no-interaction', '--no-ansi'],
            base_path(),
            PhpCliFinder::cleanEnv(dirname($php['binary'])),
            null,
            static::TIMEOUT
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            $process->stop(0);

            return [1, $intro . "\nError: the command took longer than " . static::TIMEOUT . " seconds and was stopped. Run it over SSH to see what is slow."];
        } catch (\Throwable $e) {
            Log::error('System Settings: could not start "php artisan ' . $command . '": ' . $this->redact($e->getMessage()));

            return [1, $intro . "\nError: the process could not be started — " . $this->redact($e->getMessage())];
        }

        $out = $this->clean($process->getOutput() . "\n" . $process->getErrorOutput());
        $code = (int) $process->getExitCode();
        if ($code !== 0) {
            Log::warning('System Settings: "php artisan ' . $command . '" ended with code ' . $code, ['output' => mb_substr($out, 0, 2000)]);
        }

        return [$code, trim($intro . "\n" . $out)];
    }

    protected function clean(string $output): string
    {
        $output = trim(preg_replace('/\e\[[\d;]*m/', '', $output));
        $output = preg_replace('/ \.{3,} /', ' … ', $output);

        return $this->redact($output);
    }

    /** Replace any secret value that could appear in an error message with ••••. */
    protected function redact(string $text): string
    {
        $secrets = [
            config('app.key'),
            config('mail.mailers.smtp.password'),
            config('database.connections.' . config('database.default') . '.password'),
            config('database.redis.default.password'),
        ];
        foreach ($_ENV + $_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && preg_match('/(PASS|SECRET|_KEY|TOKEN)/i', $key)) {
                $secrets[] = $value;
            }
        }
        foreach (array_unique(array_filter($secrets, fn ($s) => is_string($s) && strlen($s) >= 4)) as $secret) {
            $text = str_replace($secret, '••••', $text);
        }

        return $text;
    }
}
