<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Screen\AsSource;

#[Fillable(['object_kind_id', 'name', 'active'])]
class ObjectName extends Model
{
    use AsSource;

    /** @return BelongsTo<ObjectKind, $this> */
    public function kind(): BelongsTo
    {
        return $this->belongsTo(ObjectKind::class, 'object_kind_id');
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
