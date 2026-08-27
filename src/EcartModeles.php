<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Écart entre le modèle COMPLET et le modèle SIMPLIFIÉ pour un même scénario, décomposé en deux
 * parts qui s'additionnent exactement à l'écart total (aucun résidu) :
 * - ecartServeurWh/Pourcent : la part imputable à l'énergie du serveur hors GPU, un terme
 *   entièrement absent du modèle simplifié ;
 * - ecartCartesWh/Pourcent : la part imputable au fait de compter l'énergie GPU autant de fois
 *   qu'il y a de cartes, plutôt qu'une seule fois comme le fait implicitement le modèle simplifié.
 * Les pourcentages sont exprimés par rapport à l'énergie totale du modèle SIMPLIFIÉ (même
 * dénominateur que ecartTotalPourcent), de sorte que
 * ecartServeurPourcent + ecartCartesPourcent = ecartTotalPourcent exactement.
 *
 * ecartMultiplicateur est le facteur multiplicatif réel entre les deux énergies totales
 * (energieTotaleWh du complet / energieTotaleWh du simplifié) — PAS le même nombre que
 * ecartTotalPourcent : « +449,5 % » veut dire « multiplié par 5,495 », pas « multiplié par 4,495 ».
 * Ce champ n'a de sens que pour l'écart TOTAL ; il n'existe pas de multiplicateur équivalent pour
 * les parts serveur/cartes prises isolément (ce sont des contributions à l'écart, pas des
 * grandeurs qu'on multiplie l'une par l'autre).
 */
final class EcartModeles
{
    public function __construct(
        public readonly float $ecartTotalWh,
        public readonly float $ecartTotalPourcent,
        public readonly float $ecartMultiplicateur,
        public readonly float $ecartServeurWh,
        public readonly float $ecartServeurPourcent,
        public readonly float $ecartCartesWh,
        public readonly float $ecartCartesPourcent,
    ) {
    }
}
