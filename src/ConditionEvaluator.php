<?php

declare(strict_types=1);

namespace wise\form\schema;

/**
 * ConditionEvaluator — 条件表达式求值器（纯 PHP，零框架依赖）。
 *
 * 消费 {@see \wise\form\schema\field\AbstractField::requiredIf()} /
 * {@see \wise\form\schema\field\AbstractField::requiredUnless()} 产生的
 * `conditional_rules` 结构：
 *
 * ```php
 * ['field' => 'type', 'operator' => '=', 'value' => 'company']
 * ```
 *
 * 用途：给定一份**提交数据**，判断某字段的条件是否成立，
 * 从而让 {@see ValidationGenerator::generateFor()} 把「条件必填」折叠进有效校验规则。
 *
 * 与 {@see Dependency} 的区别：
 *   - `Dependency` 描述的是**前端显隐**元数据（由 HtmlRenderer 转成 data-conditions）
 *   - 本类是**服务端求值**，用于让条件必填真正生效
 *   两者操作符集合保持一致，但用途与消费端完全不同。
 */
class ConditionEvaluator
{
    /**
     * 支持的操作符（与 AbstractField::requiredIf 的文档一致）。
     */
    public const OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'in', 'not_in'];

    /**
     * 求值一组条件（隐式 AND，全部成立才返回 true）。
     *
     * @param array<int, array<string, mixed>> $conditions
     * @param array<string, mixed>             $data
     */
    public function evaluateAll(array $conditions, array $data): bool
    {
        foreach ($conditions as $condition) {
            if (!\is_array($condition)) {
                continue;
            }

            if (!$this->evaluate($condition, $data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 求值单条条件。
     *
     * @param array<string, mixed> $condition 形如 {field, operator, value}
     * @param array<string, mixed> $data      提交数据
     */
    public function evaluate(array $condition, array $data): bool
    {
        $field    = (string) ($condition['field'] ?? '');
        $operator = (string) ($condition['operator'] ?? '=');
        $expected = $condition['value'] ?? null;
        $actual   = $field === '' ? null : ($data[$field] ?? null);

        return match ($operator) {
            '='      => $this->equals($actual, $expected),
            '!='     => !$this->equals($actual, $expected),
            '>'      => $this->compare($actual, $expected) > 0,
            '>='     => $this->compare($actual, $expected) >= 0,
            '<'      => $this->compare($actual, $expected) < 0,
            '<='     => $this->compare($actual, $expected) <= 0,
            'in'     => $this->inList($actual, $expected),
            'not_in' => !$this->inList($actual, $expected),
            // 未知操作符一律视为不成立，避免"因拼错操作符而放宽校验"
            default  => false,
        };
    }

    /**
     * 宽松相等：先归一化再严格比较，容忍表单数据的类型差异
     * （`'1'` / `1` / `true` 视为相等，`'0'` / `0` / `false` 视为相等，缺失字段视为空串）。
     */
    private function equals(mixed $actual, mixed $expected): bool
    {
        $left  = $this->normalize($actual);
        $right = $this->normalize($expected);

        // 数组之间用宽松比较（多选字段）；数组与标量不相等
        if (\is_array($left) || \is_array($right)) {
            return \is_array($left) && \is_array($right) && $left == $right;
        }

        return $left === $right;
    }

    /**
     * 比较：双方均为数值时按数值比较，否则按字符串比较
     * （`Y-m-d` 等格式的字典序与时序一致，故日期也可用）。
     */
    private function compare(mixed $actual, mixed $expected): int
    {
        $left  = $this->normalize($actual);
        $right = $this->normalize($expected);

        if (\is_array($left) || \is_array($right)) {
            return 0;
        }

        if (\is_numeric($left) && \is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return (string) $left <=> (string) $right;
    }

    /**
     * 列表成员判断。
     *
     * - `$expected` 支持数组或 `"a,b,c"` 逗号串
     * - `$actual` 为数组时（多选字段），任一元素命中即成立
     */
    private function inList(mixed $actual, mixed $expected): bool
    {
        $list = $this->normalizeList($expected);

        if ($list === []) {
            return false;
        }

        if (\is_array($actual)) {
            foreach ($actual as $item) {
                foreach ($list as $candidate) {
                    if ($this->equals($item, $candidate)) {
                        return true;
                    }
                }
            }

            return false;
        }

        foreach ($list as $candidate) {
            if ($this->equals($actual, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeList(mixed $value): array
    {
        if (\is_array($value)) {
            return \array_values($value);
        }

        if ($value === null || $value === '') {
            return [];
        }

        if (\is_string($value) && \str_contains($value, ',')) {
            return \array_map('trim', \explode(',', $value));
        }

        return [$value];
    }

    /**
     * 标量归一化：null → ''，true → '1'，false → '0'，其余标量转字符串。
     */
    private function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if ($value === true) {
            return '1';
        }

        if ($value === false) {
            return '0';
        }

        return \is_scalar($value) ? (string) $value : $value;
    }
}
