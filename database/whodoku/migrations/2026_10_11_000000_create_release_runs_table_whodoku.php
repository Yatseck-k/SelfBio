<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'whodoku';

    /** One row per run of the release tool, so the admin screen can show what is going on in the background. */
    public function up(): void
    {
        Schema::create('release_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 24);
            $table->string('status', 16)->default('running');
            $table->unsignedInteger('requested')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->string('message')->nullable();
            $table->string('actor_email')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_runs');
    }
};
