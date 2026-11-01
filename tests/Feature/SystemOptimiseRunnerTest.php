<?php

namespace Tests\Feature;

use App\Support\ArtisanRunner;
use App\Support\PhpCliFinder;
use Tests\TestCase;

/**
 * System Settings ▸ Optimise: PHP CLI detection and the artisan runner.
 * Nothing here runs a cache-building command (that would write bootstrap/cache during the test run).
 */
class SystemOptimiseRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PhpCliFinder::forget();
    }

    protected function tearDown(): void
    {
        PhpCliFinder::forget();
        parent::tearDown();
    }

    protected function candidates(): array
    {
        $m = new \ReflectionMethod(PhpCliFinder::class, 'candidates');
        $m->setAccessible(true);

        return $m->invoke(new PhpCliFinder());
    }

    public function test_candidates_follow_the_required_order_without_tilde(): void
    {
        config(['app.php_cli_binary' => '/custom/php']);
        $ver = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $paths = array_column($this->candidates(), 0);
        $sources = array_column($this->candidates(), 1);

        $this->assertSame('/custom/php', $paths[0]);
        $this->assertSame('PHP_CLI_BINARY', $sources[0]);
        $this->assertSame("/opt/plesk/php/{$ver}/bin/php", $paths[1]);
        $this->assertSame('phpenv', $sources[2]);
        $this->assertStringEndsWith("/.phpenv/versions/{$ver}/bin/php", $paths[2]);
        foreach ($paths as $p) {
            $this->assertStringNotContainsString('~', $p);
        }
        $this->assertSame(count($paths), count(array_unique($paths)));
    }

    public function test_tilde_in_php_cli_binary_is_expanded_to_a_home_directory(): void
    {
        config(['app.php_cli_binary' => '~/.phpenv/versions/8.3/bin/php']);
        $first = $this->candidates()[0];

        $this->assertSame('PHP_CLI_BINARY', $first[1]);
        $this->assertStringNotContainsString('~', $first[0]);
        $this->assertStringEndsWith('/.phpenv/versions/8.3/bin/php', $first[0]);
    }

    public function test_configured_working_binary_is_used_and_probed(): void
    {
        config(['app.php_cli_binary' => PHP_BINARY]);
        $php = (new PhpCliFinder())->find();

        $this->assertSame(PHP_BINARY, $php['binary']);
        $this->assertSame('PHP_CLI_BINARY', $php['source']);
        $this->assertSame(PHP_VERSION, $php['version']);
        $this->assertStringContainsString('cli — ok', $php['tried'][0][1]);
    }

    public function test_bad_configured_path_is_reported_and_another_cli_is_found(): void
    {
        config(['app.php_cli_binary' => base_path('no-such-php')]);
        $php = (new PhpCliFinder())->find();

        $this->assertSame([base_path('no-such-php'), 'not found'], $php['tried'][0]);
        $this->assertNotNull($php['binary'], 'the running CLI should be found as a fallback');
        $this->assertTrue(version_compare($php['version'], PhpCliFinder::MIN_VERSION, '>='));
    }

    public function test_a_non_php_program_is_rejected(): void
    {
        $fake = DIRECTORY_SEPARATOR === '\\' ? 'C:\Windows\System32\whoami.exe' : '/bin/echo';
        $probe = new \ReflectionMethod(PhpCliFinder::class, 'probe');
        $probe->setAccessible(true);
        [$ok, $result] = $probe->invoke(new PhpCliFinder(), $fake);

        $this->assertFalse($ok);
        $this->assertNotSame('', $result);
    }

    public function test_child_process_environment_drops_secrets(): void
    {
        $_ENV['CAMP_TEST_DB_PASSWORD'] = 'Sup3rSecret!';
        try {
            $env = PhpCliFinder::cleanEnv('/opt/plesk/php/8.3/bin');
            $this->assertFalse($env['CAMP_TEST_DB_PASSWORD']);
            foreach (['APP_KEY', 'DB_PASSWORD', 'DB_DATABASE'] as $key) {
                if (array_key_exists($key, $env)) {
                    $this->assertFalse($env[$key], $key);
                }
            }
            if (DIRECTORY_SEPARATOR === '/') {
                $this->assertStringStartsWith('/opt/plesk/php/8.3/bin:', $env['PATH']);
            }
        } finally {
            unset($_ENV['CAMP_TEST_DB_PASSWORD']);
        }
    }

    public function test_runner_refuses_commands_outside_the_allow_list(): void
    {
        foreach (['migrate', 'migrate:fresh', 'db:seed', 'db:wipe', 'down'] as $command) {
            [$code, $out] = (new ArtisanRunner())->run($command);
            $this->assertSame(1, $code, $command);
            $this->assertStringContainsString("can't be run", $out);
        }
    }

    public function test_runner_explains_when_no_php_cli_is_found(): void
    {
        $this->mock(PhpCliFinder::class)->shouldReceive('find')->andReturn([
            'binary' => null, 'version' => null, 'source' => null,
            'tried' => [['/opt/plesk/php/8.3/bin/php', 'not found or not executable']],
        ]);

        [$code, $out] = (new ArtisanRunner())->run('optimize');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('/opt/plesk/php/8.3/bin/php — not found or not executable', $out);
        $this->assertStringContainsString('PHP_CLI_BINARY=/opt/plesk/php/', $out);
        $this->assertStringContainsString('Clear all', $out);
    }

    public function test_secrets_are_redacted_from_output(): void
    {
        config(['app.key' => 'base64:TOPSECRETKEYVALUE', 'mail.mailers.smtp.password' => 'MailPass#1']);
        $clean = new \ReflectionMethod(ArtisanRunner::class, 'clean');
        $clean->setAccessible(true);

        $out = $clean->invoke(new ArtisanRunner(), "key base64:TOPSECRETKEYVALUE and MailPass#1\e[32m DONE\e[0m");

        $this->assertSame('key •••• and •••• DONE', $out);
    }
}
