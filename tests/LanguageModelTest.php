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

    public function testConstruitUnModeleAvecSesTroisAttributs(): void
    {
        $modele = new LanguageModel('Llama 3.1 70B', 70.0, $this->provenanceDeTest());

        self::assertSame('Llama 3.1 70B', $modele->nom);
        self::assertEqualsWithDelta(70.0, $modele->parametresActifsMilliards, 0.0001);
        self::assertSame(ProvenanceType::MesureeEtPubliee, $modele->provenance->type);
    }

    public function testDesParametresActifsNulsLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 0.0, $this->provenanceDeTest());
    }

    public function testDesParametresActifsNegatifsLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', -1.0, $this->provenanceDeTest());
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

    public function testToutesRetourneDeuxModeles(): void
    {
        self::assertCount(2, LanguageModel::toutes());
    }

    public function testChaqueModeleDeToutesPorteUneProvenanceNonVide(): void
    {
        foreach (LanguageModel::toutes() as $modele) {
            self::assertNotSame(
                '',
                trim($modele->provenance->url),
                sprintf('Le modèle « %s » doit citer une provenance.', $modele->nom)
            );
        }
    }
}
