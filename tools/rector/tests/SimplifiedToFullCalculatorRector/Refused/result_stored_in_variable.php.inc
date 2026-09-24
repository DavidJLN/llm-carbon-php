<?php

use LlmCarbon\DifferenceCalculator;
use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorFull;
use LlmCarbon\FootprintCalculatorSimplified;
use LlmCarbon\LanguageModel;

$model = LanguageModel::llama31_70b();
$zone = EmissionFactor::france();

$simplified = new FootprintCalculatorSimplified();
$footprint = $simplified->calculate($model, $zone, 500);
$difference = (new DifferenceCalculator())->calculate($footprint, (new FootprintCalculatorFull())->calculate($model, $zone, 500));
