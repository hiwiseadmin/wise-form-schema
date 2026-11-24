<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\ConditionEvaluator;
use wise\form\schema\tests\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ConditionEvaluator();
    }


    public function testEqualsToleratesTypeDifference(): void
    {
        $this->assertTrue($this->eval('=', 1, ['f' => '1']));
        $this->assertTrue($this->eval('=', true, ['f' => '1']));
        $this->assertTrue($this->eval('=', false, ['f' => '0']));
        $this->assertTrue($this->eval('=', 'company', ['f' => 'company']));
    }

    public function testMissingFieldIsTreatedAsEmptyString(): void
    {
        $this->assertTrue($this->eval('=', '', []));
        $this->assertFalse($this->eval('=', 'yes', []));
    }

    public function testNotEquals(): void
    {
        $this->assertTrue($this->eval('!=', 'company', ['f' => 'personal']));
        $this->assertFalse($this->eval('!=', 'company', ['f' => 'company']));
    }


    public function testNumericComparison(): void
    {
        $this->assertTrue($this->eval('>', 18, ['f' => '28']));
        $this->assertFalse($this->eval('>', 18, ['f' => '18']));
        $this->assertTrue($this->eval('>=', 18, ['f' => '18']));
        $this->assertTrue($this->eval('<', 10, ['f' => '9']));
        $this->assertTrue($this->eval('<=', 10, ['f' => '10']));
    }

    public function testDateStringComparison(): void
    {
        $this->assertTrue($this->eval('>', '2026-01-01', ['f' => '2026-09-22']));
        $this->assertFalse($this->eval('>', '2026-12-31', ['f' => '2026-09-22']));
    }


    public function testInListWithArray(): void
    {
        $this->assertTrue($this->eval('in', ['a', 'b'], ['f' => 'b']));
        $this->assertFalse($this->eval('in', ['a', 'b'], ['f' => 'c']));
    }

    public function testInListWithCommaString(): void
    {
        $this->assertTrue($this->eval('in', 'a,b,c', ['f' => 'c']));
        $this->assertFalse($this->eval('in', 'a,b,c', ['f' => 'd']));
    }

    public function testInListMatchesAnyElementOfArrayActual(): void
    {
        $this->assertTrue($this->eval('in', ['admin', 'editor'], ['f' => ['viewer', 'editor']]));
        $this->assertFalse($this->eval('in', ['admin', 'editor'], ['f' => ['viewer', 'guest']]));
    }

    public function testNotIn(): void
    {
        $this->assertTrue($this->eval('not_in', ['a', 'b'], ['f' => 'c']));
        $this->assertFalse($this->eval('not_in', ['a', 'b'], ['f' => 'a']));
    }


    public function testUnknownOperatorIsAlwaysFalse(): void
    {
        $this->assertFalse($this->eval('===', 'a', ['f' => 'a']));
        $this->assertFalse($this->eval('', 'a', ['f' => 'a']));
    }

    public function testEvaluateAllIsAnd(): void
    {
        $conditions = [
            ['field' => 'type', 'operator' => '=', 'value' => 'company'],
            ['field' => 'scale', 'operator' => '>', 'value' => 100],
        ];

        $this->assertTrue($this->evaluator->evaluateAll($conditions, ['type' => 'company', 'scale' => '200']));
        $this->assertFalse($this->evaluator->evaluateAll($conditions, ['type' => 'company', 'scale' => '50']));
        $this->assertFalse($this->evaluator->evaluateAll($conditions, ['type' => 'personal', 'scale' => '200']));
    }

    public function testEmptyConditionsIsTrue(): void
    {
        $this->assertTrue($this->evaluator->evaluateAll([], ['anything' => 1]));
    }

    public function testOperatorsConstantIsDocumentedSet(): void
    {
        $this->assertSame(['=', '!=', '>', '<', '>=', '<=', 'in', 'not_in'], ConditionEvaluator::OPERATORS);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function eval(string $operator, mixed $expected, array $data): bool
    {
        return $this->evaluator->evaluate(
            ['field' => 'f', 'operator' => $operator, 'value' => $expected],
            $data
        );
    }
}
