<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoalRequest;
use App\Models\FinancialGoal;
use App\Services\GoalService;
use App\Services\SmokingTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialGoalController extends Controller
{
    public function __construct(
        private readonly GoalService $goalService,
        private readonly SmokingTrackerService $smokingTracker,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $goals = $user->financialGoals()
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
            ->orderBy('target_date')
            ->get()
            ->map(fn (FinancialGoal $g) => $this->goalService->formatGoal($g))
            ->values();

        $smokingSummary = $this->smokingTracker->getSummary($user);

        return response()->json([
            'data' => $goals,
            'smoking_savings_pool' => [
                'estimated_money_saved' => $smokingSummary['progress']['estimated_money_saved'],
                'allocated_to_goals' => $smokingSummary['progress']['allocated_to_goals'],
                'unallocated_savings' => $smokingSummary['progress']['unallocated_savings'],
            ],
        ]);
    }

    public function store(StoreGoalRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $goal = $user->financialGoals()->create([
            'name' => $data['name'],
            'category' => $data['category'] ?? 'general',
            'description' => $data['description'] ?? null,
            'target_amount' => number_format((float) $data['target_amount'], 2, '.', ''),
            'current_actual_allocation' => number_format((float) ($data['current_actual_allocation'] ?? 0), 2, '.', ''),
            'allocated_smoking_savings' => '0.00',
            'target_date' => $data['target_date'] ?? null,
            'icon' => $data['icon'] ?? 'target',
            'color' => $data['color'] ?? '#10b981',
            'status' => $data['status'] ?? 'active',
        ]);

        if (! empty($data['allocated_smoking_savings'])) {
            $goal = $this->goalService->allocateToGoal($user, $goal, [
                'allocated_smoking_savings' => $data['allocated_smoking_savings'],
            ]);
        }

        return response()->json([
            'data' => $this->goalService->formatGoal($goal),
        ], 201);
    }

    public function update(Request $request, FinancialGoal $goal): JsonResponse
    {
        abort_if($goal->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'category' => ['sometimes', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_amount' => ['sometimes', 'numeric', 'gt:0', 'max:999999999999.99'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'in:active,completed,paused'],
        ]);

        if (array_key_exists('target_amount', $data)) {
            $data['target_amount'] = number_format((float) $data['target_amount'], 2, '.', '');
        }

        $goal->update($data);

        return response()->json([
            'data' => $this->goalService->formatGoal($goal->fresh()),
        ]);
    }

    public function allocate(Request $request, FinancialGoal $goal): JsonResponse
    {
        abort_if($goal->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'allocated_smoking_savings' => ['sometimes', 'numeric', 'min:0', 'max:999999999999.99'],
            'current_actual_allocation' => ['sometimes', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $updated = $this->goalService->allocateToGoal($request->user(), $goal, $data);

        return response()->json([
            'data' => $this->goalService->formatGoal($updated),
        ]);
    }

    public function destroy(Request $request, FinancialGoal $goal): JsonResponse
    {
        abort_if($goal->user_id !== $request->user()->id, 403);

        $goal->delete();

        return response()->json(['message' => 'Target keuangan berhasil dihapus.']);
    }
}
