<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Difference between the FULL model and the SIMPLIFIED model for the same scenario, broken down
 * into two parts that add up exactly to the total difference (no residual):
 * - serverDifferenceWh/Percent: the part attributable to the non-GPU server energy, a term
 *   entirely absent from the simplified model;
 * - cardsDifferenceWh/Percent: the part attributable to counting the GPU energy as many times as
 *   there are cards, rather than once as the simplified model implicitly does.
 * The percentages are expressed relative to the total energy of the SIMPLIFIED model (same
 * denominator as totalDifferencePercent), so that
 * serverDifferencePercent + cardsDifferencePercent = totalDifferencePercent exactly.
 *
 * differenceMultiplier is the actual multiplicative factor between the two total energies
 * (totalEnergyWh of the full model / totalEnergyWh of the simplified model) — NOT the same
 * number as totalDifferencePercent: "+449.5%" means "multiplied by 5.495", not "multiplied by
 * 4.495". This field only makes sense for the TOTAL difference; there is no equivalent
 * multiplier for the server/cards parts taken in isolation (they are contributions to the
 * difference, not quantities that are multiplied by one another).
 */
final class ModelsDifference
{
    public function __construct(
        public readonly float $totalDifferenceWh,
        public readonly float $totalDifferencePercent,
        public readonly float $differenceMultiplier,
        public readonly float $serverDifferenceWh,
        public readonly float $serverDifferencePercent,
        public readonly float $cardsDifferenceWh,
        public readonly float $cardsDifferencePercent,
    ) {
    }
}
