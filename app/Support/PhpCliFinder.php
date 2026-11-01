<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Finds a PHP *command-line* binary that can run `php artisan …` from a web request.
 *
 * Under PHP-FPM (Plesk, cPanel …) PHP_BINARY is the php-fpm daemon, not the CLI, and open_basedir usually
 * hides /opt/plesk or the user's home from file checks (is_file / is_executable return false even though the
 * file exists). So every candidate is *probed* — run with `-r 'echo PHP_VERSION…'` — which open_basedir does
 * not restrict. A candidate is accepted when it runs, reports the `cli` SAPI and a PHP version the app supports.
 *
 * Order: PHP_CLI_BINARY (config app.php_cli_binary) → Plesk /opt/plesk/php/X.Y/bin/php → ~/.phpenv/versions/X.Y/bin/php
 * → other common locations → PATH. X.Y is the version the website runs on; a CLI with the same X.Y is preferred.
 */
class PhpCliFinder
{
    /** composer.json: "php": "^8.0.2" */
    public const MIN_VERSION = '8.0.2';

    /** Seconds allowed for one probe. */
    protected const PROBE_TIMEOUT = 15;

    protected static ?array $memo = null;

    /**
     * @return array{binary: ?string, version: ?string, source: ?string, tried: array<int, array{0: string, 1: string}>}
     *         tried = [path, result] for every candidate checked, for the error message / health check.
     */
    public function find(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        $tried = [];
        $fallback = null;
        $want = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        foreach ($this->candidates() as [$path, $source]) {
            [$ok, $result, $version] = $this->probe($path);
            $tried[] = [$path, $result];
            if (!$ok) {
                continue;
            }
            $hit = ['binary' => $path, 'version' => $version, 'source' => $source];
            // An explicit PHP_CLI_BINARY wins; otherwise prefer the same X.Y as the website.
            if ($source === 'PHP_CLI_BINARY' || str_starts_with($version, $want . '.')) {
                return static::$memo = $hit + ['tried' => $tried];
            }
            $fallback ??= $hit;
        }

        return static::$memo = ($fallback ?? ['binary' => null, 'version' => null, 'source' => null]) + ['tried' => $tried];
    }

    public static function forget(): void
    {
        static::$memo = null;
    }

    /** Can this PHP start child processes at all? (Plesk / hosting panels often disable proc_open.) */
    public static function processesAllowed(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && !in_array('proc_open', $disabled, true);
    }

    /** @return array<int, array{0: string, 1: string}> [absolute path, where it came from] — unique, in priority order */
    protected function candidates(): array
    {
        $ver = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $list = [];

        $configured = trim((string) config('app.php_cli_binary'));
        if ($configured !== '') {
            foreach ($this->expandHome($configured) as $path) {
                $list[] = [$path, 'PHP_CLI_BINARY'];
            }
        }

        $list[] = ["/opt/plesk/php/{$ver}/bin/php", 'Plesk'];
        foreach ($this->homeDirs() as $home) {
            $list[] = ["{$home}/.phpenv/versions/{$ver}/bin/php", 'phpenv'];
        }

        // The CLI that belongs to the running PHP: php-fpm lives in …/sbin, the CLI in …/bin.
        if (PHP_BINARY !== '') {
            if (in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
                $list[] = [PHP_BINARY, 'running PHP'];
            }
            $list[] = [dirname(PHP_BINARY, 2) . '/bin/php', 'running PHP'];
        }
        $list[] = [PHP_BINDIR . '/php', 'running PHP'];

        foreach (["/usr/bin/php{$ver}", '/usr/local/bin/php', '/usr/bin/php'] as $path) {
            $list[] = [$path, 'system'];
        }
        foreach (@glob('/opt/plesk/php/*/bin/php') ?: [] as $path) {
            $list[] = [$path, 'Plesk'];
        }
        foreach ($this->homeDirs() as $home) {
            foreach (@glob("{$home}/.phpenv/versions/*/bin/php") ?: [] as $path) {
                $list[] = [$path, 'phpenv'];
            }
        }
        if ($inPath = (new ExecutableFinder())->find('php')) {
            $list[] = [$inPath, 'PATH'];
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            $list[] = ['C:\xampp\php\php.exe', 'system'];
        }

        $seen = [];

        return array_values(array_filter($list, function ($c) use (&$seen) {
            $key = str_replace('\\', '/', $c[0]);
            if ($c[0] === '' || isset($seen[$key])) {
                return false;
            }

            return $seen[$key] = true;
        }));
    }

    /**
     * Home directories of the website user, without relying on "~" or $HOME being set
     * (php-fpm clears the environment): posix user database, $HOME, the owner of the project folder,
     * and /home/<user>/ when the project lives under it.
     */
    protected function homeDirs(): array
    {
        $homes = [];
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $homes[] = @posix_getpwuid(posix_geteuid())['dir'] ?? null;
            $owner = @fileowner(base_path());
            if ($owner !== false) {
                $homes[] = @posix_getpwuid($owner)['dir'] ?? null;
            }
        }
        $homes[] = getenv('HOME') ?: ($_SERVER['HOME'] ?? null);
        if (DIRECTORY_SEPARATOR === '\\') {
            $homes[] = getenv('USERPROFILE') ?: null;
        }
        if (preg_match('#^(/home/[^/]+)/#', base_path() . '/', $m)) {
            $homes[] = $m[1];
        }

        return array_values(array_unique(array_filter(array_map(fn ($h) => $h ? rtrim($h, '/') : null, $homes))));
    }

    /** "~/x" or "$HOME/x" → one absolute path per known home directory. */
    protected function expandHome(string $path): array
    {
        if (!preg_match('#^(~|\$HOME|\$\{HOME\})(/.*)?$#', $path, $m)) {
            return [$path];
        }

        $homes = $this->homeDirs();

        // No home directory known: keep it as written so it still shows up ("not found") in the report.
        return $homes ? array_map(fn ($home) => $home . ($m[2] ?? ''), $homes) : [$path];
    }

    /** @return array{0: bool, 1: string, 2: ?string} ok, human result, version */
    protected function probe(string $path): array
    {
        // Skip quickly when we *can* see the file is missing (open_basedir can hide existing files, so only trust "missing" when allowed to look).
        if ($this->visible($path)) {
            if (!@is_file($path)) {
                return [false, 'not found', null];
            }
            if (DIRECTORY_SEPARATOR === '/' && !@is_executable($path)) {
                return [false, 'not executable by the website user', null];
            }
        }

        try {
            $process = new Process([$path, '-d', 'display_errors=stderr', '-r', 'echo PHP_VERSION, "|", PHP_SAPI;'], base_path(), static::cleanEnv(dirname($path)), null, static::PROBE_TIMEOUT);
            $process->run();
        } catch (\Throwable $e) {
            return [false, 'could not be started', null];
        }

        if (!$process->isSuccessful()) {
            return [false, $process->getExitCode() === 127 || $process->getExitCode() === 126 ? 'not found or not executable' : 'failed to run (exit ' . $process->getExitCode() . ')', null];
        }
        if (!preg_match('/^(\d+\.\d+\.\d+)[^|]*\|(\S+)$/', trim($process->getOutput()), $m)) {
            return [false, 'unexpected output, not a PHP binary', null];
        }
        [, $version, $sapi] = $m;
        if ($sapi !== 'cli') {
            return [false, "PHP {$version} but SAPI is {$sapi}, not cli", $version];
        }
        if (version_compare($version, static::MIN_VERSION, '<')) {
            return [false, "PHP {$version} is too old (needs " . static::MIN_VERSION . '+)', $version];
        }

        return [true, "PHP {$version} cli — ok", $version];
    }

    /** Is the path inside open_basedir (or no open_basedir set), so file checks are meaningful? */
    protected function visible(string $path): bool
    {
        $base = (string) ini_get('open_basedir');
        if ($base === '') {
            return true;
        }
        foreach (explode(PATH_SEPARATOR, $base) as $dir) {
            if ($dir !== '' && str_starts_with($path, rtrim($dir, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Environment for child processes. Only PATH / HOME / locale are passed — every other inherited variable is
     * removed (false = unset in Symfony Process), so the child reads the project's .env itself and no request
     * data or already-loaded secrets (DB_PASSWORD …) are handed over.
     */
    public static function cleanEnv(?string $binDir = null): array
    {
        $inherited = array_keys($_ENV + $_SERVER + (getenv() ?: []));
        $env = array_fill_keys(array_filter($inherited, fn ($k) => is_string($k) && $k !== '' && !str_contains($k, '=')), false);

        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? null);
        if (!$home && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $home = @posix_getpwuid(posix_geteuid())['dir'] ?? null;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows (local XAMPP): processes need these to start at all.
            foreach (['PATH', 'Path', 'SystemRoot', 'SYSTEMROOT', 'TEMP', 'TMP', 'WINDIR', 'COMSPEC', 'PATHEXT', 'USERPROFILE'] as $k) {
                unset($env[$k]);
            }

            return $env;
        }

        $env['PATH'] = ($binDir ? $binDir . ':' : '') . '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
        $env['LANG'] = 'C.UTF-8';
        if ($home) {
            $env['HOME'] = $home;
        }

        return $env;
    }
}
