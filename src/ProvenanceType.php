<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Nature de la provenance d'une valeur numérique.
 *
 * MesureeEtPubliee : la source publie elle-même cette valeur (mesure, annonce officielle, jeu de
 * données) ; suivre l'URL de la Provenance suffit à la vérifier telle quelle.
 * Hypothese : la valeur n'est pas publiée par son propriétaire ; elle est reconstituée à partir
 * d'indices indirects (architecture divulguée, ratio d'activation typique, benchmarks...) et doit
 * donc être affichée et traitée comme une estimation, jamais comme une mesure.
 */
enum ProvenanceType
{
    case MesureeEtPubliee;
    case Hypothese;
}
