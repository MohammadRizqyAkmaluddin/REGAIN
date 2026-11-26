<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('theme', ['dark', 'light', 'system'])->default('dark');
            $table->string('locale', 10)->default('id');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('currency', 3)->default('IDR');
            $table->boolean('notifications_enabled')->default(true);
            $table->string('active_hours_start', 5)->default('08:00');
            $table->string('active_hours_end', 5)->default('21:00');
            $table->unsignedTinyInteger('max_daily_notifications')->default(3);
            $table->unsignedSmallInteger('notification_cooldown_minutes')->default(180);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
