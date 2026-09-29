<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

// Every expression below is typed `false` (or `false|null`) by PHPStan. Rector's isObjectType()
// strips `false` before comparing, leaving `never`, a subtype of every class: without a guard,
// each one was taken for a simplified calculator (3 577 false positives on 60 vendor packages).
function find(array $items, bool $strict = false): ?string
{
    $found = false;
    foreach ($items as $item) {
        if ($item === 'x') {
            $found = true;
        }
    }
    if ($found === false) {
        return null;
    }
    $maybe = $strict ? null : false;
    if (!$maybe instanceof \stdClass) {
        var_dump($maybe);
    }

    return $strict ? 'strict' : 'loose';
}

?>
