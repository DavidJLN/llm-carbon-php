<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

// Siblings of the `false` trap: `null` and `mixed` are also handled specially by Rector's
// isObjectType(). An untyped receiver with a calculate() call chain must not be taken for the
// simplified calculator either.
function untyped(mixed $calculator, ?object $maybe, int|false $position): mixed
{
    $nothing = null;
    if ($position === false || $maybe === null) {
        return $nothing;
    }

    return $calculator->calculate($maybe, $position, 500)->totalEnergyWh;
}

?>
