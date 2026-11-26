<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smoking_savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('smoking_profile_id')->constrained()->cascadeOnDelete();
            $table->date('saving_date');
            $table->decimal('estimated_amount', 15, 2)->default(0);
            $table->decimal('packs_avoided', 8, 2)->default(0);
            $table->unsignedInteger('cigarettes_avoided')->default(0);
            $table->boolean('is_smoke_free_day')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'saving_date']);
            $table->index(['smoking_profile_id', 'saving_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smoking_savings');
    }
};
