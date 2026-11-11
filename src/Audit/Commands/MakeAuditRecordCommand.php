<?php

namespace Larasell\Larasell\Audit\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal Run it as make:larasell-audit-record.
 */
#[AsCommand(name: 'make:larasell-audit-record')]
class MakeAuditRecordCommand extends GeneratorCommand
{
    protected $name = 'make:larasell-audit-record';

    protected $description = 'Create a new audit record class.';

    protected $type = 'Audit record';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/audit-record.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\AuditRecords';
    }

    /**
     * Names the event like the panel's own, e.g. ProductReviewApproved records product_review.approved.
     */
    protected function buildClass($name): string
    {
        $event = Str::replaceLast('_', '.', Str::snake(class_basename($name)));

        return str_replace('{{ event }}', $event, parent::buildClass($name));
    }

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the audit record already exists'],
        ];
    }
}
