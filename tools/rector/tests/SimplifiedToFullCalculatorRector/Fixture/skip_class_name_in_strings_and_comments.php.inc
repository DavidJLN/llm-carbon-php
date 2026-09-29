<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

/**
 * Mentions of the class that are only text: the rule reads resolved types, never text.
 *
 * @see \LlmCarbon\FootprintCalculatorSimplified
 */
function describe(): string
{
    // new FootprintCalculatorSimplified() used to be called here.
    $label = 'LlmCarbon\FootprintCalculatorSimplified';

    return sprintf('%s est remplacé par FootprintCalculatorFull', $label);
}

?>
