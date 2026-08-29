<?php

namespace Larasell\Larasell\Contracts;

interface CodedPromotion extends Promotion
{
    public function code(): string;
}
