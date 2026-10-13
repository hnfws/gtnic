<?php

use App\Jobs\ProcessScrapedEvent;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('rejects requests with an invalid scraper token', function () {
    config(['services.scraper.token' => 'expected-token']);

    $this->postJson('/api/scraped-events', [], [
        'Authorization' => 'Bearer incorrect-token',
    ])->assertUnauthorized();
});

it('accepts a valid scraper token before validating the payload', function () {
    config(['services.scraper.token' => 'expected-token']);

    $this->postJson('/api/scraped-events', [], [
        'Authorization' => 'Bearer expected-token',
    ])->assertStatus(422);
});

it('processes scraped event persistence synchronously and acknowledges the request', function () {
    Bus::fake();
    config(['services.scraper.token' => 'expected-token']);

    $this->postJson('/api/scraped-events', [
        'title' => 'Event Tenant Call',
        'description' => 'Limited slot at ICE BSD',
        'location_name' => 'Jakarta',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'instagram_post_url' => 'https://www.instagram.com/p/shortcode/',
        'registration_link' => 'https://instagram.com/pestapora',
        'account_name' => 'pestapora',
        'posted_at' => '2026-10-12T12:00:00+00:00',
        'deadline_date' => now()->addDays(2)->toISOString(),
    ], [
        'Authorization' => 'Bearer expected-token',
    ])->assertOk()->assertJson(['status' => 'processed']);

    Bus::assertDispatchedSync(ProcessScrapedEvent::class);
});

it('scores priority events and stores recognized deadlines', function () {
    Schema::create('events', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('category');
        $table->string('location_name');
        $table->dateTime('start_date');
        $table->dateTime('end_date');
        $table->decimal('latitude', 10, 8);
        $table->decimal('longitude', 11, 8);
        $table->string('source_url')->nullable();
        $table->string('source_account')->nullable();
        $table->unsignedSmallInteger('priority_score')->default(0);
        $table->boolean('is_priority')->default(false);
        $table->timestamps();
    });
    Schema::create('event_tenants', function ($table) {
        $table->id();
        $table->foreignId('event_id');
        $table->string('tenant_type');
        $table->unsignedBigInteger('price')->nullable();
        $table->text('requirements')->nullable();
        $table->string('registration_link')->nullable();
        $table->string('instagram_post_url')->nullable();
        $table->dateTime('deadline_date')->nullable();
        $table->timestamps();
    });

    try {
        $job = new ProcessScrapedEvent([
            'title' => 'Event Tenant Call',
            'description' => 'Limited slot at ICE BSD',
            'location_name' => 'Jakarta',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'instagram_post_url' => 'https://www.instagram.com/p/priority-test/',
            'account_name' => 'pestapora',
            'deadline_date' => now()->addDays(2)->toISOString(),
        ]);
        $job->handle();
        $job->handle();

        (new ProcessScrapedEvent([
            'title' => 'General Vendor Applications',
            'description' => 'General vendor applications are open.',
            'location_name' => 'Jakarta',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'instagram_post_url' => 'https://www.instagram.com/p/non-priority-test/',
            'account_name' => 'info.tenant',
        ]))->handle();

        expect(Event::query()->count())->toBe(2)
            ->and(Event::query()->first()->priority_score)->toBe(100)
            ->and(Event::query()->first()->is_priority)->toBeTrue()
            ->and(Event::query()->where('source_account', 'info.tenant')->first()->is_priority)->toBeFalse()
            ->and(Carbon::parse(Tenant::query()->first()->deadline_date)->isFuture())->toBeTrue();
    } finally {
        Schema::dropIfExists('event_tenants');
        Schema::dropIfExists('events');
    }
});

it('persists seen shortcode cursors for delta scraping', function () {
    Schema::create('scraper_posts', function ($table) {
        $table->id();
        $table->string('account_name', 100);
        $table->string('shortcode', 100);
        $table->dateTime('posted_at')->nullable();
        $table->timestamps();
        $table->unique(['account_name', 'shortcode']);
        $table->index(['account_name', 'posted_at']);
    });
    config(['services.scraper.token' => 'expected-token']);

    try {
        $this->postJson('/api/scraped-events/seen-posts', [
            'account' => 'pestapora',
            'posts' => [
                ['shortcode' => 'new-post', 'posted_at' => now()->toISOString()],
            ],
        ], [
            'Authorization' => 'Bearer expected-token',
        ])->assertOk()->assertJson(['count' => 1]);

        $this->getJson('/api/scraped-events/known-posts?account=pestapora', [
            'Authorization' => 'Bearer expected-token',
        ])->assertOk()->assertJsonPath('shortcodes.0', 'new-post');
    } finally {
        Schema::dropIfExists('scraper_posts');
    }
});

it('maps scraped tenant records to the event_tenants table', function () {
    expect((new Tenant)->getTable())->toBe('event_tenants');
});

it('uses emulated PostgreSQL prepares for transaction-pooled database connections', function () {
    expect(config('database.connections.pgsql.options.'.PDO::ATTR_EMULATE_PREPARES))->toBeTrue();
});
