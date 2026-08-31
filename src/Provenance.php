<?php

declare(strict_types=1);

namespace LlmCarbon;

use InvalidArgumentException;

/**
 * Provenance of a numeric value used in the calculation: where it comes from, whether it is
 * measured and published by a source or reconstructed as a hypothesis, and what the source
 * states exactly.
 *
 * Any numeric value carried by EmissionFactor or LanguageModel must be constructed with a
 * Provenance: this is not a runtime check, it is the signature of their constructors that
 * enforces it (typed, mandatory, non-nullable parameter). A number without a provenance does not
 * compile.
 */
final class Provenance
{
    public function __construct(
        public readonly ProvenanceType $type,
        public readonly string $url,
        public readonly string $yearOrConsultationDate,
        public readonly string $note,
    ) {
        if (trim($this->url) === '') {
            throw new InvalidArgumentException('La provenance doit citer une URL de source.');
        }

        if (trim($this->yearOrConsultationDate) === '') {
            throw new InvalidArgumentException(
                'La provenance doit préciser un millésime (année des données) ou une date de consultation.'
            );
        }

        if (trim($this->note) === '') {
            throw new InvalidArgumentException(
                'La provenance doit préciser, dans une note, ce que la source affirme exactement.'
            );
        }
    }
}
