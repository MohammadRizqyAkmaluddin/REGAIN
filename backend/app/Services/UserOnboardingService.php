<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Category;
use App\Models\SmokingEvent;
use App\Models\SmokingProfile;
use App\Models\Subcategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserOnboardingService
{
    public function initializeUser(User $user, float|string $openingBalance = '0.00', ?string $quitDate = null): void
    {
        DB::transaction(function () use ($user, $openingBalance, $quitDate) {
            if (! $user->accounts()->exists()) {
                Account::create([
                    'user_id' => $user->id,
                    'name' => 'Dompet Utama',
                    'type' => 'bank',
                    'opening_balance' => number_format((float) $openingBalance, 2, '.', ''),
                    'currency' => 'IDR',
                    'is_default' => true,
                ]);
            }

            if (! $user->appSetting()->exists()) {
                AppSetting::create([
                    'user_id' => $user->id,
                    'theme' => 'dark',
                    'locale' => 'id',
                    'timezone' => 'Asia/Jakarta',
                    'currency' => 'IDR',
                    'notifications_enabled' => true,
                    'active_hours_start' => '08:00',
                    'active_hours_end' => '21:00',
                    'max_daily_notifications' => 3,
                    'notification_cooldown_minutes' => 180,
                ]);
            }

            if (! $user->categories()->exists()) {
                $this->seedDefaultCategories($user);
            }

            if (! $user->smokingProfile()->exists()) {
                $effectiveQuitDate = $quitDate ?? Carbon::now('Asia/Jakarta')->toDateString();

                $profile = SmokingProfile::create([
                    'user_id' => $user->id,
                    'quit_date' => $effectiveQuitDate,
                    'packs_per_week' => '10.00',
                    'price_per_pack' => '30000.00',
                    'cigarettes_per_pack' => 16,
                    'is_active' => true,
                ]);

                SmokingEvent::create([
                    'user_id' => $user->id,
                    'smoking_profile_id' => $profile->id,
                    'event_type' => 'quit_started',
                    'event_date' => $effectiveQuitDate,
                    'event_time' => Carbon::now('Asia/Jakarta')->format('H:i:s'),
                    'packs_smoked' => '0.00',
                    'estimated_cost' => '0.00',
                    'notes' => 'Memulai perjalanan bebas rokok bersama Deep Pockets.',
                ]);
            }
        });
    }

    public function seedDefaultCategories(User $user): void
    {
        $expenseCategories = [
            [
                'name' => 'Makanan & Minuman',
                'slug' => 'food-beverage',
                'icon' => 'utensils',
                'color' => '#f59e0b',
                'subcategories' => [
                    'Makanan Pokok',
                    'Camilan',
                    'Kopi',
                    'Minuman',
                    'Restoran',
                ],
            ],
            [
                'name' => 'Transportasi',
                'slug' => 'transportation',
                'icon' => 'car',
                'color' => '#3b82f6',
                'subcategories' => [
                    'Bensin',
                    'Parkir',
                    'Transportasi Umum',
                    'Transportasi Online',
                ],
            ],
            [
                'name' => 'Gaya Hidup',
                'slug' => 'lifestyle',
                'icon' => 'sparkles',
                'color' => '#ec4899',
                'subcategories' => [
                    'Hiburan',
                    'Belanja',
                    'Perawatan Diri',
                ],
            ],
            [
                'name' => 'Tagihan',
                'slug' => 'bills',
                'icon' => 'receipt',
                'color' => '#ef4444',
                'subcategories' => [
                    'Internet',
                    'Listrik',
                    'Langganan',
                ],
            ],
            [
                'name' => 'Lainnya',
                'slug' => 'other',
                'icon' => 'dots',
                'color' => '#6b7280',
                'subcategories' => [
                    'Lain-lain',
                ],
            ],
        ];

        $incomeCategories = [
            [
                'name' => 'Gaji',
                'slug' => 'salary',
                'icon' => 'briefcase',
                'color' => '#10b981',
                'subcategories' => [
                    'Gaji Bulanan',
                    'Tunjangan',
                ],
            ],
            [
                'name' => 'Pemasukan Tambahan',
                'slug' => 'additional-income',
                'icon' => 'trending-up',
                'color' => '#14b8a6',
                'subcategories' => [
                    'Freelance',
                    'Bonus',
                    'Hadiah',
                    'Lain-lain',
                ],
            ],
        ];

        foreach ($expenseCategories as $idx => $catData) {
            $category = Category::create([
                'user_id' => $user->id,
                'name' => $catData['name'],
                'slug' => $catData['slug'],
                'type' => 'expense',
                'icon' => $catData['icon'],
                'color' => $catData['color'],
                'is_system' => true,
                'sort_order' => $idx + 1,
            ]);

            foreach ($catData['subcategories'] as $subIdx => $subName) {
                Subcategory::create([
                    'user_id' => $user->id,
                    'category_id' => $category->id,
                    'name' => $subName,
                    'slug' => Str::slug($subName),
                    'is_system' => true,
                    'sort_order' => $subIdx + 1,
                ]);
            }
        }

        foreach ($incomeCategories as $idx => $catData) {
            $category = Category::create([
                'user_id' => $user->id,
                'name' => $catData['name'],
                'slug' => $catData['slug'],
                'type' => 'income',
                'icon' => $catData['icon'],
                'color' => $catData['color'],
                'is_system' => true,
                'sort_order' => $idx + 1,
            ]);

            foreach ($catData['subcategories'] as $subIdx => $subName) {
                Subcategory::create([
                    'user_id' => $user->id,
                    'category_id' => $category->id,
                    'name' => $subName,
                    'slug' => Str::slug($subName),
                    'is_system' => true,
                    'sort_order' => $subIdx + 1,
                ]);
            }
        }
    }
}
