# Option and attribute actions take an Origin, and the core audits them

The option and attribute actions now take an `Origin`, like the product actions, and their
events carry it. The core records these changes in the audit log itself, so every app gets
them, wherever the change comes from.

## Who is affected

Apps that call `SaveOptionDefinition`, `DeleteOptionDefinition`, `SaveProductAttribute`,
`CreateProductAttribute`, `UpdateProductAttribute`, `DeleteProductAttribute`,
`CreateProductAttributeValue`, `UpdateProductAttributeValue` or `DeleteProductAttributeValue`,
or construct their events. Apps that recorded their own audit records for these events should
remove them, or the changes are recorded twice.

## How to upgrade

Pass the origin as the last argument:

```php
// Before
app(SaveOptionDefinition::class)->handle($id, $payload);

// After
use Larasell\Larasell\Audit\Origin;

app(SaveOptionDefinition::class)->handle($id, $payload, Origin::web($admin));
```

The records keep their event names, such as `option_definition.created` and
`product_attribute_value.updated`.
