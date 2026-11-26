<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smoking_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('quit_date');
            $table->decimal('packs_per_week', 8, 2)->default(10.00);
            $table->decimal('price_per_pack', 15, 2)->default(30000.00);
            $table->unsignedSmallInteger('cigarettes_per_pack')->default(16);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smoking_profiles');
    }
};
