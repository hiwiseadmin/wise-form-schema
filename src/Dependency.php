<?php

declare(strict_types=1);

namespace wise\form\schema;

/**
 * Dependency — 字段联动规则值对象。
 *
 * 描述一个字段对另一个字段的依赖关系，例如：
 * - 当 fieldA 的值为 X 时，显示/隐藏/启用/禁用当前字段。
 *
 * 纯元数据载体，不做运行时执行。
 */
class Dependency
{
    /**
     * @param string $field    依赖的字段名
     * @param string $operator 操作符（'=', '!=', 'in', 'not_in', '>', '<', '>=', '<='）
     * @param mixed  $value    期望值
     * @param string $action   触发动作（'show', 'hide', 'enable', 'disable'）
     */
    public function __construct(
        public readonly string $field,
        public readonly string $operator = '=',
        public readonly mixed $value = '',
        public readonly string $action = 'show'
    ) {
    }

    /**
     * 转为数组表示。
     *
     * @return array{field: string, operator: string, value: mixed, action: string}
     */
    public function toArray(): array
    {
        return [
            'field'    => $this->field,
            'operator' => $this->operator,
            'value'    => $this->value,
            'action'   => $this->action,
        ];
    }
}
