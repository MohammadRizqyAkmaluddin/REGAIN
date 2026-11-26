<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 50)->default('general'); // emergency_fund, gadget, vacation, investment, general
            $table->text('description')->nullable();
            $table->decimal('target_amount', 15, 2);
            $table->decimal('current_actual_allocation', 15, 2)->default(0);
            $table->decimal('allocated_smoking_savings', 15, 2)->default(0);
            $table->date('target_date')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->enum('status', ['active', 'completed', 'paused'])->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_goals');
    }
};
