<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Screen\AsSource;

/**
 * A purchase of a hint pack. The price is stored in kopecks and is taken from the config, never from the client.
 *
 * @property int $id
 * @property string $public_id
 * @property int $player_id
 * @property string $product
 * @property int $quantity
 * @property int $amount_kopecks
 * @property string $currency
 * @property string $status pending|paid|canceled|refunded|failed
 * @property string $idempotence_key
 * @property string $provider
 * @property string|null $provider_payment_id
 */
#[Fillable([
    'public_id', 'player_id', 'product', 'quantity', 'amount_kopecks', 'currency', 'status', 'idempotence_key',
    'provider', 'provider_payment_id', 'paid_at', 'fulfilled_at', 'meta',
])]
#[RouteKey('public_id')]
class Order extends Model
{
    use AsSource;

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const CANCELED = 'canceled';

    public const REFUNDED = 'refunded';

    public const FAILED = 'failed';

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return HasMany<OrderEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function rubles(): string
    {
        return number_format($this->amount_kopecks / 100, 2, '.', '');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount_kopecks' => 'integer',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
