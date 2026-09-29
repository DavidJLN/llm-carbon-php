<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorFull;
use LlmCarbon\LanguageModel;

// Mirror of the Refused/ cases, on the FULL calculator: storing the result, passing the instance
// along and declaring its type are all fine here, since there is nothing to migrate.
function keep(FootprintCalculatorFull $calculator, LanguageModel $model, EmissionFactor $zone): void
{
    $footprint = $calculator->calculate($model, $zone, 500);
    var_dump($calculator, $footprint, $calculator instanceof FootprintCalculatorFull, FootprintCalculatorFull::class);
}

?>
