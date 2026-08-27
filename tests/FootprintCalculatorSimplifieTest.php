<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use InvalidArgumentException;
use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorSimplifie;
use LlmCarbon\LanguageModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FootprintCalculatorSimplifieTest extends TestCase
{
    private function llama70b(): LanguageModel
    {
        return LanguageModel::llama31_70b();
    }

    public function testLeCasDeReferenceNeBougePas(): void
    {
        $empreinte = (new FootprintCalculatorSimplifie())
            ->calculate($this->llama70b(), EmissionFactor::france(), 500);

        // Tolérance à la dixième de millième : on compare des flottants, jamais à l'égalité stricte.
        self::assertEqualsWithDelta(4.6002, $empreinte->energieTotaleWh, 0.0001);
        self::assertEqualsWithDelta(0.3740, $empreinte->emissionsGco2eq, 0.0001);
    }

    /**
     * @return iterable<string, array{EmissionFactor, float}>
     */
    public static function zonesEtEmissionsAttendues(): iterable
    {
        yield 'France' => [EmissionFactor::france(), 0.3740];
        yield 'Europe' => [EmissionFactor::europe(), 2.3433];
        yield 'États-Unis' => [EmissionFactor::etatsUnis(), 3.1272];
        yield 'Monde' => [EmissionFactor::monde(), 2.7164];
    }

    #[DataProvider('zonesEtEmissionsAttendues')]
    public function testChaqueZoneDonneLesEmissionsAttenduesAEnergieIdentique(
        EmissionFactor $facteur,
        float $emissionsAttenduesGco2eq
    ): void {
        $empreinte = (new FootprintCalculatorSimplifie())
            ->calculate($this->llama70b(), $facteur, 500);

        self::assertEqualsWithDelta(4.6002, $empreinte->energieTotaleWh, 0.0001);
        self::assertEqualsWithDelta($emissionsAttenduesGco2eq, $empreinte->emissionsGco2eq, 0.0001);
    }

    public function testGpt4DonneLesValeursAttendues(): void
    {
        $empreinte = (new FootprintCalculatorSimplifie())
            ->calculate(LanguageModel::gpt4(), EmissionFactor::france(), 500);

        // Verrouille les 176 milliards de paramètres actifs de gpt4() : un changement de cette
        // valeur (ou des coefficients EcoLogits) doit faire échouer ce test.
        self::assertEqualsWithDelta(10.2670, $empreinte->energieTotaleWh, 0.0001);
        self::assertEqualsWithDelta(0.8347, $empreinte->emissionsGco2eq, 0.0001);
    }

    public function testGpt4oDonneLesValeursAttendues(): void
    {
        $empreinte = (new FootprintCalculatorSimplifie())
            ->calculate(LanguageModel::gpt4o(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(3.2102, $empreinte->energieTotaleWh, 0.0001);
        self::assertEqualsWithDelta(0.2610, $empreinte->emissionsGco2eq, 0.0001);
    }

    public function testQwen3235bA22bDonneLesValeursAttendues(): void
    {
        $empreinte = (new FootprintCalculatorSimplifie())
            ->calculate(LanguageModel::qwen3_235b_a22b(), EmissionFactor::france(), 500);

        self::assertEqualsWithDelta(2.0341, $empreinte->energieTotaleWh, 0.0001);
        self::assertEqualsWithDelta(0.1654, $empreinte->emissionsGco2eq, 0.0001);
    }

    public function testZeroTokenGenereLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FootprintCalculatorSimplifie())->calculate($this->llama70b(), EmissionFactor::france(), 0);
    }

    public function testUnNombreDeTokensNegatifLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FootprintCalculatorSimplifie())->calculate($this->llama70b(), EmissionFactor::france(), -1);
    }
}
