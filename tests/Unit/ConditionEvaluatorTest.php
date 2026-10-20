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

    // ──────────────────────────────────────────────
    //  相等 / 不等
    // ──────────────────────────────────────────────

    public function testEqualsToleratesTypeDifference(): void
    {
        // 表单数据总是字符串，声明侧可能写 int / bool，需容忍
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

    // ──────────────────────────────────────────────
    //  数值 / 字符串比较
    // ──────────────────────────────────────────────

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
        // Y-m-d 的字典序与时序一致，无需转时间戳
        $this->assertTrue($this->eval('>', '2026-01-01', ['f' => '2026-09-22']));
        $this->assertFalse($this->eval('>', '2026-12-31', ['f' => '2026-09-22']));
    }

    // ──────────────────────────────────────────────
    //  in / not_in
    // ──────────────────────────────────────────────

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
        // 多选字段的提交值是数组，任一元素命中即成立
        $this->assertTrue($this->eval('in', ['admin', 'editor'], ['f' => ['viewer', 'editor']]));
        $this->assertFalse($this->eval('in', ['admin', 'editor'], ['f' => ['viewer', 'guest']]));
    }

    public function testNotIn(): void
    {
        $this->assertTrue($this->eval('not_in', ['a', 'b'], ['f' => 'c']));
        $this->assertFalse($this->eval('not_in', ['a', 'b'], ['f' => 'a']));
    }

    // ──────────────────────────────────────────────
    //  边界
    // ──────────────────────────────────────────────

    public function testUnknownOperatorIsAlwaysFalse(): void
    {
        // 拼错操作符时不应"放宽校验"，而应视为条件不成立
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
