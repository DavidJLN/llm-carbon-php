<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

// Same short name as the real calculator, but a different resolved type: must not be touched.
final class FootprintCalculatorSimplified
{
    public function calculate(): \stdClass
    {
        return new \stdClass();
    }
}

function unrelated(): \stdClass
{
    $calculator = new FootprintCalculatorSimplified();

    return $calculator->calculate();
}

?>
