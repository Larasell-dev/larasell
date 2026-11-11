<?php

namespace Larasell\Larasell\Catalog\Options\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Translatable;

/**
 * A reusable option such as Colour or Size, whose values products choose from.
 *
 * @property int $id
 * @property string $slug
 * @property Translatable $name
 * @property string $display dropdown, buttons or swatch
 */
class OptionDefinition extends Model
{
    protected $table = 'larasell_option_definitions';

    protected $guarded = [];

    protected $attributes = ['display' => 'dropdown'];

    protected $casts = ['name' => TranslatableCast::class];

    /** @return HasMany<OptionDefinitionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(OptionDefinitionValue::class)->orderBy('position')->orderBy('id');
    }
}
