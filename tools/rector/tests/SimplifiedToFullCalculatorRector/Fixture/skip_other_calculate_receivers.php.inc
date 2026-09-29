<?php

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector\Fixture;

// Same shape as the supported case (new, assigned, ->calculate()->totalEnergyWh), but on objects
// that are not the simplified calculator: an anonymous class and a class chosen at runtime.
function lookalikes(string $className): array
{
    $calculator = new class {
        public function calculate(): \stdClass
        {
            $result = new \stdClass();
            $result->totalEnergyWh = 1.0;

            return $result;
        }
    };
    $dynamic = new $className();

    return [$calculator->calculate()->totalEnergyWh, $dynamic->calculate()->totalEnergyWh];
}

?>
