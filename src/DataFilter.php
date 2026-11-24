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
     * @param array $schema        Form::toArray() 输出的 Schema 数组
     * @param array $input         待过滤的原始输入数据
     * @param bool  $applyDefaults 未提交字段是否补默认值。
     *                             整页提交用 true（默认，保持既有行为）；
     *                             局部更新（PATCH）必须传 false，否则未提交的列会被静默写成默认值。
     * @return array 过滤并转换后的数据
     * @throws SchemaException
     */
    public function filter(array $schema, array $input, bool $applyDefaults = true): array
    {
        $definedFields = $schema['fields'] ?? [];
        $repeaters = $schema['repeaters'] ?? [];

        $filtered = $this->filterBySchema($definedFields, $input, $applyDefaults);

        foreach ($repeaters as $repeaterName => $repeaterDef) {
            if (!isset($input[$repeaterName])) {
                continue;
            }

            $repeaterFields = $repeaterDef['fields'] ?? [];

            if (\is_array($input[$repeaterName])) {
                if ($this->isIndexedArray($input[$repeaterName])) {
                    $filtered[$repeaterName] = [];
                    foreach ($input[$repeaterName] as $index => $row) {
                        if (\is_array($row)) {
                            $filtered[$repeaterName][$index] = $this->filterBySchema($repeaterFields, $row, $applyDefaults);
                        }
                    }
                } else {
                    $filtered[$repeaterName] = $this->filterBySchema($repeaterFields, $input[$repeaterName], $applyDefaults);
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
    private function filterBySchema(array $fieldDefs, array $data, bool $applyDefaults = true): array
    {
        $result = [];

        foreach ($fieldDefs as $fieldName => $fieldDef) {
            if (!\is_array($fieldDef)) {
                continue;
            }

            if (!\array_key_exists($fieldName, $data)) {
                if (!$applyDefaults) {
                    continue;
                }

                $rawValue = $fieldDef['default'] ?? $this->getDefaultForType($fieldDef['type'] ?? 'text');
            } else {
                $rawValue = $data[$fieldName];
            }

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
        if ($array === []) {
            return true;
        }

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

        if ($isRange) {
            return $this->toArrayValue($value);
        }

        return match ($type) {
            'number' => $this->castNumber($value),
            'checkbox' => $this->toArrayValue($value),
            'switch' => (bool) $value,
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
            return \str_contains($value, '.') ? (float) $value : (int) $value;
        }

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
