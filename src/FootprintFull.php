<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Result of the footprint calculation for a request according to EcoLogits' FULL model: unlike
 * Footprint (simplified model), it also exposes the intermediate quantities (memory, GPU cards,
 * duration) that determine the number of GPU cards required and therefore the non-GPU server
 * energy.
 */
final class FootprintFull
{
    public function __construct(
        public readonly float $requiredMemoryGb,
        public readonly int $gpuCards,
        public readonly float $durationSeconds,
        public readonly float $serverEnergyWh,
        public readonly float $gpuEnergyPerCardWh,
        public readonly float $totalEnergyWh,
        public readonly float $emissionsGco2eq,
    ) {
    }
}
