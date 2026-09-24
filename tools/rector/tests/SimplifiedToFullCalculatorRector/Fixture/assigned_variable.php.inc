<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorSimplified;
use LlmCarbon\LanguageModel;

function totalEnergyWh(LanguageModel $model, EmissionFactor $zone, int $tokens): float
{
    $calculator = new FootprintCalculatorSimplified();

    return $calculator->calculate($model, $zone, $tokens)->totalEnergyWh;
}

?>
-----
<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

use LlmCarbon\EmissionFactor;
use LlmCarbon\LanguageModel;

function totalEnergyWh(LanguageModel $model, EmissionFactor $zone, int $tokens): float
{
    $calculator = new \LlmCarbon\FootprintCalculatorFull();

    return $calculator->calculate($model, $zone, $tokens)->totalEnergyWh;
}

?>
