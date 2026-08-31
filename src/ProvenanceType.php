<?php

declare(strict_types=1);

namespace LlmCarbon;

/**
 * Nature of the provenance of a numeric value.
 *
 * MeasuredAndPublished: the source itself publishes this value (measurement, official
 * announcement, dataset); following the Provenance URL is enough to verify it as-is.
 * Hypothesis: the value is not published by its owner; it is reconstructed from indirect clues
 * (leaked architecture, typical activation ratio, benchmarks...) and must therefore be displayed
 * and treated as an estimate, never as a measurement.
 */
enum ProvenanceType
{
    case MeasuredAndPublished;
    case Hypothesis;
}
