<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Interfaces\BaseModelInterface;
use Illuminate\Database\Eloquent\Model;

class Welcome extends Model implements BaseModelInterface
{
    protected $table = 'welcome';

    public function getClassName(): string
    {
        return self::class;
    }

    public function getWelcomeData(): BaseModelInterface
    {
        return self::query()->first();
    }

    protected $fillable = [
        'title',
        'body',
    ];
}
