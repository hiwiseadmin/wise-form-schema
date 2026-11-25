<?php

declare(strict_types=1);

namespace wise\form\schema\renderer;

/**
 * JsonSchemaRenderer — 将 Schema 数组渲染为 JSON Schema（Draft-07 兼容）字符串。
 *
 * 根据字段类型自动映射 JSON Schema 类型、格式约束和枚举值。
 */
class JsonSchemaRenderer implements RendererInterface
{
    /**
     * {@inheritDoc}
     *
     * @param array $schema  Form::toArray() 输出的 Schema 数组
     * @param array $context 可选上下文（'title' 键可覆写表单标题）
     * @return string JSON Schema 字符串
     */
    public function render(array $schema, array $context = []): string
    {
        $jsonSchema = [
            'type'       => 'object',
            'title'      => $context['title'] ?? ($schema['form'] ?? 'Form'),
            'properties' => [],
        ];

        $requiredFields = [];

        foreach (($schema['fields'] ?? []) as $fieldName => $fieldDef) {
            $property = $this->mapFieldToProperty($fieldDef);
            $jsonSchema['properties'][$fieldName] = $property;
            if (!empty($fieldDef['required'])) {
                $requiredFields[] = $fieldName;
            }
        }

        if (!empty($requiredFields)) {
            $jsonSchema['required'] = $requiredFields;
        }

        $json = \json_encode(
            $jsonSchema,
            \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $json === false ? '{"type":"object","properties":{}}' : $json;
    }

    /**
     * 将单个字段定义映射为 JSON Schema 属性。
     *
     * @param array $fieldDef 单字段的 toArray() 输出
     * @return array JSON Schema 属性定义
     */
    private function mapFieldToProperty(array $fieldDef): array
    {
        $type = $fieldDef['type'] ?? 'text';
        $props = $fieldDef['props'] ?? [];

        $property = match ($type) {
            'number'   => $this->numberProperty($props),
            'switch'   => ['type' => 'boolean'],
            'checkbox' => ['type' => 'array', 'items' => ['type' => 'string']],
            'date'     => $this->dateTimeProperty('date', $props),
            'datetime' => $this->dateTimeProperty('date-time', $props),
            'time'     => $this->dateTimeProperty('time', $props),
            'color'    => ['type' => 'string', 'format' => 'color'],
            'password' => ['type' => 'string', 'format' => 'password'],
            'email'    => ['type' => 'string', 'format' => 'email'],
            'image'    => $this->multiAwareProperty($type, $props),
            'file'     => $this->multiAwareProperty($type, $props),
            'select'   => $this->multiAwareProperty($type, $props),
            default    => ['type' => 'string'],
        };

        if (!empty($fieldDef['label'])) {
            $property['title'] = $fieldDef['label'];
        }

        if (isset($fieldDef['default']) && $fieldDef['default'] !== '') {
            $property['default'] = $fieldDef['default'];
        }

        if ($type === 'radio' && !empty($props['options'])) {
            $property['enum'] = \array_keys($props['options']);
        }

        if (!empty($fieldDef['help'])) {
            $property['description'] = $fieldDef['help'];
        }

        return $property;
    }

    /**
     * 构建 number 类型的 JSON Schema 属性。
     *
     * @param array $props 字段 props 子数组
     * @return array JSON Schema 属性定义
     */
    private function numberProperty(array $props): array
    {
        $property = ['type' => 'number'];
        if (isset($props['step']) && (float) $props['step'] === 1.0) {
            $property['type'] = 'integer';
        }
        if (isset($props['min'])) {
            $property['minimum'] = \is_numeric($props['min']) ? (float) $props['min'] : $props['min'];
        }
        if (isset($props['max'])) {
            $property['maximum'] = \is_numeric($props['max']) ? (float) $props['max'] : $props['max'];
        }
        return $property;
    }

    /**
     * 构建 date / datetime / time 类型的 JSON Schema 属性。
     *
     * - 非 range 模式：{type: string, format: <format>}
     * - range 模式：  {type: array, items: {type: string, format: <format>}}
     *
     * @param string $format JSON Schema 格式名（date / date-time / time）
     * @param array  $props  字段 props 子数组
     * @return array JSON Schema 属性定义
     */
    private function dateTimeProperty(string $format, array $props): array
    {
        $isRange = !empty($props['range']);

        if ($isRange) {
            return [
                'type'  => 'array',
                'items' => ['type' => 'string', 'format' => $format],
            ];
        }

        return ['type' => 'string', 'format' => $format];
    }

    /**
     * 构建 image / file / select 类型的 JSON Schema 属性。
     *
     * - 非 multiple 模式：{type: string}
     *   - select 额外附带 enum（来自 options 的 keys）
     * - multiple 模式：  {type: array, items: {type: string, enum?: [...]}}
     *
     * @param string $type  字段类型字符串
     * @param array  $props 字段 props 子数组
     * @return array JSON Schema 属性定义
     */
    private function multiAwareProperty(string $type, array $props): array
    {
        $isMultiple = !empty($props['multiple']);

        if ($isMultiple) {
            $items = ['type' => 'string'];
            if ($type === 'select' && !empty($props['options'])) {
                $items['enum'] = \array_keys($props['options']);
            }
            return ['type' => 'array', 'items' => $items];
        }

        $property = ['type' => 'string'];
        if ($type === 'select' && !empty($props['options'])) {
            $property['enum'] = \array_keys($props['options']);
        }
        return $property;
    }
}
