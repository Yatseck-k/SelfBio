<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Screen\AsSource;

/** @property int $usage how many maps use the kind (set by the admin catalog screen) */
#[Fillable(['code', 'label', 'icon', 'occupiable', 'standing_phrase', 'active'])]
class ObjectKind extends Model
{
    use AsSource;

    /** @return HasMany<ObjectName, $this> */
    public function names(): HasMany
    {
        return $this->hasMany(ObjectName::class);
    }

    protected function casts(): array
    {
        return ['occupiable' => 'boolean', 'active' => 'boolean'];
    }
}
