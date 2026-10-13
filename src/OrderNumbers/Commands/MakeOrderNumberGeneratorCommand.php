<?php

namespace Larasell\Larasell\OrderNumbers\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeOrderNumberGeneratorCommand extends GeneratorCommand
{
    protected $name = 'make:order-number-generator';

    protected $description = 'Create a new order number generator class';

    protected $type = 'Order number generator';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/order-number-generator.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\OrderNumbers\\Generators';
    }
}
