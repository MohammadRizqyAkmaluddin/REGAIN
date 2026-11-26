<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recurring_income_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('execution_period', 7); // YYYY-MM in Asia/Jakarta
            $table->date('scheduled_date');
            $table->timestamp('executed_at');
            $table->string('status', 20)->default('completed'); // completed, skipped, failed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['recurring_income_id', 'execution_period'], 'recurring_income_period_unique');
            $table->index(['user_id', 'execution_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_executions');
    }
};
