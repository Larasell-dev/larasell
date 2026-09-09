<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 */
class Customer extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'larasell_customers';

    protected $guarded = [];

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /** @return BelongsToMany<Authenticatable, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->userModel(),
            'larasell_customer_user',
            'customer_id',
            'user_id',
        )->withTimestamps();
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(app(ModelRegistry::class)->order->class());
    }

    /** @return class-string<Authenticatable> */
    private function userModel(): string
    {
        return config('auth.providers.users.model', Authenticatable::class);
    }
}
