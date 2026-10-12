<?php

namespace Larasell\Larasell\OrderNumbers\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeOrderNumberSequenceCommand extends GeneratorCommand
{
    protected $name = 'make:order-number-sequence';

    protected $description = 'Create a new order number sequence class';

    protected $type = 'Order number sequence';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/order-number-sequence.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\OrderNumbers\\Sequences';
    }
}
