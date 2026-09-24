<?php

use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorSimplified;
use LlmCarbon\LanguageModel;

function totalWh(FootprintCalculatorSimplified $calculator): float
{
    return $calculator->calculate(LanguageModel::llama31_70b(), EmissionFactor::france(), 500)->totalEnergyWh;
}
