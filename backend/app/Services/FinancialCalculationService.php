<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialCalculationService
{
    /**
     * Convert decimal string/numeric to integer sen (cents) for exact arithmetic.
     */
    public function toSen(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        $normalized = number_format((float) $amount, 2, '.', '');
        [$whole, $frac] = explode('.', $normalized);
        $negative = str_starts_with($whole, '-');
        $absWhole = abs((int) $whole);
        $sen = ($absWhole * 100) + (int) $frac;

        return $negative ? -$sen : $sen;
    }

    public function fromSen(int $sen): string
    {
        $negative = $sen < 0;
        $abs = abs($sen);
        $whole = intdiv($abs, 100);
        $frac = $abs % 100;

        return sprintf('%s%d.%02d', $negative ? '-' : '', $whole, $frac);
    }

    /**
     * Actual Net Worth = Sum(Opening Balances) + Sum(Actual Income) - Sum(Actual Expenses).
     * Smoking savings are NEVER included here.
     */
    public function getNetWorthSummary(User $user): array
    {
        $openingSen = 0;
        $accounts = $user->accounts()->get();
        foreach ($accounts as $account) {
            $openingSen += $this->toSen($account->opening_balance);
        }

        $totals = $user->transactions()
            ->select('type', DB::raw('SUM(amount) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $totalIncomeSen = $this->toSen($totals['income'] ?? 0);
        $totalExpenseSen = $this->toSen($totals['expense'] ?? 0);
        $netWorthSen = $openingSen + $totalIncomeSen - $totalExpenseSen;

        $accountBalances = $accounts->map(function ($account) use ($user) {
            $accTotals = $user->transactions()
                ->where('account_id', $account->id)
                ->select('type', DB::raw('SUM(amount) as total'))
                ->groupBy('type')
                ->pluck('total', 'type');

            $balSen = $this->toSen($account->opening_balance)
                + $this->toSen($accTotals['income'] ?? 0)
                - $this->toSen($accTotals['expense'] ?? 0);

            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'opening_balance' => (string) $account->opening_balance,
                'current_balance' => $this->fromSen($balSen),
                'currency' => $account->currency,
                'is_default' => $account->is_default,
            ];
        })->values()->all();

        return [
            'opening_balance' => $this->fromSen($openingSen),
            'total_actual_income' => $this->fromSen($totalIncomeSen),
            'total_actual_expenses' => $this->fromSen($totalExpenseSen),
            'actual_net_worth' => $this->fromSen($netWorthSen),
            'accounts' => $accountBalances,
        ];
    }

    public function getMonthlySummary(User $user, ?string $month = null, string $timezone = 'Asia/Jakarta'): array
    {
        $ref = $month
            ? Carbon::createFromFormat('Y-m', $month, $timezone)->startOfMonth()
            : Carbon::now($timezone)->startOfMonth();

        $startDate = $ref->copy()->startOfMonth()->toDateString();
        $endDate = $ref->copy()->endOfMonth()->toDateString();

        $totals = $user->transactions()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('type', DB::raw('SUM(amount) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $incomeSen = $this->toSen($totals['income'] ?? 0);
        $expenseSen = $this->toSen($totals['expense'] ?? 0);
        $cashflowSen = $incomeSen - $expenseSen;

        $expenseByCategory = $user->transactions()
            ->where('transactions.type', 'expense')
            ->whereBetween('transactions.transaction_date', [$startDate, $endDate])
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->select(
                'categories.id as category_id',
                'categories.name as category_name',
                'categories.color as color',
                DB::raw('SUM(transactions.amount) as total'),
                DB::raw('COUNT(transactions.id) as transaction_count')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($expenseSen) {
                $rowSen = $this->toSen($row->total);
                $percentage = $expenseSen > 0 ? round(($rowSen / $expenseSen) * 100, 1) : 0.0;

                return [
                    'category_id' => (int) $row->category_id,
                    'category_name' => $row->category_name,
                    'color' => $row->color ?? '#6b7280',
                    'total' => $this->fromSen($rowSen),
                    'percentage' => $percentage,
                    'transaction_count' => (int) $row->transaction_count,
                ];
            })
            ->values()
            ->all();

        return [
            'period' => $ref->format('Y-m'),
            'monthly_income' => $this->fromSen($incomeSen),
            'monthly_expenses' => $this->fromSen($expenseSen),
            'monthly_cashflow' => $this->fromSen($cashflowSen),
            'expense_by_category' => $expenseByCategory,
        ];
    }

    public function getMonthlyTrend(User $user, int $months = 6, string $timezone = 'Asia/Jakarta'): array
    {
        $now = Carbon::now($timezone)->startOfMonth();
        $series = [];

        $openingSen = 0;
        foreach ($user->accounts()->get() as $account) {
            $openingSen += $this->toSen($account->opening_balance);
        }

        for ($i = $months - 1; $i >= 0; $i--) {
            $cursor = $now->copy()->subMonths($i);
            $start = $cursor->copy()->startOfMonth()->toDateString();
            $end = $cursor->copy()->endOfMonth()->toDateString();

            $monthTotals = $user->transactions()
                ->whereBetween('transaction_date', [$start, $end])
                ->select('type', DB::raw('SUM(amount) as total'))
                ->groupBy('type')
                ->pluck('total', 'type');

            $cumTotals = $user->transactions()
                ->where('transaction_date', '<=', $end)
                ->select('type', DB::raw('SUM(amount) as total'))
                ->groupBy('type')
                ->pluck('total', 'type');

            $incSen = $this->toSen($monthTotals['income'] ?? 0);
            $expSen = $this->toSen($monthTotals['expense'] ?? 0);
            $cumNetWorthSen = $openingSen
                + $this->toSen($cumTotals['income'] ?? 0)
                - $this->toSen($cumTotals['expense'] ?? 0);

            $series[] = [
                'period' => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('M Y'),
                'income' => $this->fromSen($incSen),
                'expenses' => $this->fromSen($expSen),
                'net_worth' => $this->fromSen($cumNetWorthSen),
            ];
        }

        return $series;
    }
}
