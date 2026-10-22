<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

/** A named setting of the site that the editors change in the admin panel (the name of the site, for now). */
#[Fillable(['key', 'value'])]
#[Table(name: 'site_settings', key: 'key', keyType: 'string')]
#[Connection('pgsql')]
#[WithoutIncrementing]
class SiteSetting extends Model {}
