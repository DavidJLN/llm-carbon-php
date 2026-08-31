<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use LlmCarbon\EmissionFactor;
use LlmCarbon\ProvenanceType;
use PHPUnit\Framework\TestCase;

final class EmissionFactorTest extends TestCase
{
    public function testFrance(): void
    {
        $factor = EmissionFactor::france();

        self::assertSame('France', $factor->zone);
        self::assertEqualsWithDelta(81.3, $factor->gCo2eqPerKwh, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $factor->provenance->type);
    }

    public function testFranceIsDoublyAttributedToEcoLogitsAndAdeme(): void
    {
        $factor = EmissionFactor::france();

        self::assertStringContainsString(
            'github.com/mlco2/ecologits',
            $factor->provenance->url,
            "L'URL de provenance du facteur français doit pointer vers EcoLogits."
        );
        self::assertStringContainsString(
            '0.4.0',
            $factor->provenance->yearOrConsultationDate,
            'Le millésime du facteur français doit citer la version 0.4.0 d\'EcoLogits.'
        );
        self::assertStringContainsString(
            'EcoLogits',
            $factor->provenance->note,
            'La note du facteur français doit mentionner EcoLogits.'
        );
        self::assertStringContainsString(
            'ADEME',
            $factor->provenance->note,
            "La note du facteur français doit aussi mentionner l'ADEME (double attribution)."
        );
    }

    public function testEurope(): void
    {
        $factor = EmissionFactor::europe();

        self::assertSame('Europe', $factor->zone);
        self::assertEqualsWithDelta(509.4, $factor->gCo2eqPerKwh, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $factor->provenance->type);
    }

    public function testUnitedStates(): void
    {
        $factor = EmissionFactor::unitedStates();

        self::assertSame('États-Unis', $factor->zone);
        self::assertEqualsWithDelta(679.8, $factor->gCo2eqPerKwh, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $factor->provenance->type);
    }

    public function testWorld(): void
    {
        $factor = EmissionFactor::world();

        self::assertSame('Monde', $factor->zone);
        self::assertEqualsWithDelta(590.5, $factor->gCo2eqPerKwh, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $factor->provenance->type);
    }

    public function testAllReturnsTheFourZones(): void
    {
        $factors = EmissionFactor::all();

        self::assertCount(4, $factors);

        $zones = array_map(static fn (EmissionFactor $factor): string => $factor->zone, $factors);
        self::assertSame(['France', 'Europe', 'États-Unis', 'Monde'], $zones);
    }

    public function testEachFactorFromAllCarriesANonEmptyProvenance(): void
    {
        foreach (EmissionFactor::all() as $factor) {
            self::assertNotSame(
                '',
                trim($factor->provenance->url),
                sprintf("Le facteur d'émission « %s » doit citer une provenance.", $factor->zone)
            );
        }
    }
}
