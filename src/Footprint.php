<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Résultat du calcul d'empreinte pour une requête : énergie et émissions associées.
 */
final class Footprint
{
    public function __construct(
        public readonly float $energieParTokenWh,
        public readonly float $energieTotaleWh,
        public readonly float $emissionsGco2eq,
    ) {
    }
}
