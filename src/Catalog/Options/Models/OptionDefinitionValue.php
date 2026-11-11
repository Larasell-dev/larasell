<?php

namespace Larasell\Larasell\Catalog\Options\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Translatable;

/**
 * @property int $id
 * @property int $option_definition_id
 * @property string $slug
 * @property Translatable $name
 * @property string|null $swatch A colour such as #000000
 * @property int $position
 * @property OptionDefinition $definition
 */
class OptionDefinitionValue extends Model
{
    protected $table = 'larasell_option_definition_values';

    protected $guarded = [];

    protected $casts = ['name' => TranslatableCast::class, 'position' => 'integer'];

    /** @return BelongsTo<OptionDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(OptionDefinition::class, 'option_definition_id');
    }
}
