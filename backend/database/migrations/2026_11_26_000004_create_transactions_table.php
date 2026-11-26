<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->decimal('amount', 15, 2);
            $table->string('description', 255)->nullable();
            $table->date('transaction_date');
            $table->time('transaction_time')->default('00:00:00');
            $table->string('source', 30)->default('manual'); // manual, recurring
            $table->uuid('client_uuid')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_uuid']);
            $table->index(['user_id', 'type', 'transaction_date']);
            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
