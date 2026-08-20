<?php

declare(strict_types=1);

namespace LlmCarbon\Tests;

use InvalidArgumentException;
use LlmCarbon\Provenance;
use LlmCarbon\ProvenanceType;
use PHPUnit\Framework\TestCase;

final class ProvenanceTest extends TestCase
{
    public function testConstruitUneProvenanceAvecSesQuatreAttributs(): void
    {
        $provenance = new Provenance(
            ProvenanceType::MesureeEtPubliee,
            'https://example.org',
            '2024',
            'Ce que la source affirme exactement.'
        );

        self::assertSame(ProvenanceType::MesureeEtPubliee, $provenance->type);
        self::assertSame('https://example.org', $provenance->url);
        self::assertSame('2024', $provenance->millesimeOuDateDeConsultation);
        self::assertSame('Ce que la source affirme exactement.', $provenance->note);
    }

    public function testUneUrlVideLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Provenance(ProvenanceType::MesureeEtPubliee, '   ', '2024', 'Note.');
    }

    public function testUnMillesimeVideLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Provenance(ProvenanceType::MesureeEtPubliee, 'https://example.org', '  ', 'Note.');
    }

    public function testUneNoteVideLevePuisException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Provenance(ProvenanceType::MesureeEtPubliee, 'https://example.org', '2024', '  ');
    }
}
