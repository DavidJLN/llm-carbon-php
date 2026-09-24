<?php

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorSimplified;
use LlmCarbon\LanguageModel;

$calculator = new FootprintCalculatorSimplified();
$whPerToken = $calculator->calculate(LanguageModel::llama31_70b(), EmissionFactor::france(), 500)->energyPerTokenWh;
