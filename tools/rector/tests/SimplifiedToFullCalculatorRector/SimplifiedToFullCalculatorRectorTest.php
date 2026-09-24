<?php

declare(strict_types=1);

namespace LlmCarbon\Rector\Tests\SimplifiedToFullCalculatorRector;

use Iterator;
use LlmCarbon\Rector\RefusedTransformationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\Fixture\FixtureSplitter;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class SimplifiedToFullCalculatorRectorTest extends AbstractRectorTestCase
{
    /**
     * Fixture/: "before ----- after" files are transformed, single-part "skip_" files must come
     * out unchanged (the rule returns null).
     */
    #[DataProvider('provideFixtures')]
    public function testTransformsOrLeavesUnchanged(string $fixtureFilePath): void
    {
        $this->doTestFile($fixtureFilePath);
    }

    /**
     * Idempotence: the rule run on its own output changes nothing.
     */
    #[DataProvider('provideFixtures')]
    public function testRerunningOnItsOwnOutputChangesNothing(string $fixtureFilePath): void
    {
        $contents = (string) file_get_contents($fixtureFilePath);
        $output = FixtureSplitter::containsSplit($contents)
            ? FixtureSplitter::splitFixtureFileContents($contents)[1]
            : $contents;

        $outputFixturePath = sys_get_temp_dir() . '/idempotence_' . basename($fixtureFilePath);
        file_put_contents($outputFixturePath, $output);

        try {
            $this->doTestFile($outputFixturePath);
        } finally {
            @unlink($outputFixturePath);
        }
    }

    public static function provideFixtures(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    /**
     * @return iterable<string, array{string, string}> fixture => pattern the refusal message must match
     */
    public static function provideRefusals(): iterable
    {
        yield 'résultat conservé puis passé à DifferenceCalculator' => [
            'result_stored_in_variable.php.inc',
            '/ligne 13 : le résultat de calculate\(\) est conservé ou transmis tel quel .*->totalEnergyWh et ->emissionsGco2eq/',
        ];
        yield 'propriété absente de FootprintFull' => [
            'energy_per_token_read.php.inc',
            '/ligne 8 : le résultat de calculate\(\) est lu via ->energyPerTokenWh, absent de FootprintFull/',
        ];
        yield 'type déclaré en paramètre' => [
            'type_declaration.php.inc',
            '/ligne 7 : référence au type LlmCarbon\\\\FootprintCalculatorSimplified hors d\'un new/',
        ];
        yield 'instance transmise à une fonction' => [
            'instance_passed_along.php.inc',
            '/ligne 6 : instance du calculateur simplifié utilisée hors des formes prises en charge/',
        ];
    }

    /**
     * Unsupported cases are refused with a message naming the file, the line and the reason.
     * Rector turns the exception into a reported error and leaves the file untouched.
     */
    #[DataProvider('provideRefusals')]
    public function testRefusesAndReportsUnsupportedCases(string $fixtureFileName, string $messagePattern): void
    {
        try {
            $this->doTestFile(__DIR__ . '/Refused/' . $fixtureFileName);
        } catch (RefusedTransformationException $refusal) {
            $fileName = preg_quote(pathinfo($fixtureFileName, PATHINFO_FILENAME), '/');
            self::assertMatchesRegularExpression('/refuse de transformer \S*' . $fileName . '\S*, laissé intact/', $refusal->getMessage());
            self::assertMatchesRegularExpression($messagePattern, $refusal->getMessage());

            return;
        }

        self::fail('La règle aurait dû refuser de transformer ' . $fixtureFileName);
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/configured_rule.php';
    }
}
