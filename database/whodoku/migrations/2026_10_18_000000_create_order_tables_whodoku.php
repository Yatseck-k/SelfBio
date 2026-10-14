<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Paid hint packs: orders with the payment of the provider, a journal of what the provider reported, and the hints bought. */
return new class extends Migration
{
    protected $connection = 'whodoku';

    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('product', 40);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('amount_kopecks');
            $table->string('currency', 3)->default('RUB');
            $table->string('status', 16)->default('pending')->index();
            $table->uuid('idempotence_key')->unique();
            $table->string('provider', 20);
            $table->string('provider_payment_id', 80)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('provider_status', 40)->nullable();
            $table->string('result', 60);
            $table->json('details')->nullable();
            $table->timestamps();
        });

        Schema::table('players', function (Blueprint $table): void {
            $table->unsignedInteger('purchased_hints')->default(0)->after('points');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn('purchased_hints');
        });
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('orders');
    }
};
