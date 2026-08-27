<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Résultat du calcul d'empreinte pour une requête selon le modèle COMPLET d'EcoLogits : contrairement
 * à Footprint (modèle simplifié), il expose aussi les grandeurs intermédiaires (mémoire, cartes GPU,
 * durée) qui déterminent le nombre de cartes GPU nécessaires et donc l'énergie du serveur hors GPU.
 */
final class FootprintComplet
{
    public function __construct(
        public readonly float $memoireRequiseGo,
        public readonly int $cartesGpu,
        public readonly float $dureeSecondes,
        public readonly float $energieServeurWh,
        public readonly float $energieGpuParCarteWh,
        public readonly float $energieTotaleWh,
        public readonly float $emissionsGco2eq,
    ) {
    }
}
