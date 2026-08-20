<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use InvalidArgumentException;
use LlmCarbon\LanguageModel;
use PHPUnit\Framework\TestCase;

final class LanguageModelTest extends TestCase
{
    public function testConstruitUnModeleAvecSesTroisAttributs(): void
    {
        $modele = new LanguageModel('Llama 3.1 70B', 70.0, 'https://example.org');

        self::assertSame('Llama 3.1 70B', $modele->nom);
        self::assertEqualsWithDelta(70.0, $modele->parametresActifsMilliards, 0.0001);
        self::assertSame('https://example.org', $modele->urlSource);
    }

    public function testDesParametresActifsNulsLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', 0.0, 'https://example.org');
    }

    public function testDesParametresActifsNegatifsLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LanguageModel('Modèle invalide', -1.0, 'https://example.org');
    }
}
