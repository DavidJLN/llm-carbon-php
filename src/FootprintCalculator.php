<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Implémente la méthodologie EcoLogits d'estimation de l'énergie et des émissions d'une requête
 * LLM. Seule cette classe porte les coefficients de la méthodologie.
 */
final class FootprintCalculator
{
    /**
     * Coefficient multiplicatif (pente) reliant les paramètres actifs (en milliards) à l'énergie
     * GPU consommée par token généré, en Wh.
     * En notation décimale, 8.91e-5 vaut 0,0000891.
     * Source : https://ecologits.ai/latest/methodology/energy/
     */
    private const ECOLOGITS_ENERGIE_ALPHA = 8.91e-5;

    /**
     * Terme constant (ordonnée à l'origine) de l'énergie GPU consommée par token généré, en Wh.
     * En notation décimale, 1.43e-3 vaut 0,00143.
     * Source : https://ecologits.ai/latest/methodology/energy/
     */
    private const ECOLOGITS_ENERGIE_BETA = 1.43e-3;

    /**
     * PUE (Power Usage Effectiveness) moyen retenu par EcoLogits pour les centres de données des
     * fournisseurs de services d'IA.
     * Source : https://ecologits.ai/latest/methodology/energy/
     */
    private const PUE_DATACENTER = 1.2;

    public function calculate(LanguageModel $languageModel, EmissionFactor $emissionFactor, int $tokensGeneres): Footprint
    {
        $energieParTokenWh = self::ECOLOGITS_ENERGIE_ALPHA * $languageModel->parametresActifsMilliards
            + self::ECOLOGITS_ENERGIE_BETA;

        $energieTotaleWh = $energieParTokenWh * $tokensGeneres * self::PUE_DATACENTER;

        $emissionsGco2eq = ($energieTotaleWh / 1000) * $emissionFactor->gCo2eqParKwh;

        return new Footprint($energieParTokenWh, $energieTotaleWh, $emissionsGco2eq);
    }
}
