<?php

$calculator = new \LlmCarbon\FootprintCalculatorSimplified();
$wh = $calculator->calculate(\LlmCarbon\LanguageModel::llama31_70b(), \LlmCarbon\EmissionFactor::france(), 500)->totalEnergyWh;

?>
-----
<?php

$calculator = new \LlmCarbon\FootprintCalculatorFull();
$wh = $calculator->calculate(\LlmCarbon\LanguageModel::llama31_70b(), \LlmCarbon\EmissionFactor::france(), 500)->totalEnergyWh;

?>
