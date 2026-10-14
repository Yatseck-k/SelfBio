<?php

declare(strict_types=1);

namespace Whodoku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
#[WithoutIncrementing]
class AppearanceSetting extends Model
{
    #[\Override]
    protected $primaryKey = 'key';

    #[\Override]
    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
