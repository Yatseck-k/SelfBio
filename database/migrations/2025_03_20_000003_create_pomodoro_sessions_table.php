<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pomodoro_sessions', function (Blueprint $table) {
            $table->id();
            $table->integer('duration')->default(25); // в минутах
            $table->boolean('completed')->default(false);
            $table->enum('type', ['work', 'short_break', 'long_break'])->default('work');
            $table->datetime('started_at');
            $table->datetime('completed_at')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pomodoro_sessions');
    }
};
