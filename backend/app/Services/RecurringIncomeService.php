<?php

namespace App\Services;

use App\Models\RecurringExecution;
use App\Models\RecurringIncome;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RecurringIncomeService
{
    public function computeScheduledDateForMonth(int $year, int $month, int $dayOfMonth, string $timezone = 'Asia/Jakarta'): Carbon
    {
        $base = Carbon::create($year, $month, 1, 0, 0, 0, $timezone);
        $clampedDay = min($dayOfMonth, $base->daysInMonth);

        return $base->day($clampedDay);
    }

    public function computeNextRunDate(int $dayOfMonth, Carbon $referenceDate, bool $allowSameDay = true): Carbon
    {
        $tz = $referenceDate->getTimezone()->getName();
        $candidate = $this->computeScheduledDateForMonth($referenceDate->year, $referenceDate->month, $dayOfMonth, $tz);

        if ($allowSameDay ? $candidate->lt($referenceDate->copy()->startOfDay()) : $candidate->lte($referenceDate->copy()->startOfDay())) {
            $nextMonth = $referenceDate->copy()->startOfMonth()->addMonth();
            $candidate = $this->computeScheduledDateForMonth($nextMonth->year, $nextMonth->month, $dayOfMonth, $tz);
        }

        return $candidate;
    }

    /**
     * Process all due recurring incomes idempotently.
     * Returns count of newly generated transactions.
     */
    public function processDueIncomes(?Carbon $asOf = null, ?int $userId = null): int
    {
        $now = ($asOf ?? Carbon::now('Asia/Jakarta'))->copy()->setTimezone('Asia/Jakarta');
        $today = $now->toDateString();

        $query = RecurringIncome::query()
            ->where('is_active', true)
            ->where('next_run_date', '<=', $today);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $dueItems = $query->get();
        $created = 0;

        foreach ($dueItems as $recurring) {
            if ($this->executeSingle($recurring, $now)) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Idempotent execution for a single recurring income for its due period.
     */
    public function executeSingle(RecurringIncome $recurring, ?Carbon $asOf = null): bool
    {
        $now = ($asOf ?? Carbon::now('Asia/Jakarta'))->copy()->setTimezone('Asia/Jakarta');
        $scheduledDate = Carbon::parse($recurring->next_run_date, 'Asia/Jakarta');
        $period = $scheduledDate->format('Y-m');

        if ($scheduledDate->gt($now->copy()->endOfDay())) {
            return false;
        }

        try {
            return DB::transaction(function () use ($recurring, $now, $scheduledDate, $period) {
                $locked = RecurringIncome::query()
                    ->whereKey($recurring->id)
                    ->lockForUpdate()
                    ->first();

                if (! $locked || ! $locked->is_active) {
                    return false;
                }

                $alreadyExecuted = RecurringExecution::query()
                    ->where('recurring_income_id', $locked->id)
                    ->where('execution_period', $period)
                    ->exists();

                if ($alreadyExecuted) {
                    $nextMonth = $scheduledDate->copy()->startOfMonth()->addMonth();
                    $locked->next_run_date = $this->computeScheduledDateForMonth(
                        $nextMonth->year,
                        $nextMonth->month,
                        $locked->day_of_month,
                        'Asia/Jakarta'
                    )->toDateString();
                    $locked->save();

                    return false;
                }

                $transaction = Transaction::create([
                    'user_id' => $locked->user_id,
                    'account_id' => $locked->account_id,
                    'category_id' => $locked->category_id,
                    'subcategory_id' => $locked->subcategory_id,
                    'type' => 'income',
                    'amount' => $locked->amount,
                    'description' => $locked->description ?: $locked->name,
                    'transaction_date' => $scheduledDate->toDateString(),
                    'transaction_time' => $now->format('H:i:s'),
                    'source' => 'recurring',
                ]);

                RecurringExecution::create([
                    'user_id' => $locked->user_id,
                    'recurring_income_id' => $locked->id,
                    'transaction_id' => $transaction->id,
                    'execution_period' => $period,
                    'scheduled_date' => $scheduledDate->toDateString(),
                    'executed_at' => $now,
                    'status' => 'completed',
                    'notes' => "Otomatis dicatat untuk periode {$period}",
                ]);

                $nextMonth = $scheduledDate->copy()->startOfMonth()->addMonth();
                $locked->last_executed_at = $now;
                $locked->next_run_date = $this->computeScheduledDateForMonth(
                    $nextMonth->year,
                    $nextMonth->month,
                    $locked->day_of_month,
                    'Asia/Jakarta'
                )->toDateString();
                $locked->save();

                return true;
            });
        } catch (QueryException $e) {
            // Unique constraint violation on (recurring_income_id, execution_period) guarantees idempotency
            if (str_contains(strtolower($e->getMessage()), 'unique') || $e->getCode() === '23000') {
                return false;
            }

            throw $e;
        }
    }
}
