<?php

declare(strict_types=1);

namespace LlmCarbon;

use InvalidArgumentException;

/**
 * Implements the SIMPLIFIED model of the EcoLogits methodology (linear regression on active
 * parameters alone): the GPU energy per token is estimated directly, without going through the
 * required memory, the number of GPU cards, or the non-GPU server energy. See
 * FootprintCalculatorFull for the full model of which this simplified model is a special case
 * (it corresponds to the latter's energie_gpu term, multiplied by the PUE, without accounting
 * for the number of cards). Only this class carries the coefficients of the simplified model.
 */
final class FootprintCalculatorSimplified
{
    /**
     * Multiplicative coefficient (slope) relating the active parameters (in billions) to the GPU
     * energy consumed per generated token, in Wh.
     * In decimal notation, 8.91e-5 equals 0.0000891.
     * Source: https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md
     * (formula E_GPU/#T_out = alpha * P_active + beta) and
     * https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/impacts/llm.py (exact values).
     */
    private const ECOLOGITS_ENERGY_ALPHA_WH_PER_BILLION = 8.91e-5;

    /**
     * Constant term (y-intercept) of the GPU energy consumed per generated token, in Wh.
     * In decimal notation, 1.43e-3 equals 0.00143.
     * Source: https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md
     */
    private const ECOLOGITS_ENERGY_BETA_WH = 1.43e-3;

    /**
     * PUE (Power Usage Effectiveness) retained by EcoLogits v0.4.0 for a hyperscale datacenter or
     * a supercomputer. Unitless (ratio of the datacenter's total energy to the energy of the IT
     * equipment alone).
     * THIS IS NOT A SECTOR-WIDE AVERAGE: in v0.4.0 (the version cited here), EcoLogits does not
     * break this value down by provider — it is presented as-is. More recent versions of
     * EcoLogits (not used in this project) break it down and show that 1.2 corresponds
     * specifically to OpenAI/Azure (≈1.09 for Anthropic/Cohere/Google, 1.09-1.14 for HuggingFace,
     * 1.16 for Mistral): this project therefore applies this value uniformly, including to
     * models not hosted by OpenAI (e.g. Llama 3.1 70B).
     * Source: https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md
     * ("We typically use a PUE = 1.2 for hyperscaler data centers or supercomputers.")
     */
    private const PUE_DATACENTER = 1.2;

    public function calculate(LanguageModel $languageModel, EmissionFactor $emissionFactor, int $generatedTokens): Footprint
    {
        if ($generatedTokens <= 0) {
            throw new InvalidArgumentException('Le nombre de tokens générés doit être strictement positif.');
        }

        $energyPerTokenWh = self::ECOLOGITS_ENERGY_ALPHA_WH_PER_BILLION * $languageModel->activeParametersBillions
            + self::ECOLOGITS_ENERGY_BETA_WH;

        $totalEnergyWh = $energyPerTokenWh * $generatedTokens * self::PUE_DATACENTER;

        $emissionsGco2eq = ($totalEnergyWh / 1000) * $emissionFactor->gCo2eqPerKwh;

        return new Footprint($energyPerTokenWh, $totalEnergyWh, $emissionsGco2eq);
    }
}
