<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorFull;
use LlmCarbon\FootprintCalculatorSimplified as LegacyCalculator;
use LlmCarbon\LanguageModel;

function emissions(LanguageModel $model, EmissionFactor $zone): array
{
    $full = new FootprintCalculatorFull();

    return [
        (new LegacyCalculator())->calculate($model, $zone, 500)->emissionsGco2eq,
        $full->calculate($model, $zone, 500),
    ];
}

?>
-----
<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorFull;
use LlmCarbon\LanguageModel;

function emissions(LanguageModel $model, EmissionFactor $zone): array
{
    $full = new FootprintCalculatorFull();

    return [
        (new FootprintCalculatorFull())->calculate($model, $zone, 500)->emissionsGco2eq,
        $full->calculate($model, $zone, 500),
    ];
}

?>
