<?php

declare(strict_types=1);

namespace LlmCarbon\Rector;

use LlmCarbon\FootprintCalculatorFull;
use LlmCarbon\FootprintCalculatorSimplified;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitor;
use PHPStan\Type\ObjectType;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;

/**
 * Replaces the SIMPLIFIED EcoLogits calculator with the FULL one:
 *
 *     $calculator = new FootprintCalculatorSimplified();
 *     $wh = $calculator->calculate($model, $zone, $tokens)->totalEnergyWh;
 * becomes
 *     $calculator = new FootprintCalculatorFull();
 *     $wh = $calculator->calculate($model, $zone, $tokens)->totalEnergyWh;
 *
 * The code keeps its shape, NOT its figures: the full model adds the non-GPU server energy and
 * multiplies by the number of GPU cards required, so totalEnergyWh and emissionsGco2eq change
 * (DifferenceCalculator quantifies that gap).
 *
 * Classes are recognized by their RESOLVED type (PHPStan type of the expressions, resolved name
 * of the Name nodes), never by the text written in the file: aliases, imports and fully
 * qualified names are all handled alike.
 *
 * Supported shapes only — anything else in the file is refused (RefusedTransformationException)
 * and the file is left untouched, because a partial migration would mix both models silently:
 * - `new FootprintCalculatorSimplified()` assigned to a plain variable in an expression statement,
 *   or called directly: `(new FootprintCalculatorSimplified())->calculate(...)`;
 * - that instance only used as the receiver of calculate();
 * - each calculate() result read immediately through ->totalEnergyWh or ->emissionsGco2eq, the
 *   only two properties Footprint and FootprintFull have in common (FootprintFull is not a
 *   Footprint: a result stored, passed to DifferenceCalculator or read through
 *   ->energyPerTokenWh cannot be migrated);
 * - no other reference to the type (type declaration, instanceof, ::class, static call...).
 * Docblocks are not analysed.
 *
 * Idempotent: once rewritten, the file no longer contains any simplified calculator, and the
 * rule returns null.
 */
final class SimplifiedToFullCalculatorRector extends AbstractRector
{
    private const SIMPLIFIED = FootprintCalculatorSimplified::class;
    private const FULL = FootprintCalculatorFull::class;

    /** Properties shared by Footprint and FootprintFull, hence readable after the migration. */
    private const COMMON_RESULT_PROPERTIES = ['totalEnergyWh', 'emissionsGco2eq'];

    public function getNodeTypes(): array
    {
        return [FileNode::class];
    }

    /**
     * @param FileNode $node
     */
    public function refactor(Node $node): ?FileNode
    {
        $simplifiedType = new ObjectType(self::SIMPLIFIED);

        /** @var array<int, Expr> $simplifiedExpressions every expression typed as the simplified calculator */
        $simplifiedExpressions = [];
        /** @var array<int, true> $supportedPositions ids of expressions found in a supported position */
        $supportedPositions = [];
        /** @var array<int, New_> $instantiations */
        $instantiations = [];
        /** @var array<int, MethodCall> $calculateCalls */
        $calculateCalls = [];
        /** @var array<int, string|null> $readProperties call id => property read on its result */
        $readProperties = [];
        /** @var array<int, Name> $typeReferences Name nodes resolving to the simplified calculator */
        $typeReferences = [];
        /** @var list<string> $refusals */
        $refusals = [];

        $this->traverseNodesWithCallable($node->stmts, function (Node $current) use (
            $simplifiedType,
            &$simplifiedExpressions,
            &$supportedPositions,
            &$instantiations,
            &$readProperties,
            &$typeReferences,
            &$refusals,
        ): ?int {
            // Imports are handled separately (removed once the file is migrated).
            if ($current instanceof Use_ || $current instanceof GroupUse) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            if ($current instanceof Name && $this->isName($current, self::SIMPLIFIED)) {
                $typeReferences[spl_object_id($current)] = $current;
            }

            if ($current instanceof Expression && $current->expr instanceof Assign) {
                $supportedPositions[spl_object_id($current->expr)] = true;
            }

            if (!$current instanceof Expr) {
                return null;
            }

            if ($current instanceof PropertyFetch && $current->var instanceof MethodCall) {
                $readProperties[spl_object_id($current->var)] = $current->name instanceof Identifier
                    ? $current->name->toString()
                    : null;
            }

            if (!$this->isObjectType($current, $simplifiedType)) {
                return null;
            }
            $simplifiedExpressions[spl_object_id($current)] = $current;

            if ($current instanceof Assign && $current->var instanceof Variable && $current->expr instanceof New_) {
                $supportedPositions[spl_object_id($current->var)] = true;
                $supportedPositions[spl_object_id($current->expr)] = true;
            }

            if ($current instanceof New_) {
                if ($current->class instanceof Name) {
                    $instantiations[spl_object_id($current)] = $current;
                } else {
                    $refusals[] = sprintf('ligne %d : instanciation dynamique du calculateur simplifié.', $current->getStartLine());
                }
            }

            return null;
        });

        // Receivers are identified once every expression has been typed: a MethodCall is visited
        // before its receiver, so this cannot be done in the traversal above.
        $this->traverseNodesWithCallable($node->stmts, function (Node $current) use (
            &$simplifiedExpressions,
            &$supportedPositions,
            &$calculateCalls,
            &$refusals,
        ): ?int {
            if (!$current instanceof MethodCall || !isset($simplifiedExpressions[spl_object_id($current->var)])) {
                return null;
            }
            if (!$this->isName($current->name, 'calculate')) {
                $refusals[] = sprintf(
                    'ligne %d : appel de %s() sur le calculateur simplifié, seule calculate() est prise en charge.',
                    $current->getStartLine(),
                    $this->getName($current->name) ?? '(nom dynamique)'
                );

                return null;
            }
            $calculateCalls[spl_object_id($current)] = $current;
            $supportedPositions[spl_object_id($current->var)] = true;

            return null;
        });

        foreach ($calculateCalls as $callId => $call) {
            $readProperty = $readProperties[$callId] ?? null;
            if (!in_array($readProperty, self::COMMON_RESULT_PROPERTIES, true)) {
                $refusals[] = sprintf(
                    'ligne %d : le résultat de calculate() %s ; seules les lectures immédiates %s sont prises en '
                    . 'charge, FootprintFull n\'étant pas un Footprint.',
                    $call->getStartLine(),
                    $readProperty === null
                        ? 'est conservé ou transmis tel quel (variable, argument de DifferenceCalculator...)'
                        : sprintf('est lu via ->%s, absent de FootprintFull', $readProperty),
                    implode(' et ', array_map(static fn (string $property): string => '->' . $property, self::COMMON_RESULT_PROPERTIES))
                );
            }
        }

        foreach ($simplifiedExpressions as $expressionId => $expression) {
            if (!isset($supportedPositions[$expressionId])) {
                $refusals[] = sprintf(
                    'ligne %d : instance du calculateur simplifié utilisée hors des formes prises en charge '
                    . '(affectation d\'un new à une variable, puis appel de calculate()).',
                    $expression->getStartLine()
                );
            }
        }

        $instantiatedClassNames = array_map(static fn (New_ $new): Node => $new->class, $instantiations);
        foreach ($typeReferences as $reference) {
            if (!in_array($reference, $instantiatedClassNames, true)) {
                $refusals[] = sprintf(
                    'ligne %d : référence au type %s hors d\'un new (déclaration de type, instanceof, ::class, '
                    . 'appel statique...), non réécrite par cette règle.',
                    $reference->getStartLine(),
                    self::SIMPLIFIED
                );
            }
        }

        if ($refusals !== []) {
            sort($refusals, SORT_NATURAL);
            throw new RefusedTransformationException(sprintf(
                "SimplifiedToFullCalculatorRector refuse de transformer %s, laissé intact :\n- %s",
                $this->getFile()->getFilePath(),
                implode("\n- ", array_unique($refusals))
            ));
        }

        if ($instantiations === []) {
            return null;
        }

        $fullClassName = $this->resolveFullClassName($node);
        foreach ($instantiations as $new) {
            $new->class = clone $fullClassName;
        }
        $this->removeSimplifiedImports($node);

        return $node;
    }

    /**
     * Short name when the file already imports the full calculator, fully qualified otherwise.
     */
    private function resolveFullClassName(FileNode $fileNode): Name
    {
        foreach ($this->useStatementsOf($fileNode) as $use) {
            foreach ($use->uses as $useItem) {
                if ($use->type === Use_::TYPE_NORMAL && $useItem->name->toString() === self::FULL) {
                    return new Name($useItem->getAlias()->toString());
                }
            }
        }

        return new FullyQualified(self::FULL);
    }

    private function removeSimplifiedImports(FileNode $fileNode): void
    {
        $removeFrom = function (array $stmts): array {
            $kept = [];
            foreach ($stmts as $stmt) {
                if ($stmt instanceof Use_ && $stmt->type === Use_::TYPE_NORMAL) {
                    $stmt->uses = array_values(array_filter(
                        $stmt->uses,
                        static fn ($useItem): bool => $useItem->name->toString() !== self::SIMPLIFIED
                    ));
                    if ($stmt->uses === []) {
                        continue;
                    }
                }
                $kept[] = $stmt;
            }

            return $kept;
        };

        $fileNode->stmts = $removeFrom($fileNode->stmts);
        foreach ($fileNode->stmts as $stmt) {
            if ($stmt instanceof Namespace_) {
                $stmt->stmts = $removeFrom($stmt->stmts);
            }
        }
    }

    /**
     * @return list<Use_>
     */
    private function useStatementsOf(FileNode $fileNode): array
    {
        $uses = [];
        foreach ($fileNode->stmts as $stmt) {
            $stmts = $stmt instanceof Namespace_ ? $stmt->stmts : [$stmt];
            foreach ($stmts as $innerStmt) {
                if ($innerStmt instanceof Use_) {
                    $uses[] = $innerStmt;
                }
            }
        }

        return $uses;
    }
}
