# llm-carbon-php

[![Tests](https://github.com/DavidJLN/llm-carbon-php/actions/workflows/tests.yml/badge.svg)](https://github.com/DavidJLN/llm-carbon-php/actions/workflows/tests.yml)

Small PHP script that estimates the energy consumed and the CO2eq
emissions of a request to a language model (LLM), from hardcoded input
values (model, number of active parameters, number of generated tokens).

## Usage

```bash
composer install
php -S localhost:8000 -t public
```

Then open http://localhost:8000 in a browser. `composer install` does not download any dependency: it only generates the PSR-4 autoloader for the `LlmCarbon\` namespace.

## Methodology

The calculation follows the [EcoLogits
v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md)
methodology, which offers two levels of modeling. Both coexist in this
repository (`FootprintCalculatorSimplified` and `FootprintCalculatorFull`)
so their results can be compared.

**Simplified model** (`src/FootprintCalculatorSimplified.php`):

1. **GPU energy per token**: linear regression between the number of
   active parameters of the model (in billions) and the energy consumed
   per generated token, `energy = α × parameters + β`.
2. **Total energy**: the energy per token is multiplied by the number of
   generated tokens, then by the PUE (Power Usage Effectiveness) of a
   hyperscale datacenter or supercomputer retained by EcoLogits v0.4.0
   (1.2), to account for ancillary energy (cooling, infrastructure) — see
   "Limitations" below: this is not a sector-wide average.
3. **CO2eq emissions**: the total energy (converted to kWh) is multiplied
   by the emission factor of the electricity mix of the datacenter's
   hosting zone.

**Complete model** (`src/FootprintCalculatorFull.php`): reuses the same
regression for GPU energy, but integrates it into a more detailed
calculation that also accounts for the hardware actually required to host
the model:

1. **Required memory** from the model's TOTAL parameters (not just the
   active parameters) and a quantization hypothesis (4 bits by default,
   for lack of publication by the provider), with a ×1.2 overhead.
2. **Number of GPU cards** required to load the model (required memory
   divided by the memory of a reference card — 80 GB — rounded up).
3. **Generation duration**, a regression on the active parameters.
4. **Non-GPU server energy**, proportional to the duration and the number
   of cards used (out of the 8 cards on the reference server).
5. **GPU energy**, as in the simplified model but multiplied by the number
   of cards required.
6. **Total energy and emissions**, as in the simplified model, from the
   sum of the two previous energies.

The complete model gives a higher result than the simplified model as soon
as a model requires several GPU cards (GPU energy is then counted several
times); for a dense model that fits on a single card, the gap comes only
from the non-GPU server energy, absent from the simplified model.
`src/DifferenceCalculator.php` breaks down this gap into two parts that add up
exactly (no residual): the "server" part (the term missing from the
simplified model) and the "cards" part (the extra amount due to counting
GPU energy several times). The page displays both results side by side as
well as the total gap and its breakdown.

The detailed figures for each step of both models are displayed on the
page, below the results, along with two comparison tables with all other
parameters held equal: by hosting zone (France, Europe, United States,
World) and by catalog model (Llama 3.1 70B, GPT-4, GPT-4o,
Qwen3-235B-A22B) — this second table also shows the number of GPU cards
required and the gap (total, of which server, of which cards) per model.
The French emission factor has a double attribution: [ADEME Base
Empreinte](https://base-empreinte.ademe.fr/) and [EcoLogits v0.4.0
electricity_mixes.csv](https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/data/electricity_mixes.csv),
which publish the same value. The other three zones come from the
[Boavizta electricity
dataset](https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv),
sourced from ADEME Base IMPACTS® (2011 data).

## Value provenance

Each `EmissionFactor` carries a `Provenance` (`src/Provenance.php`), and
each `LanguageModel` carries two — one for its active parameters, one for
its total parameters, which can have different origins (see GPT-4 below).
A `Provenance` is mandatory at construction: type (`MeasuredAndPublished` or
`Hypothesis`), source URL, year or consultation date, and a note stating
exactly what the source states. It is impossible to create either of
these two classes without a complete provenance — the constructor
requires it. For a dense model (active parameters = total parameters,
like Llama 3.1 70B), the `LanguageModel::dense()` factory states this
equality once instead of repeating the value and its provenance.

Most catalog values are measured and published by their source (green
badge "✓ Measured and published"), including **Qwen3-235B-A22B**
(Alibaba), an open model for which the Qwen team officially publishes the
235 billion total parameters and the 22 billion parameters activated per
token. The two exceptions concern OpenAI models (orange badge "⚠
Hypothesis"), whose architecture is never published:

- **GPT-4** — active parameters (176 billion retained): follows the
  EcoLogits method for proprietary models — from a leaked
  Mixture-of-Experts architecture (~1.8 trillion total parameters) and a
  typical MoE activation ratio of 10% to 30%, EcoLogits estimates a range
  of 176 to 528 billion active parameters (exactly x3); this project
  retains the lower bound, the most conservative one. The upper bound
  would multiply the energy per token by about 2.8 in the EcoLogits
  regression alone — and the **total** energy of the complete model, only
  by about 2.81 (see the warning below). Total parameters (1,800 billion
  retained): the leak mentioned above bears directly on this figure; no
  separate published upper bound exists.
- **GPT-4o** — active parameters (44 billion retained) and total (440
  billion): EcoLogits estimates this model at exactly one quarter of the
  parameters it retains for GPT-4 (1760/4, 176/4, 528/4), without
  publishing independent reasoning for this precise ratio beyond its own
  dataset. This project also retains the lower bound (44 billion) for
  active parameters; the upper bound (132 billion, exactly x3) multiplies
  the energy per token from the regression by about 2.47, and the
  **total** energy of the complete model by only about 2.40.

**An input range is not an output range.** The two examples above (x3 in
active parameters → x2.8/x2.5 on the regression alone → x2.81/x2.40 on the
total energy of the complete model) illustrate that these three ratios
never coincide: the EcoLogits regression is affine (it has a constant term
β that does not vary with the parameters, so nothing in it is strictly
proportional), and the complete model combines two of them (duration, GPU
energy) under the same PUE, which produces yet a third ratio. See the
docblock of `src/FootprintCalculatorFull.php` and the dedicated tests
in `tests/FootprintCalculatorFullTest.php`.

See [EcoLogits methodology for proprietary
models](https://ecologits.ai/latest/methodology/proprietary_models/) and
the [EcoLogits 0.11.1 models
dataset](https://github.com/mlco2/ecologits/blob/0.11.1/ecologits/data/models.json).

## Limitations

- **Hardcoded input values**: the model, the number of parameters, and
  the number of tokens are not dynamically configurable.
- **Estimate, not a measurement**: the EcoLogits regression is an
  empirical approximation, not a direct measurement of the actual GPU
  power draw for a given request.
- **GPU energy only (simplified model) or GPU + non-GPU server (complete
  model)**: in both cases, the calculation does not cover energy related
  to storage, network, or model training — only inference is estimated.
- **Assumed, not published, quantization**: the complete model assumes a
  default quantization of 4 bits per parameter (for lack of information
  published by providers on the quantization actually used in
  production), which determines the required GPU memory and therefore the
  number of cards.
- **A single PUE applied to all models, not a sector-wide average**: 1.2
  is the value retained by EcoLogits v0.4.0 without breaking it down by
  provider; more recent versions of EcoLogits (not used in this project)
  show that 1.2 corresponds specifically to OpenAI/Azure (≈1.09 for
  Anthropic/Cohere/Google, 1.09-1.14 for HuggingFace, 1.16 for Mistral).
  This project therefore applies 1.2 uniformly, including to Llama 3.1
  70B, which is not hosted by OpenAI.
- **Hosting zone not automatically detected**: the table compares several
  zones (France, Europe, United States, World), but does not know the
  actual location of the datacenter that processed the request.
- **No account taken of context** (input tokens, prompt size), only the
  number of generated tokens is used.
- **GPT-4 and GPT-4o parameters not published**: these values are
  hypotheses (see "Value provenance" above), not measurements — the
  results displayed for these two models inherit this uncertainty.
- **The complete model should not appear more reliable than its inputs**:
  the number of GPU cards (`gpuCards`) is determined **solely** by the
  TOTAL parameters and the assumed quantization — no other data
  corroborates it. For GPT-4o, it is therefore the 440 billion *assumed*
  total parameters (a hypothesis, not a measurement) that alone determine
  this number of cards, and by extension a large part of the complete
  model's total energy. The page reflects this visually: `gpuCards`, the
  energy, and the emissions of the complete model carry the "⚠
  Hypothesis" badge as soon as either of their two inputs (active or
  total) is one — they never carry the "✓ Measured and published" badge
  if the simplified model, on the same row, does not already carry it
  itself.
