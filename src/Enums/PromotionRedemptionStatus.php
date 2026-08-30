<?php

namespace Larasell\Larasell\Enums;

enum PromotionRedemptionStatus: string
{
    case Reserved = 'reserved';
    case Redeemed = 'redeemed';
    case Released = 'released';
}
