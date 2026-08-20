<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use LlmCarbon\EmissionFactor;
use PHPUnit\Framework\TestCase;

final class EmissionFactorTest extends TestCase
{
    public function testFrance(): void
    {
        $facteur = EmissionFactor::france();

        self::assertSame('France', $facteur->zone);
        self::assertEqualsWithDelta(81.3, $facteur->gCo2eqParKwh, 0.0001);
    }

    public function testEurope(): void
    {
        $facteur = EmissionFactor::europe();

        self::assertSame('Europe', $facteur->zone);
        self::assertEqualsWithDelta(509.4, $facteur->gCo2eqParKwh, 0.0001);
    }

    public function testEtatsUnis(): void
    {
        $facteur = EmissionFactor::etatsUnis();

        self::assertSame('États-Unis', $facteur->zone);
        self::assertEqualsWithDelta(679.8, $facteur->gCo2eqParKwh, 0.0001);
    }

    public function testMonde(): void
    {
        $facteur = EmissionFactor::monde();

        self::assertSame('Monde', $facteur->zone);
        self::assertEqualsWithDelta(590.4, $facteur->gCo2eqParKwh, 0.0001);
    }

    public function testToutesRetourneLesQuatreZones(): void
    {
        $facteurs = EmissionFactor::toutes();

        self::assertCount(4, $facteurs);

        $zones = array_map(static fn (EmissionFactor $facteur): string => $facteur->zone, $facteurs);
        self::assertSame(['France', 'Europe', 'États-Unis', 'Monde'], $zones);
    }

    public function testChaqueFacteurDeToutesPorteUneSourceNonVide(): void
    {
        foreach (EmissionFactor::toutes() as $facteur) {
            self::assertNotSame(
                '',
                trim($facteur->urlSource),
                sprintf("Le facteur d'émission « %s » doit citer une source.", $facteur->zone)
            );
        }
    }
}
