<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use FilesystemIterator;
use LlmCarbon\Tests\Fixtures\FloatConstantsFixture;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

require_once __DIR__ . '/Fixtures/FloatConstantsFixture.php';

/**
 * Convention imposed on every float class constant of src/: its name ends with a recognized unit,
 * and its docblock carries a "@source <URL> <year>" line. Enforced here rather than by a linter
 * because the project allows no external dependency.
 */
final class FloatConstantConventionTest extends TestCase
{
    /**
     * Unit suffixes a float constant name may end with. _S (seconds) extends the initial list so
     * that the latency coefficients keep a truthful name.
     */
    private const ALLOWED_UNIT_SUFFIXES = [
        '_WH', '_KWH', '_W', '_G_CO2E_PER_KWH', '_BITS', '_GO', '_S', '_RATIO', '_SANS_UNITE',
    ];

    /**
     * Denominator allowed after a unit suffix, for the EcoLogits regression slopes expressed per
     * billion active parameters (e.g. _WH_PER_BILLION).
     */
    private const PER_BILLION_SUFFIX = '_PER_BILLION';

    public function testEveryFloatConstantOfSrcRespectsTheConvention(): void
    {
        $violations = [];
        $floatConstantCount = 0;

        foreach (self::srcClassNames() as $className) {
            $floatConstantCount += count(self::floatConstantsDeclaredIn($className));
            $violations = [...$violations, ...self::violationsIn($className)];
        }

        // Guards against a vacuous pass (e.g. src/ no longer found, or constants no longer read).
        self::assertGreaterThan(0, $floatConstantCount, 'Aucune constante flottante trouvée dans src/.');
        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testAValidFloatConstantProducesNoViolation(): void
    {
        $violations = self::violationsIn(FloatConstantsFixture::class);

        self::assertEmpty(preg_grep('/::VALID_/', $violations));
    }

    public function testANameWithoutRecognizedUnitIsReportedWithTheAllowedSuffixes(): void
    {
        self::assertContains(
            'Constante FloatConstantsFixture::PUE : pas d\'unité reconnue dans le nom. Suffixes admis : '
            . '_WH, _KWH, _W, _G_CO2E_PER_KWH, _BITS, _GO, _S, _RATIO, _SANS_UNITE, éventuellement '
            . 'suivis de _PER_BILLION (ex. _WH_PER_BILLION). Utilise _RATIO ou _SANS_UNITE si la '
            . 'grandeur est adimensionnelle.',
            self::violationsIn(FloatConstantsFixture::class)
        );
    }

    public function testPerBillionAloneIsNotAUnit(): void
    {
        self::assertNotEmpty(preg_grep(
            '/^Constante FloatConstantsFixture::ENERGY_PER_BILLION : pas d\'unité reconnue/',
            self::violationsIn(FloatConstantsFixture::class)
        ));
    }

    public function testAMissingSourceLineIsReportedWithTheExpectedFormat(): void
    {
        self::assertContains(
            'Constante FloatConstantsFixture::NO_SOURCE_WH : pas de ligne @source avec une URL et une '
            . 'année dans le bloc de documentation. Format attendu : @source https://exemple.org/document '
            . '2024 (l\'année hors de l\'URL).',
            self::violationsIn(FloatConstantsFixture::class)
        );
    }

    public function testASourceLineWithTheYearOnlyInsideTheUrlIsRejected(): void
    {
        self::assertNotEmpty(preg_grep(
            '/::YEAR_ONLY_IN_URL_WH : pas de ligne @source/',
            self::violationsIn(FloatConstantsFixture::class)
        ));
    }

    public function testASourceLineWithoutUrlIsRejected(): void
    {
        self::assertNotEmpty(preg_grep(
            '/::NO_URL_WH : pas de ligne @source/',
            self::violationsIn(FloatConstantsFixture::class)
        ));
    }

    public function testNonFloatConstantsAreIgnored(): void
    {
        self::assertEmpty(preg_grep('/::INTEGER_WITHOUT_UNIT_NOR_SOURCE /', self::violationsIn(FloatConstantsFixture::class)));
    }

    /**
     * @return list<string> one message per broken condition, naming the constant
     */
    private static function violationsIn(string $className): array
    {
        $shortName = (new ReflectionClass($className))->getShortName();
        $violations = [];

        foreach (self::floatConstantsDeclaredIn($className) as $name => $docComment) {
            $label = sprintf('Constante %s::%s', $shortName, $name);

            if (!self::hasRecognizedUnit($name)) {
                $violations[] = sprintf(
                    '%s : pas d\'unité reconnue dans le nom. Suffixes admis : %s, éventuellement suivis de %s '
                    . '(ex. _WH%s). Utilise _RATIO ou _SANS_UNITE si la grandeur est adimensionnelle.',
                    $label,
                    implode(', ', self::ALLOWED_UNIT_SUFFIXES),
                    self::PER_BILLION_SUFFIX,
                    self::PER_BILLION_SUFFIX
                );
            }

            if (!self::hasSourceLineWithUrlAndYear($docComment)) {
                $violations[] = sprintf(
                    '%s : pas de ligne @source avec une URL et une année dans le bloc de documentation. '
                    . 'Format attendu : @source https://exemple.org/document 2024 (l\'année hors de l\'URL).',
                    $label
                );
            }
        }

        return $violations;
    }

    /**
     * @return array<string, string> constant name => docblock ('' when absent)
     */
    private static function floatConstantsDeclaredIn(string $className): array
    {
        $constants = [];

        foreach ((new ReflectionClass($className))->getReflectionConstants() as $constant) {
            if ($constant->getDeclaringClass()->getName() !== $className || !is_float($constant->getValue())) {
                continue;
            }
            $constants[$constant->getName()] = (string) $constant->getDocComment();
        }

        return $constants;
    }

    private static function hasRecognizedUnit(string $name): bool
    {
        if (str_ends_with($name, self::PER_BILLION_SUFFIX)) {
            $name = substr($name, 0, -strlen(self::PER_BILLION_SUFFIX));
        }

        foreach (self::ALLOWED_UNIT_SUFFIXES as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private static function hasSourceLineWithUrlAndYear(string $docComment): bool
    {
        foreach (explode("\n", $docComment) as $line) {
            if (preg_match('/@source\s+(.*)$/', $line, $source) !== 1
                || preg_match('~https?://\S+~', $source[1], $url) !== 1) {
                continue;
            }
            // The year must stand outside the URL: a date embedded in a path is not a vintage.
            if (preg_match('/\b(19|20)\d{2}\b/', str_replace($url[0], '', $source[1])) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<class-string> classes of src/, derived from the PSR-4 mapping LlmCarbon\ => src/
     */
    private static function srcClassNames(): array
    {
        $srcDirectory = dirname(__DIR__) . '/src';
        $classNames = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDirectory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $relativePath = substr($file->getPathname(), strlen($srcDirectory) + 1, -strlen('.php'));
                $classNames[] = 'LlmCarbon\\' . str_replace('/', '\\', $relativePath);
            }
        }
        sort($classNames);

        return $classNames;
    }
}
