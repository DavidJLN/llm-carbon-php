<?php

declare(strict_types=1);

namespace LlmCarbon;

use InvalidArgumentException;

/**
 * Implémente le modèle SIMPLIFIÉ de la méthodologie EcoLogits (régression linéaire sur les seuls
 * paramètres actifs) : l'énergie GPU par token est estimée directement, sans passer par la mémoire
 * requise, le nombre de cartes GPU ni l'énergie du serveur hors GPU. Voir FootprintCalculatorComplet
 * pour le modèle complet dont ce modèle simplifié est un cas particulier (il correspond au terme
 * energie_gpu de ce dernier, multiplié par le PUE, sans tenir compte du nombre de cartes).
 * Seule cette classe porte les coefficients du modèle simplifié.
 */
final class FootprintCalculatorSimplifie
{
    /**
     * Coefficient multiplicatif (pente) reliant les paramètres actifs (en milliards) à l'énergie
     * GPU consommée par token généré, en Wh.
     * En notation décimale, 8.91e-5 vaut 0,0000891.
     * Source : https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md
     * (formule E_GPU/#T_out = alpha * P_active + beta) et
     * https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/impacts/llm.py (valeurs exactes).
     */
    private const ECOLOGITS_ENERGIE_ALPHA_WH_PAR_MILLIARD = 8.91e-5;

    /**
     * Terme constant (ordonnée à l'origine) de l'énergie GPU consommée par token généré, en Wh.
     * En notation décimale, 1.43e-3 vaut 0,00143.
     * Source : https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md
     */
    private const ECOLOGITS_ENERGIE_BETA_WH = 1.43e-3;

    /**
     * PUE (Power Usage Effectiveness) retenu par EcoLogits v0.4.0 pour un datacenter hyperscale ou
     * un supercalculateur. Sans unité (ratio énergie totale du datacenter / énergie des seuls
     * équipements IT).
     * CE N'EST PAS UNE MOYENNE SECTORIELLE : dans v0.4.0 (la version citée ici), EcoLogits ne
     * ventile pas cette valeur par fournisseur — elle est présentée telle quelle. Les versions plus
     * récentes d'EcoLogits (non retenues dans ce projet) la ventilent et montrent que 1,2
     * correspond spécifiquement à OpenAI/Azure (≈1,09 pour Anthropic/Cohere/Google, 1,09-1,14 pour
     * HuggingFace, 1,16 pour Mistral) : ce projet applique donc cette valeur de façon uniforme, y
     * compris à des modèles non hébergés par OpenAI (ex. Llama 3.1 70B).
     * Source : https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md
     * ("We typically use a PUE = 1.2 for hyperscaler data centers or supercomputers.")
     */
    private const PUE_DATACENTER = 1.2;

    public function calculate(LanguageModel $languageModel, EmissionFactor $emissionFactor, int $tokensGeneres): Footprint
    {
        if ($tokensGeneres <= 0) {
            throw new InvalidArgumentException('Le nombre de tokens générés doit être strictement positif.');
        }

        $energieParTokenWh = self::ECOLOGITS_ENERGIE_ALPHA_WH_PAR_MILLIARD * $languageModel->parametresActifsMilliards
            + self::ECOLOGITS_ENERGIE_BETA_WH;

        $energieTotaleWh = $energieParTokenWh * $tokensGeneres * self::PUE_DATACENTER;

        $emissionsGco2eq = ($energieTotaleWh / 1000) * $emissionFactor->gCo2eqParKwh;

        return new Footprint($energieParTokenWh, $energieTotaleWh, $emissionsGco2eq);
    }
}
