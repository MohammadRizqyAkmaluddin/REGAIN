<?php

namespace App\Services;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoalService
{
    public function __construct(
        private readonly SmokingTrackerService $smokingTracker,
        private readonly FinancialCalculationService $finance,
    ) {}

    /**
     * Virtually allocate estimated smoking savings (or actual allocation tracking) to a goal.
     * NEVER modifies actual account balances or creates transactions.
     */
    public function allocateToGoal(User $user, FinancialGoal $goal, array $data): FinancialGoal
    {
        return DB::transaction(function () use ($user, $goal, $data) {
            $smokingSummary = $this->smokingTracker->getSummary($user);
            $totalEstimatedSavingsSen = $this->finance->toSen($smokingSummary['progress']['estimated_money_saved']);

            $otherGoalsAllocatedSen = 0;
            foreach ($user->financialGoals()->where('id', '!=', $goal->id)->get() as $other) {
                $otherGoalsAllocatedSen += $this->finance->toSen($other->allocated_smoking_savings);
            }

            $newSmokingAllocSen = array_key_exists('allocated_smoking_savings', $data)
                ? $this->finance->toSen($data['allocated_smoking_savings'])
                : $this->finance->toSen($goal->allocated_smoking_savings);

            if ($newSmokingAllocSen < 0) {
                throw ValidationException::withMessages([
                    'allocated_smoking_savings' => ['Alokasi tabungan rokok tidak boleh negatif.'],
                ]);
            }

            if (($otherGoalsAllocatedSen + $newSmokingAllocSen) > $totalEstimatedSavingsSen) {
                $maxAllowed = max(0, $totalEstimatedSavingsSen - $otherGoalsAllocatedSen);

                throw ValidationException::withMessages([
                    'allocated_smoking_savings' => [
                        'Alokasi melebihi estimasi tabungan bebas rokok yang tersedia (maksimal Rp'.number_format($maxAllowed / 100, 0, ',', '.').').',
                    ],
                ]);
            }

            $newActualAllocSen = array_key_exists('current_actual_allocation', $data)
                ? $this->finance->toSen($data['current_actual_allocation'])
                : $this->finance->toSen($goal->current_actual_allocation);

            $goal->allocated_smoking_savings = $this->finance->fromSen($newSmokingAllocSen);
            $goal->current_actual_allocation = $this->finance->fromSen(max(0, $newActualAllocSen));

            $targetSen = $this->finance->toSen($goal->target_amount);
            $combinedSen = $newSmokingAllocSen + max(0, $newActualAllocSen);
            if ($targetSen > 0 && $combinedSen >= $targetSen && $goal->status === 'active') {
                $goal->status = 'completed';
            }

            $goal->save();

            return $goal->fresh();
        });
    }

    public function formatGoal(FinancialGoal $goal): array
    {
        $targetSen = $this->finance->toSen($goal->target_amount);
        $actualSen = $this->finance->toSen($goal->current_actual_allocation);
        $smokingSen = $this->finance->toSen($goal->allocated_smoking_savings);
        $combinedSen = $actualSen + $smokingSen;
        $progress = $targetSen > 0 ? min(100.0, round(($combinedSen / $targetSen) * 100, 1)) : 0.0;

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'category' => $goal->category,
            'description' => $goal->description,
            'target_amount' => (string) $goal->target_amount,
            'current_actual_allocation' => (string) $goal->current_actual_allocation,
            'allocated_smoking_savings' => (string) $goal->allocated_smoking_savings,
            'combined_progress_amount' => $this->finance->fromSen($combinedSen),
            'progress_percentage' => $progress,
            'remaining_amount' => $this->finance->fromSen(max(0, $targetSen - $combinedSen)),
            'target_date' => $goal->target_date?->format('Y-m-d'),
            'icon' => $goal->icon,
            'color' => $goal->color,
            'status' => $goal->status,
            'created_at' => $goal->created_at?->toIso8601String(),
        ];
    }
}
