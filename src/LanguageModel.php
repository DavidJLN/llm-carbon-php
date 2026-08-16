<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Modèle de langage étudié : son nombre de paramètres actifs détermine, via
 * FootprintCalculator, l'énergie GPU consommée par token généré.
 */
final class LanguageModel
{
    public function __construct(
        public readonly string $nom,
        public readonly float $parametresActifsMilliards,
        public readonly string $urlSource,
    ) {
    }
}
