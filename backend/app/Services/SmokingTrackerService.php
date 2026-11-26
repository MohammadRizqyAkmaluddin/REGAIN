<?php

namespace App\Services;

use App\Models\SmokingEvent;
use App\Models\SmokingProfile;
use App\Models\SmokingSaving;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SmokingTrackerService
{
    public function __construct(
        private readonly FinancialCalculationService $finance
    ) {}

    public function getOrCreateProfile(User $user): SmokingProfile
    {
        return $user->smokingProfile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'quit_date' => Carbon::now('Asia/Jakarta')->toDateString(),
                'packs_per_week' => '10.00',
                'price_per_pack' => '30000.00',
                'cigarettes_per_pack' => 16,
                'is_active' => true,
            ]
        );
    }

    /**
     * Compute full smoking cessation summary.
     * IMPORTANT: Estimated savings are strictly separate from actual finances.
     */
    public function getSummary(User $user, ?Carbon $asOf = null): array
    {
        $profile = $this->getOrCreateProfile($user);
        $tz = $user->appSetting?->timezone ?? 'Asia/Jakarta';
        $now = ($asOf ?? Carbon::now($tz))->copy()->setTimezone($tz);
        $today = $now->copy()->startOfDay();
        $quitDate = Carbon::parse($profile->quit_date, $tz)->startOfDay();

        $packsPerWeek = (float) $profile->packs_per_week;
        $pricePerPack = (float) $profile->price_per_pack;
        $cigsPerPack = (int) $profile->cigarettes_per_pack;

        $weeklySpend = $packsPerWeek * $pricePerPack;
        $yearlySpend = $weeklySpend * 52;
        $monthlySpend = $yearlySpend / 12;
        $dailyPacks = $packsPerWeek / 7;
        $dailySpend = $weeklySpend / 7;

        $totalDaysSinceQuit = $quitDate->gt($today) ? 0 : (int) $quitDate->diffInDays($today);

        $relapses = $profile->events()
            ->where('event_type', 'relapse')
            ->where('event_date', '>=', $quitDate->toDateString())
            ->orderByDesc('event_date')
            ->orderByDesc('event_time')
            ->get();

        $relapseDaysCount = $relapses->pluck('event_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->count();

        $totalRelapsePacks = (float) $relapses->sum('packs_smoked');
        $totalRelapseCost = (float) $relapses->sum('estimated_cost');

        // Current streak resets from latest relapse date, while historical progress is preserved
        $lastRelapse = $relapses->first();
        if ($lastRelapse) {
            $lastRelapseDate = Carbon::parse($lastRelapse->event_date, $tz)->startOfDay();
            $currentStreakDays = $lastRelapseDate->gt($today) ? 0 : (int) $lastRelapseDate->diffInDays($today);
        } else {
            $currentStreakDays = $totalDaysSinceQuit;
        }

        $totalSmokeFreeDays = max(0, $totalDaysSinceQuit - $relapseDaysCount);
        $estimatedPacksAvoided = max(0.0, ($totalDaysSinceQuit * $dailyPacks) - $totalRelapsePacks);
        $estimatedCigarettesAvoided = (int) round($estimatedPacksAvoided * $cigsPerPack);
        $estimatedMoneySaved = max(0.0, ($totalDaysSinceQuit * $dailySpend) - $totalRelapseCost);

        $allocatedToGoals = (float) $user->financialGoals()->sum('allocated_smoking_savings');
        $unallocatedSavings = max(0.0, $estimatedMoneySaved - $allocatedToGoals);

        return [
            'profile' => [
                'id' => $profile->id,
                'quit_date' => $quitDate->toDateString(),
                'packs_per_week' => number_format($packsPerWeek, 2, '.', ''),
                'price_per_pack' => number_format($pricePerPack, 2, '.', ''),
                'cigarettes_per_pack' => $cigsPerPack,
                'is_active' => $profile->is_active,
            ],
            'baseline' => [
                'daily_expenditure' => number_format($dailySpend, 2, '.', ''),
                'weekly_expenditure' => number_format($weeklySpend, 2, '.', ''),
                'monthly_expenditure' => number_format($monthlySpend, 2, '.', ''),
                'yearly_expenditure' => number_format($yearlySpend, 2, '.', ''),
            ],
            'progress' => [
                'current_streak_days' => $currentStreakDays,
                'total_days_since_quit' => $totalDaysSinceQuit,
                'total_smoke_free_days' => $totalSmokeFreeDays,
                'relapse_count' => $relapses->count(),
                'estimated_money_saved' => number_format($estimatedMoneySaved, 2, '.', ''),
                'estimated_packs_avoided' => number_format($estimatedPacksAvoided, 2, '.', ''),
                'estimated_cigarettes_avoided' => $estimatedCigarettesAvoided,
                'allocated_to_goals' => number_format($allocatedToGoals, 2, '.', ''),
                'unallocated_savings' => number_format($unallocatedSavings, 2, '.', ''),
            ],
            'recent_events' => $profile->events()
                ->orderByDesc('event_date')
                ->orderByDesc('event_time')
                ->limit(15)
                ->get()
                ->map(fn (SmokingEvent $e) => [
                    'id' => $e->id,
                    'event_type' => $e->event_type,
                    'event_date' => $e->event_date->format('Y-m-d'),
                    'event_time' => $e->event_time,
                    'packs_smoked' => (string) $e->packs_smoked,
                    'estimated_cost' => (string) $e->estimated_cost,
                    'trigger' => $e->trigger,
                    'notes' => $e->notes,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Record a relapse gently without deleting historical progress or touching actual finances.
     */
    public function recordRelapse(User $user, array $data): SmokingEvent
    {
        $profile = $this->getOrCreateProfile($user);
        $tz = $user->appSetting?->timezone ?? 'Asia/Jakarta';
        $now = Carbon::now($tz);

        $packsSmoked = (float) ($data['packs_smoked'] ?? 1);
        $estimatedCost = $packsSmoked * (float) $profile->price_per_pack;
        $eventDate = $data['event_date'] ?? $now->toDateString();

        return DB::transaction(function () use ($user, $profile, $data, $now, $packsSmoked, $estimatedCost, $eventDate) {
            $event = SmokingEvent::create([
                'user_id' => $user->id,
                'smoking_profile_id' => $profile->id,
                'event_type' => 'relapse',
                'event_date' => $eventDate,
                'event_time' => $data['event_time'] ?? $now->format('H:i:s'),
                'packs_smoked' => number_format($packsSmoked, 2, '.', ''),
                'estimated_cost' => number_format($estimatedCost, 2, '.', ''),
                'trigger' => $data['trigger'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Update snapshot for that day if it exists
            SmokingSaving::updateOrCreate(
                ['user_id' => $user->id, 'saving_date' => $eventDate],
                [
                    'smoking_profile_id' => $profile->id,
                    'estimated_amount' => '0.00',
                    'packs_avoided' => '0.00',
                    'cigarettes_avoided' => 0,
                    'is_smoke_free_day' => false,
                ]
            );

            return $event;
        });
    }

    /**
     * Snapshot daily smoking savings for a user on a given date.
     */
    public function snapshotDailySavings(User $user, ?Carbon $date = null): ?SmokingSaving
    {
        $profile = $user->smokingProfile;
        if (! $profile || ! $profile->is_active) {
            return null;
        }

        $tz = $user->appSetting?->timezone ?? 'Asia/Jakarta';
        $targetDate = ($date ?? Carbon::now($tz))->copy()->setTimezone($tz)->startOfDay();
        $quitDate = Carbon::parse($profile->quit_date, $tz)->startOfDay();

        if ($targetDate->lt($quitDate)) {
            return null;
        }

        $dateStr = $targetDate->toDateString();
        $relapseOnDate = $profile->events()
            ->where('event_type', 'relapse')
            ->whereDate('event_date', $dateStr)
            ->get();

        $dailyPacks = (float) $profile->packs_per_week / 7;
        $dailySpend = $dailyPacks * (float) $profile->price_per_pack;

        $relapsePacks = (float) $relapseOnDate->sum('packs_smoked');
        $relapseCost = (float) $relapseOnDate->sum('estimated_cost');

        $netPacks = max(0.0, $dailyPacks - $relapsePacks);
        $netAmount = max(0.0, $dailySpend - $relapseCost);
        $netCigs = (int) round($netPacks * $profile->cigarettes_per_pack);

        return SmokingSaving::updateOrCreate(
            ['user_id' => $user->id, 'saving_date' => $dateStr],
            [
                'smoking_profile_id' => $profile->id,
                'estimated_amount' => number_format($netAmount, 2, '.', ''),
                'packs_avoided' => number_format($netPacks, 2, '.', ''),
                'cigarettes_avoided' => $netCigs,
                'is_smoke_free_day' => $relapseOnDate->isEmpty(),
            ]
        );
    }

    public function getReports(User $user, ?Carbon $asOf = null): array
    {
        $profile = $this->getOrCreateProfile($user);
        $tz = $user->appSetting?->timezone ?? 'Asia/Jakarta';
        $now = ($asOf ?? Carbon::now($tz))->copy()->setTimezone($tz)->startOfDay();
        $quitDate = Carbon::parse($profile->quit_date, $tz)->startOfDay();

        $dailyPacks = (float) $profile->packs_per_week / 7;
        $dailySpend = $dailyPacks * (float) $profile->price_per_pack;

        $relapsesByDate = $profile->events()
            ->where('event_type', 'relapse')
            ->get()
            ->groupBy(fn (SmokingEvent $e) => $e->event_date->format('Y-m-d'));

        // Weekly report (last 8 weeks)
        $weekly = [];
        for ($w = 7; $w >= 0; $w--) {
            $weekStart = $now->copy()->startOfWeek()->subWeeks($w);
            $weekEnd = $weekStart->copy()->endOfWeek();
            if ($weekEnd->gt($now)) {
                $weekEnd = $now->copy();
            }

            $amount = 0.0;
            $packs = 0.0;
            $smokeFreeDays = 0;

            for ($d = $weekStart->copy(); $d->lte($weekEnd); $d->addDay()) {
                if ($d->lt($quitDate)) {
                    continue;
                }
                $key = $d->toDateString();
                $dayRelapses = $relapsesByDate->get($key, collect());
                $relapseCost = (float) $dayRelapses->sum('estimated_cost');
                $relapsePacks = (float) $dayRelapses->sum('packs_smoked');

                $amount += max(0.0, $dailySpend - $relapseCost);
                $packs += max(0.0, $dailyPacks - $relapsePacks);
                if ($dayRelapses->isEmpty()) {
                    $smokeFreeDays++;
                }
            }

            $weekly[] = [
                'label' => $weekStart->format('d M'),
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'estimated_saved' => number_format($amount, 2, '.', ''),
                'packs_avoided' => number_format($packs, 2, '.', ''),
                'smoke_free_days' => $smokeFreeDays,
            ];
        }

        // Monthly report (last 6 months)
        $monthly = [];
        for ($m = 5; $m >= 0; $m--) {
            $monthStart = $now->copy()->startOfMonth()->subMonths($m);
            $monthEnd = $monthStart->copy()->endOfMonth();
            if ($monthEnd->gt($now)) {
                $monthEnd = $now->copy();
            }

            $amount = 0.0;
            $packs = 0.0;
            $smokeFreeDays = 0;

            for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
                if ($d->lt($quitDate)) {
                    continue;
                }
                $key = $d->toDateString();
                $dayRelapses = $relapsesByDate->get($key, collect());
                $relapseCost = (float) $dayRelapses->sum('estimated_cost');
                $relapsePacks = (float) $dayRelapses->sum('packs_smoked');

                $amount += max(0.0, $dailySpend - $relapseCost);
                $packs += max(0.0, $dailyPacks - $relapsePacks);
                if ($dayRelapses->isEmpty()) {
                    $smokeFreeDays++;
                }
            }

            $monthly[] = [
                'period' => $monthStart->format('Y-m'),
                'label' => $monthStart->translatedFormat('M Y'),
                'estimated_saved' => number_format($amount, 2, '.', ''),
                'packs_avoided' => number_format($packs, 2, '.', ''),
                'smoke_free_days' => $smokeFreeDays,
            ];
        }

        return [
            'weekly' => $weekly,
            'monthly' => $monthly,
        ];
    }
}
