<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'whodoku';

    /** The lists administrators edit: kinds of furniture, their names and the furniture of each room. */
    public function up(): void
    {
        Schema::create('object_kinds', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('label');
            $table->string('icon', 16);
            $table->boolean('occupiable');
            $table->string('standing_phrase')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('object_names', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('object_kind_id')->constrained('object_kinds')->cascadeOnDelete();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('room_themes', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->json('kinds');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_themes');
        Schema::dropIfExists('object_names');
        Schema::dropIfExists('object_kinds');
    }
};
