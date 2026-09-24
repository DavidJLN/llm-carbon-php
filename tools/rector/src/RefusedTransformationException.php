<?php

declare(strict_types=1);

namespace LlmCarbon\Rector;

use RuntimeException;

/**
 * Thrown by a rule that meets a case it does not know how to transform safely. Rector turns it
 * into an error that names the file, leaves that file untouched, still processes the others and
 * exits with a non-zero code: the refusal cannot go unnoticed.
 */
final class RefusedTransformationException extends RuntimeException
{
}
