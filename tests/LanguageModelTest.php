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
    private function testProvenance(): Provenance
    {
        return new Provenance(ProvenanceType::MeasuredAndPublished, 'https://example.org', '2024', 'Note.');
    }

    public function testBuildsAModelWithItsFiveAttributes(): void
    {
        $model = new LanguageModel(
            'Llama 3.1 70B',
            70.0,
            $this->testProvenance(),
            70.0,
            $this->testProvenance(),
        );

        self::assertSame('Llama 3.1 70B', $model->name);
        self::assertEqualsWithDelta(70.0, $model->activeParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $model->provenance->type);
        self::assertEqualsWithDelta(70.0, $model->totalParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $model->totalParametersProvenance->type);
    }

    public function testZeroActiveParametersThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 0.0, $this->testProvenance(), 10.0, $this->testProvenance());
    }

    public function testNegativeActiveParametersThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', -1.0, $this->testProvenance(), 10.0, $this->testProvenance());
    }

    public function testZeroTotalParametersThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 10.0, $this->testProvenance(), 0.0, $this->testProvenance());
    }

    public function testTotalParametersBelowActiveParametersThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 10.0, $this->testProvenance(), 9.0, $this->testProvenance());
    }

    public function testDenseEnforcesEqualityBetweenActiveAndTotalParameters(): void
    {
        $provenance = $this->testProvenance();
        $model = LanguageModel::dense('Modèle dense', 42.0, $provenance);

        self::assertEqualsWithDelta(42.0, $model->activeParametersBillions, 0.0001);
        self::assertEqualsWithDelta(42.0, $model->totalParametersBillions, 0.0001);
        self::assertSame($provenance, $model->provenance);
        self::assertSame($provenance, $model->totalParametersProvenance);
    }

    public function testLlama3170bIsMeasuredAndPublished(): void
    {
        $model = LanguageModel::llama31_70b();

        self::assertSame('Llama 3.1 70B', $model->name);
        self::assertEqualsWithDelta(70.0, $model->activeParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $model->provenance->type);
    }

    public function testGpt4IsAHypothesis(): void
    {
        $model = LanguageModel::gpt4();

        self::assertSame(ProvenanceType::Hypothesis, $model->provenance->type);
        self::assertGreaterThan(0, $model->activeParametersBillions);
    }

    public function testGpt4IsAMoeWithFewerActiveThanTotalParameters(): void
    {
        $model = LanguageModel::gpt4();

        self::assertEqualsWithDelta(176.0, $model->activeParametersBillions, 0.0001);
        self::assertEqualsWithDelta(1800.0, $model->totalParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::Hypothesis, $model->totalParametersProvenance->type);
        self::assertLessThan($model->totalParametersBillions, $model->activeParametersBillions);
    }

    public function testGpt4JustifiesTheHypothesisAndGivesTheUpperBound(): void
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

    public function testGpt4oIsAMoeWithFewerActiveThanTotalParameters(): void
    {
        $model = LanguageModel::gpt4o();

        self::assertEqualsWithDelta(44.0, $model->activeParametersBillions, 0.0001);
        self::assertEqualsWithDelta(440.0, $model->totalParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::Hypothesis, $model->provenance->type);
        self::assertSame(ProvenanceType::Hypothesis, $model->totalParametersProvenance->type);
    }

    public function testQwen3235bA22bIsMeasuredAndPublishedWithTotalDistinctFromActive(): void
    {
        $model = LanguageModel::qwen3_235b_a22b();

        self::assertEqualsWithDelta(22.0, $model->activeParametersBillions, 0.0001);
        self::assertEqualsWithDelta(235.0, $model->totalParametersBillions, 0.0001);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $model->provenance->type);
        self::assertSame(ProvenanceType::MeasuredAndPublished, $model->totalParametersProvenance->type);
    }

    public function testAllReturnsFourModels(): void
    {
        self::assertCount(4, LanguageModel::all());
    }

    public function testEachModelFromAllCarriesANonEmptyProvenance(): void
    {
        foreach (LanguageModel::all() as $model) {
            self::assertNotSame(
                '',
                trim($model->provenance->url),
                sprintf('Le modèle « %s » doit citer une provenance pour ses paramètres actifs.', $model->name)
            );
            self::assertNotSame(
                '',
                trim($model->totalParametersProvenance->url),
                sprintf('Le modèle « %s » doit citer une provenance pour ses paramètres totaux.', $model->name)
            );
        }
    }
}
