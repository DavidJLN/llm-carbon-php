<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use InvalidArgumentException;
use LlmCarbon\LanguageModel;
use LlmCarbon\Provenance;
use LlmCarbon\ProvenanceType;
use PHPUnit\Framework\TestCase;

final class LanguageModelTest extends TestCase
{
    private function provenanceDeTest(): Provenance
    {
        return new Provenance(ProvenanceType::MesureeEtPubliee, 'https://example.org', '2024', 'Note.');
    }

    public function testConstruitUnModeleAvecSesCinqAttributs(): void
    {
        $modele = new LanguageModel(
            'Llama 3.1 70B',
            70.0,
            $this->provenanceDeTest(),
            70.0,
            $this->provenanceDeTest(),
        );

        self::assertSame('Llama 3.1 70B', $modele->nom);
        self::assertEqualsWithDelta(70.0, $modele->parametresActifsMilliards, 0.0001);
        self::assertSame(ProvenanceType::MesureeEtPubliee, $modele->provenance->type);
        self::assertEqualsWithDelta(70.0, $modele->parametresTotauxMilliards, 0.0001);
        self::assertSame(ProvenanceType::MesureeEtPubliee, $modele->provenanceParametresTotaux->type);
    }

    public function testDesParametresActifsNulsLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 0.0, $this->provenanceDeTest(), 10.0, $this->provenanceDeTest());
    }

    public function testDesParametresActifsNegatifsLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', -1.0, $this->provenanceDeTest(), 10.0, $this->provenanceDeTest());
    }

    public function testDesParametresTotauxNulsLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 10.0, $this->provenanceDeTest(), 0.0, $this->provenanceDeTest());
    }

    public function testDesParametresTotauxInferieursAuxActifsLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 10.0, $this->provenanceDeTest(), 9.0, $this->provenanceDeTest());
    }

    public function testDenseImposeLegaliteEntreActifsEtTotaux(): void
    {
        $provenance = $this->provenanceDeTest();
        $modele = LanguageModel::dense('Modèle dense', 42.0, $provenance);

        self::assertEqualsWithDelta(42.0, $modele->parametresActifsMilliards, 0.0001);
        self::assertEqualsWithDelta(42.0, $modele->parametresTotauxMilliards, 0.0001);
        self::assertSame($provenance, $modele->provenance);
        self::assertSame($provenance, $modele->provenanceParametresTotaux);
    }

    public function testLlama3170bEstMesureEtPublie(): void
    {
        $modele = LanguageModel::llama31_70b();

        self::assertSame('Llama 3.1 70B', $modele->nom);
        self::assertEqualsWithDelta(70.0, $modele->parametresActifsMilliards, 0.0001);
        self::assertSame(ProvenanceType::MesureeEtPubliee, $modele->provenance->type);
    }

    public function testGpt4EstUneHypothese(): void
    {
        $modele = LanguageModel::gpt4();

        self::assertSame(ProvenanceType::Hypothese, $modele->provenance->type);
        self::assertGreaterThan(0, $modele->parametresActifsMilliards);
    }

    public function testGpt4EstUnMoeAvecMoinsDActifsQueDeTotaux(): void
    {
        $modele = LanguageModel::gpt4();

        self::assertEqualsWithDelta(176.0, $modele->parametresActifsMilliards, 0.0001);
        self::assertEqualsWithDelta(1800.0, $modele->parametresTotauxMilliards, 0.0001);
        self::assertSame(ProvenanceType::Hypothese, $modele->provenanceParametresTotaux->type);
        self::assertLessThan($modele->parametresTotauxMilliards, $modele->parametresActifsMilliards);
    }

    public function testGpt4JustifieLHypotheseEtDonneLaBorneHaute(): void
    {
        $note = LanguageModel::gpt4()->provenance->note;

        self::assertStringContainsString(
            'propriétaire',
            $note,
            "La note doit expliquer pourquoi la valeur n'est pas publiée (modèle propriétaire)."
        );
        self::assertStringContainsString(
            '528',
            $note,
            'La note doit citer la borne haute chiffrée de l\'estimation (528 milliards).'
        );
    }

    public function testGpt4oEstUnMoeAvecMoinsDActifsQueDeTotaux(): void
    {
        $modele = LanguageModel::gpt4o();

        self::assertEqualsWithDelta(44.0, $modele->parametresActifsMilliards, 0.0001);
        self::assertEqualsWithDelta(440.0, $modele->parametresTotauxMilliards, 0.0001);
        self::assertSame(ProvenanceType::Hypothese, $modele->provenance->type);
        self::assertSame(ProvenanceType::Hypothese, $modele->provenanceParametresTotaux->type);
    }

    public function testQwen3235bA22bEstMesureEtPublieAvecTotalDistinctDesActifs(): void
    {
        $modele = LanguageModel::qwen3_235b_a22b();

        self::assertEqualsWithDelta(22.0, $modele->parametresActifsMilliards, 0.0001);
        self::assertEqualsWithDelta(235.0, $modele->parametresTotauxMilliards, 0.0001);
        self::assertSame(ProvenanceType::MesureeEtPubliee, $modele->provenance->type);
        self::assertSame(ProvenanceType::MesureeEtPubliee, $modele->provenanceParametresTotaux->type);
    }

    public function testToutesRetourneQuatreModeles(): void
    {
        self::assertCount(4, LanguageModel::toutes());
    }

    public function testChaqueModeleDeToutesPorteUneProvenanceNonVide(): void
    {
        foreach (LanguageModel::toutes() as $modele) {
            self::assertNotSame(
                '',
                trim($modele->provenance->url),
                sprintf('Le modèle « %s » doit citer une provenance pour ses paramètres actifs.', $modele->nom)
            );
            self::assertNotSame(
                '',
                trim($modele->provenanceParametresTotaux->url),
                sprintf('Le modèle « %s » doit citer une provenance pour ses paramètres totaux.', $modele->nom)
            );
        }
    }
}
