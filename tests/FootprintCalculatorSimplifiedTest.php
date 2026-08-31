<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use InvalidArgumentException;
use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorSimplified;
use LlmCarbon\LanguageModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FootprintCalculatorSimplifiedTest extends TestCase
{
    private function llama70b(): LanguageModel
    {
        return LanguageModel::llama31_70b();
    }

    public function testTheReferenceCaseDoesNotMove(): void
    {
        $footprint = (new FootprintCalculatorSimplified())
            ->calculate($this->llama70b(), EmissionFactor::france(), 500);

        // Tolerance to the ten-thousandth: we compare floats, never with strict equality.
        self::assertEqualsWithDelta(4.6002, $footprint->totalEnergyWh, 0.0001);
        self::assertEqualsWithDelta(0.3740, $footprint->emissionsGco2eq, 0.0001);
    }

    /**
     * @return iterable<string, array{EmissionFactor, float}>
     */
    public static function zonesAndExpectedEmissions(): iterable
    {
        yield 'France' => [EmissionFactor::france(), 0.3740];
        yield 'Europe' => [EmissionFactor::europe(), 2.3433];
        yield 'États-Unis' => [EmissionFactor::unitedStates(), 3.1272];
        yield 'Monde' => [EmissionFactor::world(), 2.7164];
    }

    #[DataProvider('zonesAndExpectedEmissions')]
    public function testEachZoneGivesTheExpectedEmissionsAtEqualEnergy(
        EmissionFactor $factor,
        float $expectedEmissionsGco2eq
    ): void {
        $footprint = (new FootprintCalculatorSimplified())
            ->calculate($this->llama70b(), $factor, 500);

        self::assertEqualsWithDelta(4.6002, $footprint->totalEnergyWh, 0.0001);
        self::assertEqualsWithDelta($expectedEmissionsGco2eq, $footprint->emissionsGco2eq, 0.0001);
    }

    public function testGpt4GivesTheExpectedValues(): void
    {
        $footprint = (new FootprintCalculatorSimplified())
            ->calculate(LanguageModel::gpt4(), EmissionFactor::france(), 500);

        // Locks in gpt4()'s 176 billion active parameters: a change to this value (or to the
        // EcoLogits coefficients) must make this test fail.
        self::assertEqualsWithDelta(10.2670, $footprint->totalEnergyWh, 0.0001);
        self::assertEqualsWithDelta(0.8347, $footprint->emissionsGco2eq, 0.0001);
    }

    public function testGpt4oGivesTheExpectedValues(): void
    {
        $footprint = (new FootprintCalculatorSimplified())
            ->calculate(LanguageModel::gpt4o(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(3.2102, $footprint->totalEnergyWh, 0.0001);
        self::assertEqualsWithDelta(0.2610, $footprint->emissionsGco2eq, 0.0001);
    }

    public function testQwen3235bA22bGivesTheExpectedValues(): void
    {
        $footprint = (new FootprintCalculatorSimplified())
            ->calculate(LanguageModel::qwen3_235b_a22b(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(2.0341, $footprint->totalEnergyWh, 0.0001);
        self::assertEqualsWithDelta(0.1654, $footprint->emissionsGco2eq, 0.0001);
    }

    public function testZeroGeneratedTokensThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FootprintCalculatorSimplified())->calculate($this->llama70b(), EmissionFactor::france(), 0);
    }

    public function testANegativeNumberOfTokensThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FootprintCalculatorSimplified())->calculate($this->llama70b(), EmissionFactor::france(), -1);
    }
}
