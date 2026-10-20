<?php

declare(strict_types=1);

namespace wise\form\schema;

use wise\form\schema\exception\SchemaException;

/**
 * DataFilter — 白名单数据过滤与类型转换。
 *
 * 1. 仅保留 Schema 中定义的字段（静默丢弃未定义字段）。
 * 2. 根据字段类型执行类型转换。
 * 3. 递归处理嵌套 Repeater 数据。
 */
class DataFilter
{
    /**
     * 对输入数据按 Schema 进行过滤和类型转换。
     *
     * @param array $schema Form::toArray() 输出的 Schema 数组
     * @param array $input  待过滤的原始输入数据
     * @return array 过滤并转换后的数据
     * @throws SchemaException
     */
    public function filter(array $schema, array $input): array
    {
        $definedFields = $schema['fields'] ?? [];
        $repeaters = $schema['repeaters'] ?? [];

        // 1. 仅保留顶层已定义字段
        $filtered = $this->filterBySchema($definedFields, $input);

        // 2. 处理 Repeater 数据
        foreach ($repeaters as $repeaterName => $repeaterDef) {
            if (!isset($input[$repeaterName])) {
                continue;
            }

            $repeaterFields = $repeaterDef['fields'] ?? [];

            if (\is_array($input[$repeaterName])) {
                // 判断是否为数组的数组（多行）还是单个关联数组
                if ($this->isIndexedArray($input[$repeaterName])) {
                    $filtered[$repeaterName] = [];
                    foreach ($input[$repeaterName] as $index => $row) {
                        if (\is_array($row)) {
                            $filtered[$repeaterName][$index] = $this->filterBySchema($repeaterFields, $row);
                        }
                    }
                } else {
                    // 单个关联数组
                    $filtered[$repeaterName] = $this->filterBySchema($repeaterFields, $input[$repeaterName]);
                }
            }
        }

        return $filtered;
    }

    /**
     * 根据字段定义白名单过滤并转换单层数据。
     *
     * @param array $fieldDefs 字段定义数组（keyed by field name）
     * @param array $data      原始输入数据
     * @return array
     */
    private function filterBySchema(array $fieldDefs, array $data): array
    {
        $result = [];

        foreach ($fieldDefs as $fieldName => $fieldDef) {
            if (!\is_array($fieldDef)) {
                continue;
            }

            $rawValue = \array_key_exists($fieldName, $data)
                ? $data[$fieldName]
                : ($fieldDef['default'] ?? $this->getDefaultForType($fieldDef['type'] ?? 'text'));

            $result[$fieldName] = $this->castValue($rawValue, $fieldDef);
        }

        return $result;
    }

    /**
     * 判断数组是否为索引数组（非关联数组）。
     *
     * @param array $array
     * @return bool
     */
    private function isIndexedArray(array $array): bool
    {
        return \array_keys($array) === \range(0, \count($array) - 1);
    }

    /**
     * 根据字段定义对值进行类型转换。
     *
     * @param mixed $value    原始值
     * @param array $fieldDef 字段定义
     * @return mixed
     */
    private function castValue(mixed $value, array $fieldDef): mixed
    {
        $type = $fieldDef['type'] ?? 'text';
        $isMultiple = !empty($fieldDef['props']['multiple']);
        $isRange = !empty($fieldDef['props']['range']);

        // range 模式（date / datetime / time）：始终转为数组
        if ($isRange) {
            return $this->toArrayValue($value);
        }

        return match ($type) {
            'number' => $this->castNumber($value),
            'checkbox' => $this->toArrayValue($value),
            'switch' => (bool) $value,
            // image / file / select 在 multiple 模式下转为数组
            'image', 'file', 'select' => $isMultiple
                ? $this->toArrayValue($value)
                : ($value === null ? null : (string) $value),
            'text', 'textarea', 'radio', 'date', 'datetime', 'time', 'hidden',
            'editor', 'color', 'password', 'email'
                => $value === null ? null : (string) $value,
            default => $value,
        };
    }

    /**
     * 将值转换为数字（整数或浮点数）。
     *
     * 非数值输入返回 null，避免静默转为 0。
     *
     * @param mixed $value
     * @return int|float|null
     */
    private function castNumber(mixed $value): int|float|null
    {
        if (\is_int($value) || \is_float($value)) {
            return $value;
        }

        if (\is_string($value) && \is_numeric($value)) {
            // 含小数点 → float，否则 int
            return \str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // 非数值输入返回 null，由上层校验层处理
        return null;
    }

    /**
     * 根据字段类型返回合理的默认值（作为 schema 缺少 default 时的最终回退）。
     *
     * @param string $type 字段类型
     * @return mixed
     */
    private function getDefaultForType(string $type): mixed
    {
        return match ($type) {
            'number'   => null,
            'checkbox' => [],
            'switch'   => false,
            default    => '',
        };
    }

    /**
     * 将标量值安全转为数组。
     *
     * - 已是数组原样返回
     * - 空字符串 / null → []
     * - 其余 → [$value]
     *
     * @param mixed $value
     * @return array
     */
    private function toArrayValue(mixed $value): array
    {
        if (\is_array($value)) {
            return $value;
        }
        if ($value === '' || $value === null) {
            return [];
        }
        return [$value];
    }
}
