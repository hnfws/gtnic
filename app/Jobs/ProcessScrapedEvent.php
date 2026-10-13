<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessScrapedEvent implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 86400;

    public function __construct(public array $payload) {}

    public function uniqueId(): string
    {
        return $this->payload['instagram_post_url'];
    }

    public function handle(): void
    {
        DB::transaction(function () {
            if (Tenant::where('instagram_post_url', $this->payload['instagram_post_url'])->exists()) {
                return;
            }

            $accountName = strtolower($this->payload['account_name']);
            $caption = mb_strtolower($this->payload['description'] ?? '');
            $priorityKeywords = config('services.scraper.priority_keywords', []);
            $priorityAccounts = config('services.scraper.priority_accounts', []);
            $keywordPriority = collect($priorityKeywords)->contains(
                fn (string $keyword) => str_contains($caption, mb_strtolower($keyword))
            );
            $accountPriority = in_array($accountName, $priorityAccounts, true);
            $priorityScore = ($keywordPriority ? 50 : 0) + ($accountPriority ? 50 : 0);
            $isPriority = $priorityScore > 0;

            $event = Event::create([
                'title' => $this->payload['title'],
                'category' => 'bazaar',
                'location_name' => $this->payload['location_name'] ?? 'Lokasi Perlu Validasi',
                'start_date' => now(),
                'end_date' => now()->addDays(3),
                'latitude' => $this->payload['latitude'],
                'longitude' => $this->payload['longitude'],
                'source_url' => $this->payload['registration_link'] ?? null,
                'source_account' => $accountName,
                'priority_score' => $priorityScore,
                'is_priority' => DB::getDriverName() === 'pgsql'
                    ? ($isPriority ? 'true' : 'false')
                    : $isPriority,
            ]);

            if (DB::getDriverName() === 'pgsql') {
                DB::statement(
                    'UPDATE events SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$this->payload['longitude'], $this->payload['latitude'], $event->id]
                );
            }

            Tenant::create([
                'event_id' => $event->id,
                'tenant_type' => 'curated_call',
                'price' => $this->payload['price'] ?? null,
                'requirements' => $this->payload['description'] ?? null,
                'registration_link' => $this->payload['registration_link'] ?? null,
                'instagram_post_url' => $this->payload['instagram_post_url'],
                'deadline_date' => isset($this->payload['deadline_date'])
                    ? Carbon::parse($this->payload['deadline_date'])
                    : null,
            ]);
        });
    }
}
