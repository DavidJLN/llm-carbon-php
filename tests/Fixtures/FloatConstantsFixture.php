<?php

declare(strict_types=1);

namespace LlmCarbon\Tests\Fixtures;

/**
 * Deliberately broken constants, one per case of the float constant convention, used to prove
 * that FloatConstantConventionTest detects each violation. Values and URLs are placeholders:
 * this class is never used by the calculation.
 */
final class FloatConstantsFixture
{
    /**
     * @source https://exemple.org/document 2024
     */
    public const VALID_WH = 1.5;

    /**
     * @source https://exemple.org/document 2024
     */
    public const VALID_WH_PER_BILLION = 1.5;

    /**
     * @source https://exemple.org/document 2024
     */
    public const PUE = 1.2;

    /**
     * @source https://exemple.org/document 2024
     */
    public const ENERGY_PER_BILLION = 1.5;

    public const NO_SOURCE_WH = 1.5;

    /**
     * @source https://exemple.org/2024/document
     */
    public const YEAR_ONLY_IN_URL_WH = 1.5;

    /**
     * @source Rapport interne 2024
     */
    public const NO_URL_WH = 1.5;

    public const INTEGER_WITHOUT_UNIT_NOR_SOURCE = 80;
}
