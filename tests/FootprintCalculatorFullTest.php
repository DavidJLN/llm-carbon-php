<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use InvalidArgumentException;
use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorFull;
use LlmCarbon\LanguageModel;
use LlmCarbon\Provenance;
use LlmCarbon\ProvenanceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * One test per new term of the FULL model (required memory, GPU cards, duration, server energy,
 * GPU energy, total energy, emissions), each isolated on its own FootprintFull property so that a
 * regression on a single term makes exactly one targeted test fail. Each test was manually
 * sabotaged during development (constant or operator temporarily altered in
 * FootprintCalculatorFull) to verify it does fail when the calculation is wrong.
 */
final class FootprintCalculatorFullTest extends TestCase
{
    private function llama70b(): LanguageModel
    {
        return LanguageModel::llama31_70b();
    }

    private function testProvenance(): Provenance
    {
        return new Provenance(ProvenanceType::MeasuredAndPublished, 'https://example.org', '2024', 'Note.');
    }

    public function testRequiredMemoryGbOnlyDependsOnTotalParametersAndQuantization(): void
    {
        // 1.2 x 70 x 4 / 8 = 42 GB, regardless of the emission zone and the number of tokens.
        $footprint = (new FootprintCalculatorFull())
            ->calculate($this->llama70b(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(42.0, $footprint->requiredMemoryGb, 0.0001);
    }

    /**
     * @return iterable<string, array{float, int}>
     */
    public static function totalParametersAndExpectedCards(): iterable
    {
        // memory = 1.2 x P_total x 4 / 8 = 0.6 x P_total (GB)
        yield '100 milliards -> 60 Go -> 1 carte' => [100.0, 1];
        yield '200 milliards -> 120 Go -> 2 cartes (plafond, pas 1,5)' => [200.0, 2];
        yield '1800 milliards (GPT-4) -> 1080 Go -> 14 cartes' => [1800.0, 14];
    }

    #[DataProvider('totalParametersAndExpectedCards')]
    public function testGpuCardsRoundsUpToTheCeiling(float $totalParametersBillions, int $expectedCards): void
    {
        $model = new LanguageModel(
            'Modèle de test',
            1.0,
            $this->testProvenance(),
            $totalParametersBillions,
            $this->testProvenance(),
        );

        $footprint = (new FootprintCalculatorFull())
            ->calculate($model, EmissionFactor::france(), 500);

        self::assertSame($expectedCards, $footprint->gpuCards);
    }

    public function testDurationSecondsDependsOnActiveParametersAndTokens(): void
    {
        // (8.02e-4 x 70 + 2.23e-2) x 500 = 39.22 s
        $footprint = (new FootprintCalculatorFull())
            ->calculate($this->llama70b(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(39.22, $footprint->durationSeconds, 0.0001);
    }

    public function testServerEnergyWhConvertsTheDurationToHours(): void
    {
        // (39.22 s / 3600) x 1000 W x (1 card / 8) = 1.361805... Wh
        // This test fails if the seconds -> hours conversion (/ 3600) is forgotten: the result
        // would then be 3600 times too large (4902.5 Wh), which is physically absurd for
        // 500 tokens.
        $footprint = (new FootprintCalculatorFull())
            ->calculate($this->llama70b(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(1.361805556, $footprint->serverEnergyWh, 0.000001);
    }

    public function testGpuEnergyPerCardWhUsesTheEcologitsRegression(): void
    {
        // (8.91e-5 x 70 + 1.43e-3) x 500 = 3.8335 Wh
        $footprint = (new FootprintCalculatorFull())
            ->calculate($this->llama70b(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(3.8335, $footprint->gpuEnergyPerCardWh, 0.0001);
    }

    public function testTotalEnergyWhCombinesServerAndCardsTimesGpuEnergyUnderPue(): void
    {
        // GPT-4 (14 cards, not 1) deliberately chosen here rather than Llama 70B: with a single
        // card, forgetting the "cards x" factor on the GPU energy would be invisible (cards x X = X
        // when cards = 1). PUE x (server_energy + cards x gpu_energy)
        // = 1.2 x (39.72791667 + 14 x 8.5558) = 191.41094 Wh.
        // This test fails if the number of cards is not applied as a multiplicative factor of
        // the GPU energy, or if the PUE is not applied to the sum of the two terms.
        $footprint = (new FootprintCalculatorFull())
            ->calculate(LanguageModel::gpt4(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(191.41094, $footprint->totalEnergyWh, 0.00001);
    }

    /**
     * @return iterable<string, array{EmissionFactor, float}>
     */
    public static function zonesAndExpectedEmissions(): iterable
    {
        yield 'France' => [EmissionFactor::france(), 0.506854];
        yield 'Europe' => [EmissionFactor::europe(), 3.175786];
    }

    #[DataProvider('zonesAndExpectedEmissions')]
    public function testEmissionsGco2eqAppliesTheZoneFactor(
        EmissionFactor $factor,
        float $expectedEmissionsGco2eq
    ): void {
        $footprint = (new FootprintCalculatorFull())
            ->calculate($this->llama70b(), $factor, 500);

        self::assertEqualsWithDelta($expectedEmissionsGco2eq, $footprint->emissionsGco2eq, 0.0001);
    }

    public function testGpt4oGivesTheExpectedValue(): void
    {
        // Regression: 44 billion active / 440 billion total -> 4 GPU cards (264 GB
        // required / 80 GB per card, rounded up) -> 17.6400 Wh with the full model.
        $footprint = (new FootprintCalculatorFull())
            ->calculate(LanguageModel::gpt4o(), EmissionFactor::france(), 500);

        self::assertSame(4, $footprint->gpuCards);
        self::assertEqualsWithDelta(17.6400, $footprint->totalEnergyWh, 0.0001);
    }

    public function testQwen3235bA22bGivesTheExpectedValue(): void
    {
        // 22 billion active / 235 billion total -> 141 GB required -> 2 GPU cards.
        $footprint = (new FootprintCalculatorFull())
            ->calculate(LanguageModel::qwen3_235b_a22b(), EmissionFactor::france(), 500);

        self::assertSame(2, $footprint->gpuCards);
        self::assertEqualsWithDelta(5.732573, $footprint->totalEnergyWh, 0.00001);
    }

    public function testAnInputRangeIsNotAnOutputRangeForGpt4o(): void
    {
        // Trap: GPT-4o's active parameters range from 44 to 132 billion (exactly x3), but this
        // only multiplies the full model's total energy by ≈2.40 — neither by 3 (the input
        // ratio), nor by ≈2.47 (the ratio of the GPU energy regression alone, see
        // LanguageModel::gpt4o()). The regression is affine (constant term β), and the full
        // model combines two of them (duration, GPU energy) under the same PUE.
        $calculator = new FootprintCalculatorFull();
        $factor = EmissionFactor::france();

        $lowModel = new LanguageModel('t', 44, $this->testProvenance(), 440, $this->testProvenance());
        $highModel = new LanguageModel('t', 132, $this->testProvenance(), 440, $this->testProvenance());

        $lowEnergy = $calculator->calculate($lowModel, $factor, 500)->totalEnergyWh;
        $highEnergy = $calculator->calculate($highModel, $factor, 500)->totalEnergyWh;

        self::assertEqualsWithDelta(2.400188, $highEnergy / $lowEnergy, 0.00001);
    }

    public function testAnInputRangeIsNotAnOutputRangeForGpt4(): void
    {
        // Same trap for GPT-4: 176 to 528 billion active parameters (x3 in input), but only
        // x2.81 in total energy (versus x2.83 for the GPU energy regression alone, see
        // LanguageModel::gpt4()) — the two ratios diverge, even though they are close here.
        $calculator = new FootprintCalculatorFull();
        $factor = EmissionFactor::france();

        $lowModel = new LanguageModel('t', 176, $this->testProvenance(), 1760, $this->testProvenance());
        $highModel = new LanguageModel('t', 528, $this->testProvenance(), 1760, $this->testProvenance());

        $lowEnergy = $calculator->calculate($lowModel, $factor, 500)->totalEnergyWh;
        $highEnergy = $calculator->calculate($highModel, $factor, 500)->totalEnergyWh;

        self::assertEqualsWithDelta(2.806530, $highEnergy / $lowEnergy, 0.00001);
    }

    public function testZeroGeneratedTokensThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FootprintCalculatorFull())->calculate($this->llama70b(), EmissionFactor::france(), 0);
    }

    public function testANegativeNumberOfTokensThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FootprintCalculatorFull())->calculate($this->llama70b(), EmissionFactor::france(), -1);
    }
}
