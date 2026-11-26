<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $user->transactions()
            ->with(['account', 'category', 'subcategory']);

        if ($type = $request->query('type')) {
            if (in_array($type, ['income', 'expense'], true)) {
                $query->where('type', $type);
            }
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', (int) $categoryId);
        }

        if ($subcategoryId = $request->query('subcategory_id')) {
            $query->where('subcategory_id', (int) $subcategoryId);
        }

        if ($accountId = $request->query('account_id')) {
            $query->where('account_id', (int) $accountId);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('transaction_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('transaction_date', '<=', $dateTo);
        }

        if ($month = $request->query('month')) {
            if (preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
                $start = Carbon::createFromFormat('Y-m', $month, 'Asia/Jakarta')->startOfMonth()->toDateString();
                $end = Carbon::createFromFormat('Y-m', $month, 'Asia/Jakarta')->endOfMonth()->toDateString();
                $query->whereBetween('transaction_date', [$start, $end]);
            }
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('subcategory', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 25)));

        $paginated = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('transaction_time')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => TransactionResource::collection($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Idempotent handling for offline-created transactions
        if (! empty($data['client_uuid'])) {
            $existing = $user->transactions()
                ->where('client_uuid', $data['client_uuid'])
                ->with(['account', 'category', 'subcategory'])
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => new TransactionResource($existing),
                    'deduplicated' => true,
                ], 200);
            }
        }

        $transaction = $this->createValidatedTransaction($user, $data);

        return response()->json([
            'data' => new TransactionResource($transaction->load(['account', 'category', 'subcategory'])),
        ], 201);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        abort_if($transaction->user_id !== $request->user()->id, 403);

        return response()->json([
            'data' => new TransactionResource($transaction->load(['account', 'category', 'subcategory'])),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        abort_if($transaction->user_id !== $request->user()->id, 403);

        $data = $request->validated();
        $targetType = $data['type'] ?? $transaction->type;
        $targetCategoryId = $data['category_id'] ?? $transaction->category_id;

        $category = Category::where('id', $targetCategoryId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($category->type !== $targetType) {
            throw ValidationException::withMessages([
                'category_id' => ['Kategori tidak sesuai dengan jenis transaksi.'],
            ]);
        }

        if (array_key_exists('subcategory_id', $data) && $data['subcategory_id'] !== null) {
            $sub = Subcategory::where('id', $data['subcategory_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            if ($sub->category_id !== $category->id) {
                throw ValidationException::withMessages([
                    'subcategory_id' => ['Subkategori tidak termasuk dalam kategori yang dipilih.'],
                ]);
            }
        }

        if (array_key_exists('amount', $data)) {
            $data['amount'] = number_format((float) $data['amount'], 2, '.', '');
        }

        if (array_key_exists('transaction_time', $data) && $data['transaction_time']) {
            $data['transaction_time'] = strlen($data['transaction_time']) === 5
                ? $data['transaction_time'].':00'
                : $data['transaction_time'];
        }

        $transaction->update($data);

        return response()->json([
            'data' => new TransactionResource($transaction->fresh()->load(['account', 'category', 'subcategory'])),
        ]);
    }

    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        abort_if($transaction->user_id !== $request->user()->id, 403);

        $transaction->delete();

        return response()->json(['message' => 'Transaksi berhasil dihapus.']);
    }

    /**
     * Batch sync endpoint for offline-queued transactions (Dexie.js).
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'transactions' => ['required', 'array', 'max:50'],
            'transactions.*.client_uuid' => ['required', 'uuid'],
            'transactions.*.account_id' => ['nullable', 'integer'],
            'transactions.*.type' => ['required', 'in:income,expense'],
            'transactions.*.category_id' => ['required', 'integer'],
            'transactions.*.subcategory_id' => ['nullable', 'integer'],
            'transactions.*.amount' => ['required', 'numeric', 'gt:0'],
            'transactions.*.description' => ['nullable', 'string', 'max:255'],
            'transactions.*.transaction_date' => ['required', 'date_format:Y-m-d'],
            'transactions.*.transaction_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ]);

        $results = [];

        foreach ($validated['transactions'] as $item) {
            try {
                $existing = $user->transactions()
                    ->where('client_uuid', $item['client_uuid'])
                    ->first();

                if ($existing) {
                    $results[] = [
                        'client_uuid' => $item['client_uuid'],
                        'status' => 'synced',
                        'transaction_id' => $existing->id,
                        'deduplicated' => true,
                    ];

                    continue;
                }

                $created = $this->createValidatedTransaction($user, $item);
                $results[] = [
                    'client_uuid' => $item['client_uuid'],
                    'status' => 'synced',
                    'transaction_id' => $created->id,
                    'deduplicated' => false,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'client_uuid' => $item['client_uuid'],
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    private function createValidatedTransaction($user, array $data): Transaction
    {
        $accountId = $data['account_id'] ?? $user->accounts()->where('is_default', true)->value('id') ?? $user->accounts()->value('id');
        if (! $accountId || ! $user->accounts()->where('id', $accountId)->exists()) {
            throw ValidationException::withMessages([
                'account_id' => ['Akun tidak valid.'],
            ]);
        }

        $category = $user->categories()->where('id', $data['category_id'])->first();
        if (! $category || $category->type !== $data['type']) {
            throw ValidationException::withMessages([
                'category_id' => ['Kategori tidak sesuai dengan jenis transaksi.'],
            ]);
        }

        $subcategoryId = $data['subcategory_id'] ?? null;
        if ($subcategoryId !== null) {
            $validSub = $user->subcategories()
                ->where('id', $subcategoryId)
                ->where('category_id', $category->id)
                ->exists();

            if (! $validSub) {
                throw ValidationException::withMessages([
                    'subcategory_id' => ['Subkategori tidak termasuk dalam kategori yang dipilih.'],
                ]);
            }
        }

        $tz = $user->appSetting?->timezone ?? 'Asia/Jakarta';
        $time = $data['transaction_time'] ?? Carbon::now($tz)->format('H:i:s');
        if (strlen($time) === 5) {
            $time .= ':00';
        }

        return DB::transaction(function () use ($user, $accountId, $category, $subcategoryId, $data, $time) {
            return Transaction::create([
                'user_id' => $user->id,
                'account_id' => $accountId,
                'category_id' => $category->id,
                'subcategory_id' => $subcategoryId,
                'type' => $data['type'],
                'amount' => number_format((float) $data['amount'], 2, '.', ''),
                'description' => $data['description'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'transaction_time' => $time,
                'source' => 'manual',
                'client_uuid' => $data['client_uuid'] ?? null,
            ]);
        });
    }
}
