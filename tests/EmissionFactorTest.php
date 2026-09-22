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

    public function testFranceNoteMatchesExactlyWhatTheSourceStates(): void
    {
        // public/index.php affiche cette note telle quelle dans le pied de page : un utilisateur
        // qui vérifie la source du facteur français doit lire exactement ce que les sources
        // affirment, pas un texte tronqué ou réordonné par une régression silencieuse.
        self::assertSame(
            'EcoLogits v0.4.0, fichier electricity_mixes.csv, ligne « FRA » : gwp = 0,0813225 '
            . 'kgCO2eq/kWh, soit 81,3 gCO2eq/kWh une fois converti et arrondi à une décimale '
            . '(précision retenue ici). Double attribution : cette même valeur est aussi publiée '
            . "par la Base Empreinte de l'ADEME (https://base-empreinte.ademe.fr/), sans que ce "
            . 'projet ait pu isoler sur cette base un millésime précis ni la formulation exacte du '
            . 'lien entre les deux sources.',
            EmissionFactor::france()->provenance->note
        );
    }

    public function testEurope(): void
    {
        $factor = EmissionFactor::europe();

        self::assertSame('Europe', $factor->zone);
        self::assertEqualsWithDelta(509.4, $factor->gCo2eqPerKwh, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $factor->provenance->type);
    }

    public function testEuropeNoteMatchesExactlyWhatTheSourceStates(): void
    {
        // Idem France : un utilisateur qui vérifie la source du facteur européen doit lire
        // exactement la ligne « EEE » citée par Boavizta, pas un texte altéré.
        self::assertSame(
            'Jeu de données électrique de Boavizta, ligne « Climate change », colonne « EEE » : '
            . '0,509427 kgCO2eq/kWh, elle-même sourcée ADEME Base IMPACTS® (données 2011).',
            EmissionFactor::europe()->provenance->note
        );
    }

    public function testUnitedStates(): void
    {
        $factor = EmissionFactor::unitedStates();

        self::assertSame('États-Unis', $factor->zone);
        self::assertEqualsWithDelta(679.8, $factor->gCo2eqPerKwh, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $factor->provenance->type);
    }

    public function testUnitedStatesNoteMatchesExactlyWhatTheSourceStates(): void
    {
        // Idem France : un utilisateur qui vérifie la source du facteur américain doit lire
        // exactement la ligne « USA » citée par Boavizta, pas un texte altéré.
        self::assertSame(
            'Jeu de données électrique de Boavizta, ligne « Climate change », colonne « USA » : '
            . '0,67978 kgCO2eq/kWh, elle-même sourcée ADEME Base IMPACTS® (données 2011).',
            EmissionFactor::unitedStates()->provenance->note
        );
    }

    public function testWorld(): void
    {
        $factor = EmissionFactor::world();

        self::assertSame('Monde', $factor->zone);
        self::assertEqualsWithDelta(590.5, $factor->gCo2eqPerKwh, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $factor->provenance->type);
    }

    public function testWorldNoteMatchesExactlyWhatTheSourceStates(): void
    {
        // Idem France : un utilisateur qui vérifie la source du facteur mondial doit lire
        // exactement la ligne « WOR » citée par Boavizta, pas un texte altéré.
        self::assertSame(
            'Jeu de données électrique de Boavizta, ligne « Climate change », colonne « WOR » : '
            . '0,590478 kgCO2eq/kWh, elle-même sourcée ADEME Base IMPACTS® (données 2011).',
            EmissionFactor::world()->provenance->note
        );
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
