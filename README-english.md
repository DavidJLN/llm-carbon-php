# llm-carbon-php — usage as a package

This document describes `davidjln/llm-carbon-php` as a **Composer package to
install in your own code**. For the standalone web demo of this repository
(HTML page displaying a hardcoded scenario), see
[README-demo-english.md](README-demo-english.md).

## What this package does, and for whom

This package calculates the energy consumed and the CO2eq emissions of an
inference request to a language model (LLM), from three inputs: a model
(number of parameters), an emission factor (geographic hosting zone of the
datacenter), and a number of generated tokens. The calculation follows the
[EcoLogits v0.4.0](https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md)
methodology.

It targets PHP developers who want to **integrate a carbon estimate into
their own application** (dashboard, logging, reporting) rather than using
the demonstration page provided by this repository — for example to
compute, for each LLM call made by their application, the energy and
emissions associated with that specific call.

## Installation

Minimum PHP version required: **8.4** (the package uses `readonly`
properties).

```bash
composer require davidjln/llm-carbon-php
```

This package has no runtime dependency: `composer require` only installs
the package itself.

## Minimal usage

```php
<?php

require 'vendor/autoload.php';

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorSimplified;
use LlmCarbon\LanguageModel;

$footprint = (new FootprintCalculatorSimplified())->calculate(
    LanguageModel::llama31_70b(),
    EmissionFactor::france(),
    500, // number of tokens generated in the response
);

echo $footprint->emissionsGco2eq, ' gCO2eq';
```

`FootprintCalculatorSimplified::calculate()` returns a `Footprint`
(`src/Footprint.php`) exposing three values: `energyPerTokenWh`,
`totalEnergyWh`, and `emissionsGco2eq`. `LanguageModel` and
`EmissionFactor` each expose one factory per catalog value (see `all()`
on each class for the full list: models `llama31_70b()`, `gpt4()`,
`gpt4o()`, `qwen3_235b_a22b()`; zones `france()`, `europe()`,
`unitedStates()`, `world()`).

A second implementation, `FootprintCalculatorFull`, has the same
`calculate()` signature and additionally accounts for the GPU memory
required and the number of cards needed to load the model; see
[README-demo-english.md](README-demo-english.md#methodology) for details on
the difference between the two.

## What the calculation covers

The scope is strictly limited to **inference**, based solely on the
**number of tokens generated in output**:

- the GPU energy consumed to generate the response tokens (EcoLogits
  regression on the model's active parameters);
- with `FootprintCalculatorFull` only, the non-GPU server energy
  associated with that same generation;
- the conversion of this energy into CO2eq emissions, via the emission
  factor of the electricity mix of the chosen zone.

## What the calculation does not cover

- **Input tokens**: the prompt sent to the model is not part of the
  calculation in any way; only the number of tokens *generated* is used.
- **Model training**: the energy and emissions related to training (or
  fine-tuning) are not counted — only inference is.
- **Hardware manufacturing**: emissions related to the manufacturing of
  GPUs and servers ("embodied" impact, upstream of their entry into
  service) are not counted — only the energy consumed while executing the
  request is.
- **Storage and network**: neither the energy for storing the model
  weights nor that of the network transport of the request or response is
  counted.
- **Uncertainty is not a statistical confidence interval**: each input
  value cites its `Provenance` (measured and published by the model
  provider, or a reconstructed hypothesis for lack of publication — see
  `src/ProvenanceType.php`) and the result that depends on it inherits
  this status, but the package does not compute any margin of error or
  output range: for the two proprietary models in the catalog (GPT-4,
  GPT-4o), whose parameters are not published, a conservative hypothesis
  (lower bound) is retained rather than a range.

## Sources and years

- **GPU energy regression (α, β) and datacenter PUE (1.2)**: [EcoLogits
  v0.4.0
  methodology](https://github.com/mlco2/ecologits/blob/0.4.0/docs/methodology/llm_inference.md)
  and [exact
  values](https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/impacts/llm.py)
  — version 0.4.0.
- **France emission factor** (81.3 gCO2eq/kWh): [EcoLogits v0.4.0
  electricity_mixes.csv](https://github.com/mlco2/ecologits/blob/0.4.0/ecologits/data/electricity_mixes.csv),
  the same value is also published by the [ADEME Base
  Empreinte](https://base-empreinte.ademe.fr/).
- **Europe, United States, World emission factors**: [Boavizta electricity
  dataset](https://github.com/Boavizta/boaviztapi/blob/main/boaviztapi/data/crowdsourcing/electrical_mix.csv),
  2011 data (sourced from ADEME Base IMPACTS®).
- **Llama 3.1 70B** (70 billion parameters, dense): [official Meta
  announcement, 2024-07-23](https://ai.meta.com/blog/meta-llama-3-1/).
- **Qwen3-235B-A22B** (235 billion total parameters, 22 billion
  activated): [official Qwen3 announcement,
  2025-04-29](https://qwenlm.github.io/blog/qwen3/).
- **GPT-4 and GPT-4o** (parameters not published by OpenAI, values typed
  `Hypothesis`): [EcoLogits methodology for proprietary
  models](https://ecologits.ai/latest/methodology/proprietary_models/) and
  [EcoLogits 0.11.1 models
  dataset](https://github.com/mlco2/ecologits/blob/0.11.1/ecologits/data/models.json).

The full detail of each source (URL, year, exactly what it states) is
accessible from the code via `LanguageModel::$provenance` /
`$totalParametersProvenance` and `EmissionFactor::$provenance` — see
[README-demo-english.md](README-demo-english.md#value-provenance) for the
summary and detailed limitations.
