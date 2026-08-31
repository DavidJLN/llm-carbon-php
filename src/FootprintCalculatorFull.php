<?php

declare(strict_types=1);

namespace LlmCarbon;

use InvalidArgumentException;

/**
 * Implements the FULL model of the EcoLogits v0.4 methodology: unlike the SIMPLIFIED model
 * (FootprintCalculatorSimplified), it computes the GPU memory required to load the model from its
 * TOTAL parameters (hence the number of GPU cards needed), the non-GPU server energy
 * (proportional to the generation duration and the number of cards used), and adds this server
 * energy to the GPU energy (itself multiplied by the number of cards, since each card consumes
 * this energy). Only this class carries the coefficients of the full model.
 *
 * Main source (formulas and prose):
 * https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md
 * Source of the exact numeric values (Python constants of the same version):
 * https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/impacts/llm.py
 *
 * WARNING — an input range is not an output range: totalEnergyWh is NOT proportional to
 * activeParametersBillions. Two combined reasons: (1) both regressions (duration, GPU energy)
 * are affine, not linear — each has a constant term (β) that does not vary with the parameters,
 * so doubling/tripling the active parameters never exactly doubles/triples the result of a
 * regression; (2) this calculator combines TWO distinct affine regressions (duration and GPU
 * energy) under the same PUE, which produces a third ratio, different from that of each
 * regression taken in isolation. Concretely, for GPT-4o (44 to 132 billion active parameters,
 * i.e. x3 in input), the GPU energy per token alone is only multiplied by ≈2.47, and the total
 * energy of this calculator (which also includes the server term) only by ≈2.40 — neither x3 nor
 * x2.47 (see LanguageModel::gpt4o() and the tests in FootprintCalculatorFullTest that lock in
 * these two ratios).
 *
 * WARNING — this full model must never appear more reliable than its inputs, in particular
 * totalParametersBillions: gpuCards (and therefore a significant part of totalEnergyWh, both via
 * serverEnergyWh AND via the gpuCards * gpuEnergyPerCardWh factor) is determined SOLELY by
 * totalParametersBillions and the assumed quantization — no other data corroborates this number
 * of cards. For a proprietary model whose total parameters are a Hypothesis rather than a
 * measurement (e.g. GPT-4o: 440 billion assumed, see LanguageModel::gpt4o()), this single figure
 * alone therefore fixes a structural quantity (a step, via ceil()) on which the rest of the
 * calculation rests, without any other simplified model coming to contradict or corroborate it.
 * The full model must therefore never display its results with more visual confidence than the
 * simplified model when its inputs are less well sourced: see worstProvenanceBadge() in
 * public/index.php, which makes totalEnergyWh and emissionsGco2eq of the full model carry the
 * worst (i.e. least reliable) of the two provenances (active, total) they depend on, and makes
 * gpuCards carry the provenance of the total parameters it depends on exclusively.
 */
final class FootprintCalculatorFull
{
    /**
     * Memory overhead factor applied to the raw size of the model weights to estimate the GPU
     * memory actually required for inference (activation buffers, KV-cache, etc.).
     * Unitless (multiplicative factor).
     * Source: llm_inference.md, formula M_model(P_total,Q) = 1.2 * P_total * Q / 8, which itself
     * cites Transformers Math 101 (https://blog.eleuther.ai/transformer-math/#total-inference-memory).
     */
    private const MEMORY_OVERHEAD_FACTOR = 1.2;

    /**
     * Default number of bits used to represent each model parameter (quantization), in the
     * absence of information published by the provider about the quantization actually used in
     * production.
     * Unlike the other constants of this class, this value does not appear anywhere in the prose
     * of llm_inference.md (which only names the variable Q, without giving it a default value):
     * the code is the sole source here.
     * Source: https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/impacts/llm.py#L8,
     * constant MODEL_QUANTIZATION_BITS = 4 (default value of the optional
     * model_quantization_bits parameter of compute_llm_impacts_dag).
     */
    private const DEFAULT_QUANTIZATION_BITS = 4;

    /**
     * Memory available on a GPU card, in GB, for the reference inference hardware (NVIDIA A100
     * 80GB) used by EcoLogits to fit its regressions.
     * Source: llm.py, constant GPU_MEMORY = 80; llm_inference.md, "we use M_GPU = 80 GB for an
     * NVIDIA A100 80GB GPU".
     */
    private const GPU_MEMORY_GB = 80;

    /**
     * Number of GPU cards installed on the reference server used by EcoLogits (a single request
     * does not necessarily use every card of the server hosting it).
     * Source: llm.py, constant SERVER_GPUS = 8; llm_inference.md, "#GPU_installed = 8".
     */
    private const GPU_CARDS_INSTALLED_PER_SERVER = 8;

    /**
     * Power of the server excluding GPUs, in watts, for the reference server used by EcoLogits.
     * Source: llm.py, constant SERVER_POWER = 1 (in kW); llm_inference.md,
     * "we use W_server\GPU = 1 kW". Expressed here in watts (1 kW = 1000 W) to stay consistent
     * with the other constants of this class, all in Wh/W.
     */
    private const NON_GPU_SERVER_POWER_W = 1000;

    /**
     * Multiplicative coefficient (slope) relating the active parameters (in billions) to the
     * generation duration per token, in seconds.
     * Source: llm.py, constant GPU_LATENCY_ALPHA = 8.02e-4; llm_inference.md, "A = 8.02e-4".
     */
    private const LATENCY_ALPHA_S_PER_BILLION = 8.02e-4;

    /**
     * Constant term (y-intercept) of the generation duration per token, in seconds.
     * Source: llm.py, constant GPU_LATENCY_BETA = 2.23e-2; llm_inference.md, "B = 2.23e-2".
     */
    private const LATENCY_BETA_S = 2.23e-2;

    /**
     * Multiplicative coefficient (slope) relating the active parameters (in billions) to the
     * energy consumed by a single GPU card per generated token, in Wh. Identical value to that of
     * the simplified model (FootprintCalculatorSimplified::ECOLOGITS_ENERGY_ALPHA_WH_PER_BILLION):
     * it is the same regression, multiplied here by the number of GPU cards required rather than
     * used alone.
     * Source: llm_inference.md, formula E_GPU/#T_out = alpha * P_active + beta.
     */
    private const GPU_ENERGY_ALPHA_WH_PER_BILLION = 8.91e-5;

    /**
     * Constant term (y-intercept) of the energy consumed by a single GPU card per generated
     * token, in Wh.
     * Source: llm_inference.md, formula E_GPU/#T_out = alpha * P_active + beta.
     */
    private const GPU_ENERGY_BETA_WH = 1.43e-3;

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
     * Source: llm.py, constant DATACENTER_PUE = 1.2; llm_inference.md, "PUE = 1.2".
     */
    private const PUE_DATACENTER = 1.2;

    public function calculate(
        LanguageModel $languageModel,
        EmissionFactor $emissionFactor,
        int $generatedTokens
    ): FootprintFull {
        if ($generatedTokens <= 0) {
            throw new InvalidArgumentException('Le nombre de tokens générés doit être strictement positif.');
        }

        // Memory needed to load the model weights (TOTAL parameters) onto the GPU, with
        // overhead. totalParametersBillions * 1e9 = number of parameters; * bits / 8 = bytes;
        // / 1e9 = GB. The two 1e9 factors cancel out: the result is expressed directly in
        // billions of parameters.
        $requiredMemoryGb = self::MEMORY_OVERHEAD_FACTOR
            * $languageModel->totalParametersBillions
            * self::DEFAULT_QUANTIZATION_BITS
            / 8;

        $gpuCards = (int) ceil($requiredMemoryGb / self::GPU_MEMORY_GB);

        $durationSeconds = (self::LATENCY_ALPHA_S_PER_BILLION * $languageModel->activeParametersBillions
            + self::LATENCY_BETA_S) * $generatedTokens;

        // The server power is in W and the duration in seconds: we convert the duration to hours
        // (/ 3600) to obtain a result in Wh, consistent with the rest of the calculation.
        $serverEnergyWh = ($durationSeconds / 3600)
            * self::NON_GPU_SERVER_POWER_W
            * ($gpuCards / self::GPU_CARDS_INSTALLED_PER_SERVER);

        $gpuEnergyPerCardWh = (self::GPU_ENERGY_ALPHA_WH_PER_BILLION * $languageModel->activeParametersBillions
            + self::GPU_ENERGY_BETA_WH) * $generatedTokens;

        $totalEnergyWh = self::PUE_DATACENTER
            * ($serverEnergyWh + $gpuCards * $gpuEnergyPerCardWh);

        $emissionsGco2eq = ($totalEnergyWh / 1000) * $emissionFactor->gCo2eqPerKwh;

        return new FootprintFull(
            $requiredMemoryGb,
            $gpuCards,
            $durationSeconds,
            $serverEnergyWh,
            $gpuEnergyPerCardWh,
            $totalEnergyWh,
            $emissionsGco2eq,
        );
    }
}
