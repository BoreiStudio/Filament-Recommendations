<?php

namespace BoreiStudio\FilamentRecommendations\Console;

use BoreiStudio\FilamentRecommendations\Models\RecommendationEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneEventsCommand extends Command
{
    protected $signature = 'recommendations:prune-events
        {--tenant= : Only prune events for this tenant id}
        {--days= : Number of days of events to keep (defaults to events.retention_days)}
        {--chunk=500 : Records deleted per query chunk}';

    protected $description = 'Delete recommendation events older than the retention policy';

    public function handle(): int
    {
        $days = $this->option('days');

        if ($days === null) {
            $days = config('recommendations.events.retention_days', 90);
        }

        $cutoff = now()->subDays((int) $days);
        $tenant = $this->option('tenant');
        $chunk = (int) $this->option('chunk');

        $deleted = 0;

        DB::transaction(function () use ($cutoff, $tenant, $chunk, &$deleted) {
            do {
                $ids = RecommendationEvent::query()
                    ->withoutGlobalScope('recommendation_tenant')
                    ->where('created_at', '<', $cutoff)
                    ->when($tenant !== null, fn ($query) => $query->where('tenant_id', $tenant))
                    ->limit($chunk)
                    ->pluck('id');

                $count = $ids->count();

                if ($count === 0) {
                    break;
                }

                RecommendationEvent::query()
                    ->withoutGlobalScope('recommendation_tenant')
                    ->whereIn('id', $ids)
                    ->delete();

                $deleted += $count;
            } while (true);
        });

        $this->components->info("Deleted {$deleted} recommendation event(s) older than {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
