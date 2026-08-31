<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use InvalidArgumentException;
use LlmCarbon\Provenance;
use LlmCarbon\ProvenanceType;
use PHPUnit\Framework\TestCase;

final class ProvenanceTest extends TestCase
{
    public function testBuildsAProvenanceWithItsFourAttributes(): void
    {
        $provenance = new Provenance(
            ProvenanceType::MeasuredAndPublished,
            'https://example.org',
            '2024',
            'Ce que la source affirme exactement.'
        );

        self::assertSame(ProvenanceType::MeasuredAndPublished, $provenance->type);
        self::assertSame('https://example.org', $provenance->url);
        self::assertSame('2024', $provenance->yearOrConsultationDate);
        self::assertSame('Ce que la source affirme exactement.', $provenance->note);
    }

    public function testAnEmptyUrlThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Provenance(ProvenanceType::MeasuredAndPublished, '   ', '2024', 'Note.');
    }

    public function testAnEmptyYearThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Provenance(ProvenanceType::MeasuredAndPublished, 'https://example.org', '  ', 'Note.');
    }

    public function testAnEmptyNoteThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Provenance(ProvenanceType::MeasuredAndPublished, 'https://example.org', '2024', '  ');
    }
}
