<?php

namespace App\Services\Billing;

use App\Enums\UsageEventType;
use App\Jobs\CheckMauUsageAlertsJob;
use App\Models\Organization;
use App\Models\UsageEvent;
use App\Models\UsageMonthlySummary;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsageTracker
{
    public function record(
        Organization $organization,
        UsageEventType $type,
        string $subjectKey,
        ?User $user = null,
        array $metadata = [],
        ?CarbonImmutable $at = null,
    ): void {
        if (! config('billing.enabled', true)) {
            return;
        }

        $at ??= CarbonImmutable::now(config('billing.mau.timezone', 'UTC'));
        $occurredOn = $at->toDateString();
        $created = false;

        try {
            $event = UsageEvent::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'subject_key' => $subjectKey,
                    'occurred_on' => $occurredOn,
                    'event_type' => $type,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'user_id' => $user?->id,
                    'metadata' => $metadata !== [] ? $metadata : null,
                    'created_at' => $at,
                ],
            );
            $created = $event->wasRecentlyCreated;
        } catch (UniqueConstraintViolationException) {
            // SQLite/date-cast lookups can miss an existing day row; unique index is source of truth.
            $event = UsageEvent::query()
                ->where('organization_id', $organization->id)
                ->where('subject_key', $subjectKey)
                ->whereDate('occurred_on', $occurredOn)
                ->where('event_type', $type)
                ->firstOrFail();
        }

        if ($created) {
            CheckMauUsageAlertsJob::dispatch($organization->id);
        }
    }

    public function recordConsoleLogin(User $user): void
    {
        $user->loadMissing('organizations');

        foreach ($user->organizations as $organization) {
            $this->record(
                $organization,
                UsageEventType::ConsoleLogin,
                $user->uuid,
                $user,
                ['source' => 'console'],
            );
        }
    }

    public function recordUserAuthenticated(Organization $organization, User $user): void
    {
        $this->record(
            $organization,
            UsageEventType::UserAuthenticated,
            $user->uuid,
            $user,
        );
    }

    /**
     * Distinct users with a qualifying auth event in the month (MAU).
     */
    public function monthlyActiveUsers(Organization $organization, ?CarbonImmutable $month = null): int
    {
        $month ??= CarbonImmutable::now(config('billing.mau.timezone', 'UTC'));
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();
        $events = config('billing.mau.qualifying_events', []);

        return (int) UsageEvent::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_type', $events)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->distinct()
            ->count('subject_key');
    }

    /**
     * @return list<array{date: string, mau: int, authentications: int}>
     */
    public function dailyBreakdown(Organization $organization, int $days = 30): array
    {
        $tz = config('billing.mau.timezone', 'UTC');
        $end = CarbonImmutable::now($tz)->startOfDay();
        $start = $end->subDays($days - 1);
        $events = config('billing.mau.qualifying_events', []);

        $rows = UsageEvent::query()
            ->select([
                'occurred_on',
                DB::raw('count(distinct subject_key) as mau'),
                DB::raw('count(*) as authentications'),
            ])
            ->where('organization_id', $organization->id)
            ->whereIn('event_type', $events)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->groupBy('occurred_on')
            ->orderBy('occurred_on')
            ->get()
            ->keyBy(fn ($row) => CarbonImmutable::parse($row->occurred_on)->toDateString());

        $series = [];
        for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDay()) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);
            $series[] = [
                'date' => $key,
                'mau' => (int) ($row->mau ?? 0),
                'authentications' => (int) ($row->authentications ?? 0),
            ];
        }

        return $series;
    }

    public function recomputeMonthlySummary(Organization $organization, ?CarbonImmutable $month = null): UsageMonthlySummary
    {
        $month ??= CarbonImmutable::now(config('billing.mau.timezone', 'UTC'));
        $yearMonth = $month->format('Y-m');
        $start = $month->startOfMonth()->toDateString();
        $end = $month->endOfMonth()->toDateString();

        $mau = $this->monthlyActiveUsers($organization, $month);

        $authCount = UsageEvent::query()
            ->where('organization_id', $organization->id)
            ->whereIn('event_type', config('billing.mau.qualifying_events', []))
            ->whereBetween('occurred_on', [$start, $end])
            ->count();

        $created = UsageEvent::query()
            ->where('organization_id', $organization->id)
            ->where('event_type', UsageEventType::UserCreated->value)
            ->whereBetween('occurred_on', [$start, $end])
            ->count();

        $tokens = UsageEvent::query()
            ->where('organization_id', $organization->id)
            ->where('event_type', UsageEventType::TokenIssued->value)
            ->whereBetween('occurred_on', [$start, $end])
            ->count();

        return UsageMonthlySummary::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'year_month' => $yearMonth,
            ],
            [
                'mau_count' => $mau,
                'authentication_count' => $authCount,
                'user_created_count' => $created,
                'token_issued_count' => $tokens,
                'computed_at' => now(),
            ],
        );
    }
}
