<?php

declare(strict_types=1);

use LlmCarbon\Rector\SimplifiedToFullCalculatorRector;
use Rector\Config\RectorConfig;

// Loaded automatically by rector/extension-installer in any project that requires this package
// (composer.json: "type": "rector-extension", extra.rector.includes). No rector.php needed.
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(SimplifiedToFullCalculatorRector::class);
};
