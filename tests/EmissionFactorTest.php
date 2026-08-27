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
        $facteur = EmissionFactor::france();

        self::assertSame('France', $facteur->zone);
        self::assertEqualsWithDelta(81.3, $facteur->gCo2eqParKwh, 0.0001);
        self::assertSame(ProvenanceType::MesureeEtPubliee, $facteur->provenance->type);
    }

    public function testFranceEstDoublementAttribueEcoLogitsEtAdeme(): void
    {
        $facteur = EmissionFactor::france();

        self::assertStringContainsString(
            'github.com/mlco2/ecologits',
            $facteur->provenance->url,
            "L'URL de provenance du facteur français doit pointer vers EcoLogits."
        );
        self::assertStringContainsString(
            '0.4.0',
            $facteur->provenance->millesimeOuDateDeConsultation,
            'Le millésime du facteur français doit citer la version 0.4.0 d\'EcoLogits.'
        );
        self::assertStringContainsString(
            'EcoLogits',
            $facteur->provenance->note,
            'La note du facteur français doit mentionner EcoLogits.'
        );
        self::assertStringContainsString(
            'ADEME',
            $facteur->provenance->note,
            "La note du facteur français doit aussi mentionner l'ADEME (double attribution)."
        );
    }

    public function testEurope(): void
    {
        $facteur = EmissionFactor::europe();

        self::assertSame('Europe', $facteur->zone);
        self::assertEqualsWithDelta(509.4, $facteur->gCo2eqParKwh, 0.0001);
        self::assertSame(ProvenanceType::MesureeEtPubliee, $facteur->provenance->type);
    }

    public function testEtatsUnis(): void
    {
        $facteur = EmissionFactor::etatsUnis();

        self::assertSame('États-Unis', $facteur->zone);
        self::assertEqualsWithDelta(679.8, $facteur->gCo2eqParKwh, 0.0001);
        self::assertSame(ProvenanceType::MesureeEtPubliee, $facteur->provenance->type);
    }

    public function testMonde(): void
    {
        $facteur = EmissionFactor::monde();

        self::assertSame('Monde', $facteur->zone);
        self::assertEqualsWithDelta(590.5, $facteur->gCo2eqParKwh, 0.0001);
        self::assertSame(ProvenanceType::MesureeEtPubliee, $facteur->provenance->type);
    }

    public function testToutesRetourneLesQuatreZones(): void
    {
        $facteurs = EmissionFactor::toutes();

        self::assertCount(4, $facteurs);

        $zones = array_map(static fn (EmissionFactor $facteur): string => $facteur->zone, $facteurs);
        self::assertSame(['France', 'Europe', 'États-Unis', 'Monde'], $zones);
    }

    public function testChaqueFacteurDeToutesPorteUneProvenanceNonVide(): void
    {
        foreach (EmissionFactor::toutes() as $facteur) {
            self::assertNotSame(
                '',
                trim($facteur->provenance->url),
                sprintf("Le facteur d'émission « %s » doit citer une provenance.", $facteur->zone)
            );
        }
    }
}
