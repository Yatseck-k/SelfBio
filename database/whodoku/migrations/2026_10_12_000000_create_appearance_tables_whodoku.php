<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'whodoku';

    public function up(): void
    {
        // An image the administrator uploaded in place of a built-in one, by slot (`kind:sofa`, `floor:спальня`, …).
        Schema::create('appearance_images', function (Blueprint $table): void {
            $table->id();
            $table->string('slot', 120)->unique();
            $table->string('path');
            $table->timestamps();
        });

        // Texts and switches of the design: seal captions, animations.
        Schema::create('appearance_settings', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appearance_settings');
        Schema::dropIfExists('appearance_images');
    }
};
