<?php

declare(strict_types=1);

namespace Markommerce\Scope\PgSql\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Query\IdentifierValidator;
use Markommerce\Scope\Query\ScopedFieldExpression;
use Markommerce\Scope\Query\ScopedFieldRendererInterface;
use Markommerce\Scope\Signature\ScopeSignature;

class PgSqlScopedFieldRenderer implements ScopedFieldRendererInterface
{
    /**
     * @throws InvalidColumnException
     */
    public function render(ScopedFieldExpression $expression): string
    {
        $this->validateBaseIdentifiers($expression);

        if ($expression->candidateSignatures === []) {
            return "\"$expression->column\"";
        }

        $this->validateSignatures($expression->candidateSignatures);

        $parts = [];

        foreach ($expression->candidateSignatures as $signature) {
            $jsonKey = $signature->toString();
            $parts[] = "\"$expression->jsonColumn\"->'$jsonKey'->>'$expression->property'";
        }

        $parts[] = "\"$expression->column\"";

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    /**
     * @throws InvalidColumnException
     */
    private function validateBaseIdentifiers(ScopedFieldExpression $expression): void
    {
        foreach ([$expression->column, $expression->property, $expression->jsonColumn] as $identifier) {
            if (!IdentifierValidator::isValidIdentifier($identifier)) {
                throw InvalidColumnException::invalidColumn($identifier);
            }
        }
    }

    /**
     * @param list<ScopeSignature> $signatures
     *
     * @throws InvalidColumnException
     */
    private function validateSignatures(array $signatures): void
    {
        foreach ($signatures as $signature) {
            foreach ($signature->axes() as $axisName) {
                if (!IdentifierValidator::isValidIdentifier($axisName)) {
                    throw InvalidColumnException::invalidColumn($axisName);
                }

                $value = $signature->get($axisName);

                if ($value !== null) {
                    $this->validateValueSegments($value);
                }
            }
        }
    }

    /**
     * @throws InvalidColumnException
     */
    private function validateValueSegments(string $value): void
    {
        foreach (explode('.', $value) as $segment) {
            if (!IdentifierValidator::isValidIdentifier($segment)) {
                throw InvalidColumnException::invalidColumn($segment);
            }
        }
    }
}
