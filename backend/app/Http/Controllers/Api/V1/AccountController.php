<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\FinancialCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(
        private readonly FinancialCalculationService $finance,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $summary = $this->finance->getNetWorthSummary($request->user());

        return response()->json([
            'data' => $summary['accounts'],
            'actual_net_worth' => $summary['actual_net_worth'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:cash,bank,ewallet,investment'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        $account = DB::transaction(function () use ($user, $data) {
            if (! empty($data['is_default'])) {
                $user->accounts()->update(['is_default' => false]);
            }

            return $user->accounts()->create([
                'name' => $data['name'],
                'type' => $data['type'],
                'opening_balance' => number_format((float) $data['opening_balance'], 2, '.', ''),
                'currency' => 'IDR',
                'is_default' => (bool) ($data['is_default'] ?? ! $user->accounts()->exists()),
            ]);
        });

        return response()->json(['data' => $account], 201);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        abort_if($account->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'in:cash,bank,ewallet,investment'],
            'opening_balance' => ['sometimes', 'numeric', 'min:0', 'max:999999999999.99'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $account, $data) {
            if (! empty($data['is_default'])) {
                $request->user()->accounts()->where('id', '!=', $account->id)->update(['is_default' => false]);
            }

            if (array_key_exists('opening_balance', $data)) {
                $data['opening_balance'] = number_format((float) $data['opening_balance'], 2, '.', '');
            }

            $account->update($data);
        });

        return response()->json(['data' => $account->fresh()]);
    }

    public function destroy(Request $request, Account $account): JsonResponse
    {
        abort_if($account->user_id !== $request->user()->id, 403);

        if ($request->user()->accounts()->count() <= 1) {
            return response()->json([
                'message' => 'Minimal harus ada satu akun aktif.',
            ], 422);
        }

        if ($account->transactions()->exists()) {
            return response()->json([
                'message' => 'Akun yang sudah memiliki transaksi tidak dapat dihapus.',
            ], 422);
        }

        $account->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }
}
