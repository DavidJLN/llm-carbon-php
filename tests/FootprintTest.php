<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use LlmCarbon\Footprint;
use PHPUnit\Framework\TestCase;

final class FootprintTest extends TestCase
{
    public function testConstruitUneEmpreinteAvecSesTroisValeurs(): void
    {
        $empreinte = new Footprint(0.007667, 4.6002, 0.3740);

        self::assertEqualsWithDelta(0.007667, $empreinte->energieParTokenWh, 0.0000001);
        self::assertEqualsWithDelta(4.6002, $empreinte->energieTotaleWh, 0.0001);
        self::assertEqualsWithDelta(0.3740, $empreinte->emissionsGco2eq, 0.0001);
    }
}
