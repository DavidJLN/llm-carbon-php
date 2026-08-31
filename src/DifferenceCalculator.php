<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Computes the difference between the FULL model (FootprintCalculatorFull) and the SIMPLIFIED
 * model (FootprintCalculatorSimplified) for the same scenario, and breaks it down into a
 * "server" part and a "cards" part. Carries no coefficient of the EcoLogits methodology: it only
 * reads the results already exposed by Footprint and FootprintFull, never the private constants
 * of the two calculators — the breakdown therefore remains valid even if the PUE of one of the
 * two calculators were to diverge from the other.
 */
final class DifferenceCalculator
{
    public function calculate(Footprint $simplified, FootprintFull $full): ModelsDifference
    {
        // serverEnergyWh and (gpuCards * gpuEnergyPerCardWh) are the two terms added together
        // before the PUE is applied in the full model (see FootprintCalculatorFull::calculate);
        // their sum, once the PUE is applied, gives totalEnergyWh. Since the PUE multiplies both
        // terms uniformly, the share of totalEnergyWh attributable to the server term is
        // therefore this same fraction — no need to know the PUE itself to isolate it.
        $sumBeforePueWh = $full->serverEnergyWh + $full->gpuCards * $full->gpuEnergyPerCardWh;
        $serverDifferenceWh = $full->totalEnergyWh * ($full->serverEnergyWh / $sumBeforePueWh);

        $totalDifferenceWh = $full->totalEnergyWh - $simplified->totalEnergyWh;
        $cardsDifferenceWh = $totalDifferenceWh - $serverDifferenceWh;

        // The multiplier (full / simplified) and the difference percentage ((full - simplified)
        // / simplified) are NOT the same number: a difference of +449.5% corresponds to a
        // multiplier of 5.495 (= 1 + 449.5/100), not 4.495.
        return new ModelsDifference(
            $totalDifferenceWh,
            ($totalDifferenceWh / $simplified->totalEnergyWh) * 100,
            $full->totalEnergyWh / $simplified->totalEnergyWh,
            $serverDifferenceWh,
            ($serverDifferenceWh / $simplified->totalEnergyWh) * 100,
            $cardsDifferenceWh,
            ($cardsDifferenceWh / $simplified->totalEnergyWh) * 100,
        );
    }
}
