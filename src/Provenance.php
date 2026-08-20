<?php

declare(strict_types=1);

namespace LlmCarbon;

use InvalidArgumentException;

/**
 * Provenance d'une valeur numérique utilisée dans le calcul : d'où elle vient, si elle est
 * mesurée et publiée par une source ou reconstituée par hypothèse, et ce que la source affirme
 * exactement.
 *
 * Toute valeur numérique portée par EmissionFactor ou LanguageModel doit être construite avec une
 * Provenance : ce n'est pas une vérification à l'exécution, c'est la signature de leurs
 * constructeurs qui l'impose (paramètre typé, obligatoire, non nullable). Un chiffre sans
 * provenance ne compile pas.
 */
final class Provenance
{
    public function __construct(
        public readonly ProvenanceType $type,
        public readonly string $url,
        public readonly string $millesimeOuDateDeConsultation,
        public readonly string $note,
    ) {
        if (trim($this->url) === '') {
            throw new InvalidArgumentException('La provenance doit citer une URL de source.');
        }

        if (trim($this->millesimeOuDateDeConsultation) === '') {
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
