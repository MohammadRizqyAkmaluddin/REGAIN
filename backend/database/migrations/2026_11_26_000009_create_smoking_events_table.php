<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smoking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('smoking_profile_id')->constrained()->cascadeOnDelete();
            $table->enum('event_type', ['quit_started', 'relapse', 'milestone', 'note']);
            $table->date('event_date');
            $table->time('event_time')->default('00:00:00');
            $table->decimal('packs_smoked', 8, 2)->default(0);
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->string('trigger', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_type', 'event_date']);
            $table->index(['smoking_profile_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smoking_events');
    }
};
