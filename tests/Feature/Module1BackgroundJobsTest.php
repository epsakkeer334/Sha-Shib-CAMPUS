<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Settings\SystemSettingsComponent;
use App\Jobs\SendEmailJob;
use App\Models\Admin\AuditTrail;
use App\Models\Admin\NotificationLog;
use App\Models\User;
use App\Services\EmailService;
use App\Support\BackgroundJobs;
use App\Support\Settings;
use Database\Seeders\admin\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * System Settings ▸ Background Jobs: queue worker switch, scheduler / worker check-ins, fallback, "send waiting now".
 * SMTP2GO is faked. Runs on the *_testing database, rolled back.
 */
class Module1BackgroundJobsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Settings::flush();

        config(['services.smtp2go.api_key' => 'api-TEST00001234', 'services.smtp2go.sender_email' => 'no-reply@camp.test', 'queue.default' => 'sync']);
        Http::preventStrayRequests();
        Http::fake(['api.smtp2go.com/*' => Http::response(['data' => ['succeeded' => 1, 'failed' => 0, 'failures' => [], 'email_id' => 'Q-OK']], 200)]);
        Settings::set(['mail.enabled' => true]);

        $this->super = User::create(['name' => 'Super', 'email' => uniqid('su') . '@user.test', 'password' => bcrypt('password'), 'status' => true]);
        $this->super->assignRole('super-admin');
    }

    protected function tearDown(): void
    {
        Settings::flush();
        parent::tearDown();
    }

    protected function sendOne(): NotificationLog
    {
        return app(EmailService::class)->sendCustom($this->super, 'Hello', 'Queued body');
    }

    public function test_worker_off_sends_after_the_response(): void
    {
        Bus::fake();
        BackgroundJobs::beat(BackgroundJobs::SCHEDULER);
        $this->sendOne();

        $this->assertSame('direct', BackgroundJobs::mode());
        Bus::assertDispatchedAfterResponse(SendEmailJob::class);
    }

    public function test_worker_on_without_scheduler_falls_back_to_after_the_response(): void
    {
        Bus::fake();
        Settings::set(['queue.worker_enabled' => true]);
        $this->sendOne();

        $this->assertSame('fallback', BackgroundJobs::mode());
        Bus::assertDispatchedAfterResponse(SendEmailJob::class);

        // Scheduler last seen 10 minutes ago: still the fallback
        BackgroundJobs::beat(BackgroundJobs::SCHEDULER);
        DB::table('background_heartbeats')->where('name', 'scheduler')->update(['last_beat_at' => now()->subMinutes(10)]);
        $this->assertSame('fallback', BackgroundJobs::mode());
        $this->assertSame('late', BackgroundJobs::status(BackgroundJobs::SCHEDULER)['state']);
    }

    public function test_heartbeats_count_hits_today_and_the_last_hour(): void
    {
        $this->assertSame('never', BackgroundJobs::status(BackgroundJobs::SCHEDULER)['state']);

        BackgroundJobs::beat(BackgroundJobs::SCHEDULER);
        BackgroundJobs::beat(BackgroundJobs::SCHEDULER);
        $status = BackgroundJobs::status(BackgroundJobs::SCHEDULER);

        $this->assertSame('running', $status['state']);
        $this->assertSame(2, $status['today']);
        $this->assertSame(2, $status['total']);
        $this->assertSame(1, $status['hour']); // same minute
        $this->assertCount(60, $status['strip']);
        $this->assertTrue(end($status['strip'])['hit']);

        // Yesterday's hits do not count for today
        DB::table('background_heartbeats')->where('name', 'scheduler')->update(['beats_date' => now()->subDay()->toDateString()]);
        BackgroundJobs::beat(BackgroundJobs::SCHEDULER);
        $this->assertSame(1, BackgroundJobs::status(BackgroundJobs::SCHEDULER)['today']);
        $this->assertSame(3, BackgroundJobs::status(BackgroundJobs::SCHEDULER)['total']);
    }

    public function test_the_worker_checks_in_only_for_the_emails_queue(): void
    {
        event(new Looping('database', 'default'));
        $this->assertSame('never', BackgroundJobs::status(BackgroundJobs::WORKER)['state']);

        event(new Looping('database', 'emails'));
        $this->assertSame('running', BackgroundJobs::status(BackgroundJobs::WORKER)['state']);
    }

    public function test_worker_on_queues_and_the_worker_sends_and_counts(): void
    {
        Settings::set(['queue.worker_enabled' => true]);
        BackgroundJobs::beat(BackgroundJobs::SCHEDULER);

        $log = $this->sendOne();
        $this->assertSame('worker', BackgroundJobs::mode());
        $this->assertSame(1, BackgroundJobs::waiting());
        $this->assertSame('pending', $log->fresh()->status);

        // "Send waiting emails now" runs the same worker inside the request
        Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)->set('tab', 'jobs')
            ->assertSee('Do I need a queue worker?')
            ->call('runWaiting')->assertDispatchedBrowserEvent('show-toast');

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame('Q-OK', $log->fresh()->provider_message_id);
        $this->assertSame(0, BackgroundJobs::waiting());
        $this->assertSame(1, BackgroundJobs::status(BackgroundJobs::WORKER)['processed']);
        $this->assertSame('never', BackgroundJobs::status(BackgroundJobs::WORKER)['state']); // a manual run is not a cron check-in
        $this->assertStringStartsWith('Ran the email queue now (1 of 1', (string) data_get(AuditTrail::where('module', 'System Settings')->latest('id')->first()->meta, 'description'));
    }

    public function test_switch_is_saved_from_the_background_jobs_tab(): void
    {
        Livewire::actingAs($this->super)->test(SystemSettingsComponent::class)->set('tab', 'jobs')
            ->assertSee('Set up the scheduler')->assertSee('schedule:run')
            ->set('s.queue.worker_enabled', true)->call('save')->assertHasNoErrors();

        Settings::flush();
        $this->assertTrue(Settings::bool('queue.worker_enabled'));
        $this->assertTrue(BackgroundJobs::shouldRunWorker());
    }

    public function test_scheduler_runs_the_heartbeat_and_the_email_worker(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(fn ($e) => $e->description === 'camp:scheduler-heartbeat'));
        $worker = $events->first(fn ($e) => str_contains((string) $e->command, 'queue:work database --queue=emails --stop-when-empty'));
        $this->assertNotNull($worker);

        // Runs only while switched on or while emails are still waiting
        $this->assertFalse($worker->filtersPass($this->app));
        Settings::set(['queue.worker_enabled' => true]);
        $this->assertTrue($worker->filtersPass($this->app));
    }
}
