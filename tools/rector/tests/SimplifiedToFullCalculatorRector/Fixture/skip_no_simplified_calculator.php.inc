<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorFull;
use LlmCarbon\LanguageModel;

function fullOnly(LanguageModel $model, EmissionFactor $zone): float
{
    return (new FootprintCalculatorFull())->calculate($model, $zone, 500)->totalEnergyWh;
}

?>
