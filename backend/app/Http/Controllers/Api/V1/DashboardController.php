<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Services\FinancialCalculationService;
use App\Services\GoalService;
use App\Services\SmokingTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly FinancialCalculationService $finance,
        private readonly SmokingTrackerService $smokingTracker,
        private readonly GoalService $goalService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tz = $user->appSetting?->timezone ?? 'Asia/Jakarta';
        $month = $request->query('month');

        if ($month && ! preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            $month = null;
        }

        $netWorth = $this->finance->getNetWorthSummary($user);
        $monthly = $this->finance->getMonthlySummary($user, $month, $tz);
        $trend = $this->finance->getMonthlyTrend($user, 6, $tz);
        $smoking = $this->smokingTracker->getSummary($user);

        $recentTransactions = $user->transactions()
            ->with(['account', 'category', 'subcategory'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('transaction_time')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $goals = $user->financialGoals()
            ->where('status', 'active')
            ->orderBy('target_date')
            ->limit(4)
            ->get()
            ->map(fn ($g) => $this->goalService->formatGoal($g))
            ->values();

        return response()->json([
            'finance' => [
                'opening_balance' => $netWorth['opening_balance'],
                'total_actual_income' => $netWorth['total_actual_income'],
                'total_actual_expenses' => $netWorth['total_actual_expenses'],
                'actual_net_worth' => $netWorth['actual_net_worth'],
                'accounts' => $netWorth['accounts'],
                'monthly' => $monthly,
                'trend' => $trend,
            ],
            'smoking' => [
                'current_streak_days' => $smoking['progress']['current_streak_days'],
                'total_smoke_free_days' => $smoking['progress']['total_smoke_free_days'],
                'estimated_money_saved' => $smoking['progress']['estimated_money_saved'],
                'estimated_packs_avoided' => $smoking['progress']['estimated_packs_avoided'],
                'estimated_cigarettes_avoided' => $smoking['progress']['estimated_cigarettes_avoided'],
                'monthly_expenditure' => $smoking['baseline']['monthly_expenditure'],
                'yearly_expenditure' => $smoking['baseline']['yearly_expenditure'],
            ],
            'recent_transactions' => TransactionResource::collection($recentTransactions),
            'active_goals' => $goals,
        ]);
    }
}
