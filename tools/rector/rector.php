<?php

declare(strict_types=1);

use LlmCarbon\Rector\SimplifiedToFullCalculatorRector;
use Rector\Config\RectorConfig;

// Usage, from tools/rector/: vendor/bin/rector process ../../public --dry-run
return RectorConfig::configure()
    ->withRules([SimplifiedToFullCalculatorRector::class]);
