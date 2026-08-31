<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Result of the footprint calculation for a request: associated energy and emissions.
 */
final class Footprint
{
    public function __construct(
        public readonly float $energyPerTokenWh,
        public readonly float $totalEnergyWh,
        public readonly float $emissionsGco2eq,
    ) {
    }
}
