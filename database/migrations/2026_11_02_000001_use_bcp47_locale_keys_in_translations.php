<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $columns = [
        'larasell_products' => ['name', 'slug', 'description'],
        'larasell_categories' => ['name', 'slug'],
        'larasell_order_items' => ['product_name'],
    ];

    public function up(): void
    {
        $this->renameLocaleKeys('_', '-');
    }

    public function down(): void
    {
        $this->renameLocaleKeys('-', '_');
    }

    private function renameLocaleKeys(string $from, string $to): void
    {
        foreach ($this->columns as $table => $columns) {
            DB::table($table)->select(['id', ...$columns])->orderBy('id')->each(function (object $row) use ($table, $columns, $from, $to): void {
                $changes = [];

                foreach ($columns as $column) {
                    $translations = is_string($row->{$column}) ? json_decode($row->{$column}, true) : null;

                    if (! is_array($translations)) {
                        continue;
                    }

                    $renamed = [];

                    foreach ($translations as $locale => $value) {
                        $renamed[str_replace($from, $to, (string) $locale)] = $value;
                    }

                    if ($renamed !== $translations) {
                        $changes[$column] = json_encode($renamed, JSON_THROW_ON_ERROR);
                    }
                }

                if ($changes !== []) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            });
        }
    }
};
