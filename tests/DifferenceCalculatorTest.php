<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use LlmCarbon\DifferenceCalculator;
use LlmCarbon\EmissionFactor;
use LlmCarbon\FootprintCalculatorFull;
use LlmCarbon\FootprintCalculatorSimplified;
use LlmCarbon\LanguageModel;
use PHPUnit\Framework\TestCase;

/**
 * One test per new term (total difference in Wh and in %, server share in Wh and in %, cards
 * share in Wh and in %), each independently sabotageable. Each test was manually sabotaged during
 * development (by temporarily altering DifferenceCalculator::calculate) to verify it does fail
 * when the calculation is wrong.
 */
final class DifferenceCalculatorTest extends TestCase
{
    private function compute(LanguageModel $model): array
    {
        $simplified = (new FootprintCalculatorSimplified())->calculate($model, EmissionFactor::france(), 500);
        $full = (new FootprintCalculatorFull())->calculate($model, EmissionFactor::france(), 500);

        return [$simplified, $full];
    }

    public function testTotalDifferenceWhIsTheDifferenceOfTheTwoTotalEnergies(): void
    {
        [$simplified, $full] = $this->compute(LanguageModel::llama31_70b());

        $difference = (new DifferenceCalculator())->calculate($simplified, $full);

        self::assertEqualsWithDelta(1.63416667, $difference->totalDifferenceWh, 0.00001);
    }

    public function testTotalDifferencePercentIsRelativeToTheSimplifiedModel(): void
    {
        [$simplified, $full] = $this->compute(LanguageModel::llama31_70b());

        $difference = (new DifferenceCalculator())->calculate($simplified, $full);

        self::assertEqualsWithDelta(35.52381781, $difference->totalDifferencePercent, 0.00001);
    }

    public function testDifferenceMultiplierIsNotThePercentageDividedBy100(): void
    {
        // Classic trap: "+449.5%" does not mean "x 4.495" but "x 5.495"
        // (= 1 + 449.5/100). Locks in GPT-4o, where the difference is large enough to make the
        // error obvious if someone mistakenly replaced the multiplier with the raw percentage.
        $simplified = (new FootprintCalculatorSimplified())
            ->calculate(LanguageModel::gpt4o(), EmissionFactor::france(), 500);
        $full = (new FootprintCalculatorFull())
            ->calculate(LanguageModel::gpt4o(), EmissionFactor::france(), 500);

        $difference = (new DifferenceCalculator())->calculate($simplified, $full);

        self::assertEqualsWithDelta(5.494904, $difference->differenceMultiplier, 0.00001);
        self::assertEqualsWithDelta(
            $difference->differenceMultiplier,
            1 + $difference->totalDifferencePercent / 100,
            0.00001,
            'Le multiplicateur doit être cohérent avec le pourcentage : x = 1 + %/100.'
        );
    }

    public function testServerDifferenceEqualsTheTotalDifferenceWhenOnlyOneCardIsNeeded(): void
    {
        // Llama 70B only requires a single GPU card: the entire difference with the simplified
        // model therefore comes from the server term, the "cards" share must be near zero.
        [$simplified, $full] = $this->compute(LanguageModel::llama31_70b());

        $difference = (new DifferenceCalculator())->calculate($simplified, $full);

        self::assertEqualsWithDelta(1.63416667, $difference->serverDifferenceWh, 0.00001);
        self::assertEqualsWithDelta(35.52381781, $difference->serverDifferencePercent, 0.00001);
        self::assertEqualsWithDelta(0.0, $difference->cardsDifferenceWh, 0.0001);
        self::assertEqualsWithDelta(0.0, $difference->cardsDifferencePercent, 0.0001);
    }

    public function testCardsDifferenceRepresents1300PercentForGpt4Which14Cards(): void
    {
        // GPT-4 requires 14 cards: the "cards" share of the difference corresponds exactly to
        // the 13 extra cards compared to the simplified model (which implicitly counts only
        // one), i.e. 13 x 100% = 1300% of the simplified total energy.
        [$simplified, $full] = $this->compute(LanguageModel::gpt4());

        $difference = (new DifferenceCalculator())->calculate($simplified, $full);

        self::assertEqualsWithDelta(133.47048, $difference->cardsDifferenceWh, 0.00001);
        self::assertEqualsWithDelta(1300.0, $difference->cardsDifferencePercent, 0.0001);
    }

    public function testServerDifferencePercentForGpt4(): void
    {
        [$simplified, $full] = $this->compute(LanguageModel::gpt4());

        $difference = (new DifferenceCalculator())->calculate($simplified, $full);

        self::assertEqualsWithDelta(47.6735, $difference->serverDifferenceWh, 0.00001);
        self::assertEqualsWithDelta(464.33900590, $difference->serverDifferencePercent, 0.00001);
    }

    public function testTheTwoPartsAddUpExactlyToTheTotalDifference(): void
    {
        foreach (LanguageModel::all() as $model) {
            [$simplified, $full] = $this->compute($model);

            $difference = (new DifferenceCalculator())->calculate($simplified, $full);

            self::assertEqualsWithDelta(
                $difference->totalDifferenceWh,
                $difference->serverDifferenceWh + $difference->cardsDifferenceWh,
                0.0001,
                sprintf('La décomposition de %s doit s\'additionner exactement à l\'écart total.', $model->name)
            );
            self::assertEqualsWithDelta(
                $difference->totalDifferencePercent,
                $difference->serverDifferencePercent + $difference->cardsDifferencePercent,
                0.0001,
                sprintf('La décomposition en %% de %s doit s\'additionner exactement à l\'écart total.', $model->name)
            );
        }
    }
}
