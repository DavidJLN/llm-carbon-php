<?php

declare(strict_types=1);

use LlmCarbon\Rector\SimplifiedToFullCalculatorRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([SimplifiedToFullCalculatorRector::class]);
