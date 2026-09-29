<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

// The tests run the config shipped to users, not a copy of it.
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->import(__DIR__ . '/../../../config/config.php');
};
