<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property array<string, mixed> $value
 */
class Setting extends Model
{
    protected $table = 'larasell_settings';

    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
    ];
}
