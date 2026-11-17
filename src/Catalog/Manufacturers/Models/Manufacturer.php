<?php

namespace Larasell\Larasell\Catalog\Manufacturers\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Catalog\Manufacturers\PostalAddress;
use Larasell\Larasell\Catalog\Manufacturers\ResponsiblePerson;
use Larasell\Larasell\Catalog\Models\Product;

/**
 * The legal producer of products, named on product pages in the EU under the GPSR.
 *
 * @property int $id
 * @property string $name
 * @property PostalAddress $address
 * @property string|null $email
 * @property string|null $url
 * @property ResponsiblePerson|null $responsible_person Needed when the manufacturer is outside the EU.
 */
class Manufacturer extends Model
{
    protected $table = 'larasell_manufacturers';

    protected $guarded = [];

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isInEu(): bool
    {
        return $this->address->country->isInEu();
    }

    /** Who customers in the EU contact about its products: itself in the EU, otherwise its responsible person. */
    public function euContact(): self|ResponsiblePerson|null
    {
        return $this->isInEu() ? $this : $this->responsible_person;
    }

    /**
     * What an order item keeps of it, so the order still names it after it changes.
     *
     * @return array{name: string, address: array<string, mixed>, email: string|null, url: string|null, responsible_person: array<string, mixed>|null}
     */
    public function snapshot(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address->toArray(),
            'email' => $this->email,
            'url' => $this->url,
            'responsible_person' => $this->responsible_person?->toArray(),
        ];
    }

    /** @return Attribute<PostalAddress, PostalAddress|array<string, mixed>> */
    protected function address(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): PostalAddress => PostalAddress::fromArray((array) json_decode($value, true)),
            set: fn (PostalAddress|array $value): string => json_encode(($value instanceof PostalAddress ? $value : PostalAddress::fromArray($value))->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    /** @return Attribute<ResponsiblePerson|null, ResponsiblePerson|array<string, mixed>|null> */
    protected function responsiblePerson(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?ResponsiblePerson {
                $person = $value === null ? null : json_decode($value, true);

                return is_array($person) ? ResponsiblePerson::fromArray($person) : null;
            },
            set: fn (ResponsiblePerson|array|null $value): ?string => $value === null
                ? null
                : json_encode(($value instanceof ResponsiblePerson ? $value : ResponsiblePerson::fromArray($value))->toArray(), JSON_THROW_ON_ERROR),
        );
    }
}
