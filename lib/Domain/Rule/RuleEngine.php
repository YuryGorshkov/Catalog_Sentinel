<?php

declare(strict_types=1);

namespace Gorshkov\CatalogSentinel\Domain\Rule;

use Throwable;

final class RuleEngine
{
    /** @param list<RuleInterface> $rules */
    public function __construct(private readonly array $rules)
    {
    }

    public function evaluate(RuleContext $context): RuleEvaluation
    {
        $results = [];
        $decision = Decision::PASS;
        foreach ($this->rules as $rule) {
            try {
                $result = $rule->evaluate($context);
            } catch (Throwable) {
                $result = new RuleResult(
                    $rule->code(),
                    RuleOutcome::ERROR,
                    'rule.internal_error',
                    evaluatedAt: $context->evaluatedAt,
                );
            }
            $results[] = $result;

            if ($result->outcome === RuleOutcome::BLOCK) {
                $decision = Decision::BLOCK;
            } elseif ($result->outcome === RuleOutcome::WARN && $decision === Decision::PASS) {
                $decision = Decision::WARN;
            } elseif ($result->outcome === RuleOutcome::ERROR && $decision === Decision::PASS) {
                $decision = Decision::ERROR;
            }
        }

        return new RuleEvaluation($decision, $results);
    }
}
