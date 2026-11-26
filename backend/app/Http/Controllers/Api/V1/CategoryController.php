<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->categories()
            ->with('subcategories')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:income,expense'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $slug = Str::slug($data['name']).'-'.Str::lower(Str::random(4));

        $category = $request->user()->categories()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'type' => $data['type'],
            'icon' => $data['icon'] ?? 'tag',
            'color' => $data['color'] ?? '#6b7280',
            'is_system' => false,
            'sort_order' => 99,
        ]);

        return response()->json(['data' => $category->load('subcategories')], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $category->update($data);

        return response()->json(['data' => $category->load('subcategories')]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403);

        if ($category->transactions()->exists()) {
            return response()->json([
                'message' => 'Kategori yang sudah digunakan pada transaksi tidak dapat dihapus.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }

    public function storeSubcategory(Request $request, Category $category): JsonResponse
    {
        abort_if($category->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $subcategory = $category->subcategories()->create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'is_system' => false,
            'sort_order' => 99,
        ]);

        return response()->json(['data' => $subcategory], 201);
    }

    public function destroySubcategory(Request $request, Subcategory $subcategory): JsonResponse
    {
        abort_if($subcategory->user_id !== $request->user()->id, 403);

        $subcategory->delete();

        return response()->json(['message' => 'Subkategori berhasil dihapus.']);
    }
}
