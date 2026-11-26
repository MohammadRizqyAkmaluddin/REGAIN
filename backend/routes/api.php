<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FinancialGoalController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\RecurringIncomeController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SmokingController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'timezone' => config('app.timezone'),
        'timestamp' => now('Asia/Jakarta')->toIso8601String(),
    ]));

    // Authentication
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Accounts
        Route::get('/accounts', [AccountController::class, 'index']);
        Route::post('/accounts', [AccountController::class, 'store']);
        Route::put('/accounts/{account}', [AccountController::class, 'update']);
        Route::delete('/accounts/{account}', [AccountController::class, 'destroy']);

        // Categories & Subcategories
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::post('/categories/{category}/subcategories', [CategoryController::class, 'storeSubcategory']);
        Route::delete('/subcategories/{subcategory}', [CategoryController::class, 'destroySubcategory']);

        // Transactions
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::post('/transactions/sync', [TransactionController::class, 'sync']);
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
        Route::put('/transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

        // Recurring Incomes
        Route::get('/recurring-incomes', [RecurringIncomeController::class, 'index']);
        Route::post('/recurring-incomes', [RecurringIncomeController::class, 'store']);
        Route::put('/recurring-incomes/{recurringIncome}', [RecurringIncomeController::class, 'update']);
        Route::delete('/recurring-incomes/{recurringIncome}', [RecurringIncomeController::class, 'destroy']);
        Route::post('/recurring-incomes/run', [RecurringIncomeController::class, 'runDue']);

        // Smoking Tracker
        Route::get('/smoking/summary', [SmokingController::class, 'summary']);
        Route::put('/smoking/profile', [SmokingController::class, 'updateProfile']);
        Route::post('/smoking/relapse', [SmokingController::class, 'recordRelapse']);
        Route::get('/smoking/reports', [SmokingController::class, 'reports']);

        // Financial Goals
        Route::get('/goals', [FinancialGoalController::class, 'index']);
        Route::post('/goals', [FinancialGoalController::class, 'store']);
        Route::put('/goals/{goal}', [FinancialGoalController::class, 'update']);
        Route::post('/goals/{goal}/allocate', [FinancialGoalController::class, 'allocate']);
        Route::delete('/goals/{goal}', [FinancialGoalController::class, 'destroy']);

        // Push Notifications
        Route::get('/push/vapid-public-key', [NotificationController::class, 'vapidPublicKey']);
        Route::post('/push/subscriptions', [NotificationController::class, 'subscribe']);
        Route::delete('/push/subscriptions', [NotificationController::class, 'unsubscribe']);
        Route::get('/notifications', [NotificationController::class, 'logs']);
        Route::post('/notifications/test', [NotificationController::class, 'sendTest']);

        // App Settings
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
    });
});
