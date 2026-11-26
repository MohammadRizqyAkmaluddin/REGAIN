<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecurringIncomeRequest;
use App\Models\RecurringIncome;
use App\Services\RecurringIncomeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringIncomeController extends Controller
{
    public function __construct(
        private readonly RecurringIncomeService $recurringService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()
            ->recurringIncomes()
            ->with(['account', 'category', 'subcategory'])
            ->with(['executions' => fn ($q) => $q->latest('executed_at')->limit(6)])
            ->orderByDesc('is_active')
            ->orderBy('day_of_month')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(StoreRecurringIncomeRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $accountId = $data['account_id'] ?? $user->accounts()->where('is_default', true)->value('id') ?? $user->accounts()->value('id');
        $categoryId = $data['category_id']
            ?? $user->categories()->where('type', 'income')->where('slug', 'salary')->value('id')
            ?? $user->categories()->where('type', 'income')->value('id');

        $tz = $user->appSetting?->timezone ?? 'Asia/Jakarta';
        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'], $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $nextRunDate = $this->recurringService->computeNextRunDate((int) $data['day_of_month'], $startDate, true);

        $recurring = $user->recurringIncomes()->create([
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'name' => $data['name'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'day_of_month' => (int) $data['day_of_month'],
            'description' => $data['description'] ?? null,
            'start_date' => $startDate->toDateString(),
            'next_run_date' => $nextRunDate->toDateString(),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json([
            'data' => $recurring->load(['account', 'category', 'subcategory']),
        ], 201);
    }

    public function update(Request $request, RecurringIncome $recurringIncome): JsonResponse
    {
        abort_if($recurringIncome->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'amount' => ['sometimes', 'numeric', 'gt:0', 'max:999999999999.99'],
            'day_of_month' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('amount', $data)) {
            $data['amount'] = number_format((float) $data['amount'], 2, '.', '');
        }

        if (array_key_exists('day_of_month', $data)) {
            $tz = $request->user()->appSetting?->timezone ?? 'Asia/Jakarta';
            $data['next_run_date'] = $this->recurringService
                ->computeNextRunDate((int) $data['day_of_month'], Carbon::now($tz), true)
                ->toDateString();
        }

        $recurringIncome->update($data);

        return response()->json([
            'data' => $recurringIncome->fresh()->load(['account', 'category', 'subcategory']),
        ]);
    }

    public function destroy(Request $request, RecurringIncome $recurringIncome): JsonResponse
    {
        abort_if($recurringIncome->user_id !== $request->user()->id, 403);

        $recurringIncome->delete();

        return response()->json(['message' => 'Pemasukan rutin berhasil dihapus.']);
    }

    public function runDue(Request $request): JsonResponse
    {
        $created = $this->recurringService->processDueIncomes(null, $request->user()->id);

        return response()->json([
            'processed_count' => $created,
            'message' => $created > 0
                ? "{$created} pemasukan rutin berhasil dicatat."
                : 'Tidak ada pemasukan rutin yang jatuh tempo saat ini.',
        ]);
    }
}
