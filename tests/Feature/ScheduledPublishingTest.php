<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use IvanMercedes\FlexFields\Events\RecordPublished;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Tests\TestCase;
use RuntimeException;

class ScheduledPublishingTest extends TestCase
{
    protected RecordDomainService $records;

    protected function setUp(): void
    {
        parent::setUp();

        Entity::create(['name' => 'Post', 'slug' => 'post']);

        $this->records = new RecordDomainService;
    }

    public function test_scheduled_records_are_published_when_their_date_passes(): void
    {
        Event::fake([RecordPublished::class]);

        $record = $this->records->create('post', ['title' => 'Launch', 'status' => 'scheduled', 'publish_at' => now()->addHour()]);

        $this->assertSame('scheduled', $record->status);
        $this->artisan('flex:publish-scheduled')->assertSuccessful();
        $this->assertSame('scheduled', $record->fresh()->status);
        Event::assertNotDispatched(RecordPublished::class);

        $this->travel(61)->minutes();
        $this->artisan('flex:publish-scheduled')->expectsOutputToContain('Published 1 scheduled record(s).')->assertSuccessful();

        $this->assertSame('published', $record->fresh()->status);
        Event::assertDispatched(RecordPublished::class, fn (RecordPublished $event) => $event->recordId === $record->id);
        $this->assertSame('updated', $record->revisions()->first()->event);
    }

    public function test_a_scheduled_record_needs_a_date_and_past_dates_publish_right_away(): void
    {
        $record = $this->records->create('post', ['title' => 'Now', 'status' => 'scheduled', 'publish_at' => now()->subMinute()]);
        $this->assertSame('published', $record->status);

        $this->expectException(RuntimeException::class);
        $this->records->create('post', ['title' => 'Missing date', 'status' => 'scheduled']);
    }

    public function test_rescheduling_keeps_the_status(): void
    {
        $record = $this->records->create('post', ['title' => 'Launch', 'status' => 'scheduled', 'publish_at' => now()->addDay()]);
        $updated = $this->records->update('post', $record->id, ['publish_at' => now()->addDays(2)->toIso8601String()]);

        $this->assertSame('scheduled', $updated->status);
        $this->assertTrue($updated->publish_at->isAfter(now()->addDay()));
    }

    public function test_the_command_runs_every_minute(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

        $this->assertStringContainsString('flex:publish-scheduled', $commands);
    }

    public function test_scheduling_through_the_api_requires_the_publish_permission(): void
    {
        $this->freezeSecond();
        config(['flex-fields.permissions.enabled' => true]);
        $granted = ['create_flex_post', 'update_flex_post', 'view_flex_post'];
        Gate::before(function ($user, string $ability) use (&$granted) {
            return in_array($ability, $granted, true) ? true : null;
        });

        $user = $this->makeUser();
        $payload = ['title' => 'Launch', 'status' => 'scheduled', 'publish_at' => now()->addDay()->toIso8601String()];

        $this->actingAs($user)->postJson('/api/flex/post', $payload)->assertForbidden();

        $granted[] = 'publish_flex_post';

        $this->actingAs($user)->postJson('/api/flex/post', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.publish_at', now()->addDay()->toIso8601String());
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('flex-fields.api.enabled', true);
        $app['config']->set('flex-fields.api.middleware', ['api', 'auth']);
    }
}
