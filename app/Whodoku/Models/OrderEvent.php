<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What the provider reported about an order and what the shop did with it. */
#[Fillable(['order_id', 'source', 'provider_status', 'result', 'details'])]
class OrderEvent extends Model
{
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
