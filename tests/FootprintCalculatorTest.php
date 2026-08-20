<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use InvalidArgumentException;
use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculator;
use LlmCarbon\LanguageModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FootprintCalculatorTest extends TestCase
{
    private function llama70b(): LanguageModel
    {
        return new LanguageModel('Llama 3.1 70B', 70.0, 'https://example.org');
    }

    public function testLeCasDeReferenceNeBougePas(): void
    {
        $empreinte = (new FootprintCalculator())
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
        yield 'Monde' => [EmissionFactor::monde(), 2.7160];
    }

    #[DataProvider('zonesEtEmissionsAttendues')]
    public function testChaqueZoneDonneLesEmissionsAttenduesAEnergieIdentique(
        EmissionFactor $facteur,
        float $emissionsAttenduesGco2eq
    ): void {
        $empreinte = (new FootprintCalculator())
            ->calculate($this->llama70b(), $facteur, 500);

        self::assertEqualsWithDelta(4.6002, $empreinte->energieTotaleWh, 0.0001);
        self::assertEqualsWithDelta($emissionsAttenduesGco2eq, $empreinte->emissionsGco2eq, 0.0001);
    }

    public function testZeroTokenGenereLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FootprintCalculator())->calculate($this->llama70b(), EmissionFactor::france(), 0);
    }

    public function testUnNombreDeTokensNegatifLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FootprintCalculator())->calculate($this->llama70b(), EmissionFactor::france(), -1);
    }
}
