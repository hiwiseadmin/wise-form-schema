<?php

declare(strict_types=1);

namespace wise\form\schema;

use wise\form\schema\exception\SchemaException;

/**
 * ValidationGenerator — 从 Schema 自动生成验证规则数组。
 *
 * 支持传入 Form 对象或 toArray() 后的 Schema 数组。
 * 自动根据字段类型映射基础规则，合并用户自定义规则，处理嵌套 Repeater 点语法。
 */
class ValidationGenerator
{
    /**
     * 字段类型 → 基础验证规则映射。
     */
    private static array $typeRuleMap = [
        'text'     => 'string',
        'textarea' => 'string',
        'number'   => 'number',
        'date'     => 'date',
        'datetime' => 'date',
        'time'     => 'date',
        'checkbox' => 'array',
        'radio'    => 'string',
        'select'   => 'string',
        'switch'   => 'boolean',
        'file'     => 'string',
        'hidden'   => 'string',
        'image'    => 'string',
        'editor'   => 'string',
        'color'    => 'string',
        'password' => 'string',
        'email'    => 'email',
    ];

    /**
     * 支持 multiple 模式的类型列表。
     *
     * 这些类型在 props.multiple === true 时验证规则应变为 array。
     */
    private static array $multipleAwareTypes = ['image', 'file', 'select'];

    /**
     * Laravel 风格规则名 → ThinkPHP 风格别名。
     *
     * 用户可能带着 Laravel 习惯写 `->rules('required')`，而 TP 只认 `require`；
     * 未知规则名会被 think-validate 当作正则处理，导致校验静默失效，故在此统一归一化。
     */
    private static array $ruleAliasMap = [
        'required' => 'require',
    ];

    /**
     * 归一化单条规则的名称（保留其参数部分）。
     *
     * 例：`required` → `require`；`max:100` → `max:100`（无别名时原样返回）。
     */
    private static function normalizeRuleName(string $rule): string
    {
        $name  = $rule;
        $param = '';

        if (\str_contains($rule, ':')) {
            [$name, $rawParam] = \explode(':', $rule, 2);
            $param             = ':' . $rawParam;
        }

        return (self::$ruleAliasMap[\strtolower($name)] ?? $name) . $param;
    }

    /**
     * 从 Form 对象或 Schema 数组生成验证规则。
     *
     * 生成的规则为 ThinkPHP 风格（require / string / number / array / boolean / date / email），
     * 可直接交给 think\Validate 使用；无任何规则的字段不会出现在结果中。
     *
     * @param array|Form $source Form 实例或 toArray() 输出的 Schema 数组
     * @return array<string, string> ['field' => 'rule1|rule2', ...]
     * @throws SchemaException
     */
    public static function generate(array|Form $source): array
    {
        $schema = $source instanceof Form ? $source->toArray() : $source;

        if (!isset($schema['fields']) || !\is_array($schema['fields'])) {
            return [];
        }

        $rules = [];

        // 处理顶层字段
        foreach ($schema['fields'] as $fieldName => $fieldDef) {
            if (!\is_array($fieldDef)) {
                continue;
            }
            $rule = self::buildFieldRule($fieldDef);
            // 无规则字段不写入，避免产生 ThinkPHP 无法识别的空规则
            if ($rule !== '') {
                $rules[$fieldName] = $rule;
            }
        }

        // 处理 Repeater 子字段（使用点语法）
        if (isset($schema['repeaters']) && \is_array($schema['repeaters'])) {
            foreach ($schema['repeaters'] as $repeaterName => $repeaterDef) {
                if (!isset($repeaterDef['fields']) || !\is_array($repeaterDef['fields'])) {
                    continue;
                }
                foreach ($repeaterDef['fields'] as $subFieldName => $subFieldDef) {
                    if (!\is_array($subFieldDef)) {
                        continue;
                    }
                    $rule = self::buildFieldRule($subFieldDef);
                    if ($rule !== '') {
                        $rules[$repeaterName . '.*.' . $subFieldName] = $rule;
                    }
                }
            }
        }

        return $rules;
    }

    /**
     * 生成"针对某份提交数据"的有效验证规则（消费 conditional_rules）。
     *
     * 与 generate() 的区别：本方法会额外读取字段的 `conditional_rules`
     * （由 AbstractField::requiredIf() / requiredUnless() 产生），
     * 当条件对传入的 $data 成立时，把 `require` 折叠进该字段的有效规则，
     * 使「条件必填」在**服务端真正生效**。
     *
     * ```php
     * // 声明：企业名称在企业类型下必填
     * Text::make('company_name')->requiredIf('type', '=', 'company');
     *
     * // 求值
     * ValidationGenerator::generateFor($schema, ['type' => 'company']);
     * // → ['type' => 'string', 'company_name' => 'require|string']
     *
     * ValidationGenerator::generateFor($schema, ['type' => 'personal']);
     * // → ['type' => 'string', 'company_name' => 'string']
     * ```
     *
     * ⚠️ **Repeater 子字段的条件必填暂不支持**：ThinkPHP 的规则串（`items.*.field`）
     * 无法表达"逐行按数据求值"的语义，需要逐行校验时请自行在业务层处理。
     *
     * **空可重复组容器**：当 `$data[repeater]` 是空数组（`[]`）时，其子字段规则会被摘除
     * （无行 = 无必填，真空满足）；但容器**缺失**时规则保留，`require` 会照常报错。
     *
     * @param array|Form          $source Form 实例或 toArray() 输出的 Schema 数组
     * @param array<string, mixed> $data  本次提交的数据（条件求值依据）
     * @return array<string, string> ['field' => 'rule1|rule2', ...]
     * @throws SchemaException
     */
    public static function generateFor(array|Form $source, array $data): array
    {
        $schema = $source instanceof Form ? $source->toArray() : $source;
        $rules  = self::generate($schema);

        $conditional = self::collectConditionalRules($schema);

        if ($conditional !== []) {
            $evaluator = new ConditionEvaluator();

            foreach ($conditional as $fieldName => $conditions) {
                if (!$evaluator->evaluateAll($conditions, $data)) {
                    continue;
                }

                $parts = isset($rules[$fieldName]) && $rules[$fieldName] !== ''
                    ? \explode('|', $rules[$fieldName])
                    : [];

                if (!\in_array('require', $parts, true)) {
                    \array_unshift($parts, 'require');
                }

                $rules[$fieldName] = \implode('|', $parts);
            }
        }

        // 空可重复组容器：子字段规则应真空满足（无行 = 无必填）。
        // think-validate 对 `items.*.sku` 的 require 无法区分「容器缺失」与「容器为空」，
        // 两种情况都会报错；这里在规则生成阶段就把「容器为空」的子字段规则摘除，
        // 保留「容器缺失」时的报错（漏传容器仍会如实提示）。
        foreach (($schema['repeaters'] ?? []) as $repeaterName => $repeaterDef) {
            if (!\is_array($repeaterDef) || !isset($repeaterDef['fields']) || !\is_array($repeaterDef['fields'])) {
                continue;
            }

            if (($data[$repeaterName] ?? null) !== []) {
                continue;
            }

            foreach (\array_keys($repeaterDef['fields']) as $subFieldName) {
                unset($rules[$repeaterName . '.*.' . $subFieldName]);
            }
        }

        return $rules;
    }

    /**
     * 收集顶层字段的 conditional_rules。
     *
     * @param array<string, mixed> $schema
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function collectConditionalRules(array $schema): array
    {
        $result = [];

        foreach (($schema['fields'] ?? []) as $fieldName => $fieldDef) {
            if (!\is_array($fieldDef)) {
                continue;
            }

            $conditionalRules = $fieldDef['conditional_rules'] ?? null;

            if (\is_array($conditionalRules) && $conditionalRules !== []) {
                $result[(string) $fieldName] = $conditionalRules;
            }
        }

        return $result;
    }

    /**
     * 根据字段定义构建验证规则字符串。
     *
     * @param array $fieldDef 单字段的 toArray() 输出
     * @return string
     */
    private static function buildFieldRule(array $fieldDef): string
    {
        $ruleParts = [];

        // 1. 必填规则
        //    ThinkPHP 的规则名是 require（Laravel 才是 required）。
        //    若写成 required，think-validate 会把它当正则表达式处理，
        //    导致必填校验既不生效也不报错（静默失效）。
        if (!empty($fieldDef['required'])) {
            $ruleParts[] = 'require';
        }

        // 2. 类型规则（考虑 multiple / range 模式）
        $type = $fieldDef['type'] ?? 'text';
        if (isset(self::$typeRuleMap[$type])) {
            $rule = self::$typeRuleMap[$type];

            // range 模式：区间值始终为数组
            $isRange = !empty($fieldDef['props']['range']);
            if ($isRange) {
                $rule = 'array';
            }

            // image / file / select 在 multiple 模式下使用 array 规则
            if (!$isRange && \in_array($type, self::$multipleAwareTypes, true)) {
                $isMultiple = !empty($fieldDef['props']['multiple']);
                $rule = $isMultiple ? 'array' : $rule;
            }

            $ruleParts[] = $rule;
        }

        // 3. 用户自定义规则（归一化 Laravel 风格规则名）
        if (!empty($fieldDef['rules']) && \is_array($fieldDef['rules'])) {
            foreach ($fieldDef['rules'] as $customRule) {
                if (\is_string($customRule) && $customRule !== '') {
                    $ruleParts[] = self::normalizeRuleName($customRule);
                }
            }
        }

        // 去重：`->required()` 与 `->rules('required')` 同时出现时只会保留一条 require
        $ruleParts = \array_values(\array_unique($ruleParts));

        // 规范输出顺序：require 始终在最前，保证「显式 ->required()」与
        // 「->rules('required')」两条路径产出一致，便于比对与断言
        $requiredPos = \array_search('require', $ruleParts, true);
        if ($requiredPos !== false && $requiredPos !== 0) {
            unset($ruleParts[$requiredPos]);
            \array_unshift($ruleParts, 'require');
        }

        // 无任何规则时返回空串（由 generate() 跳过该字段）。
        // ThinkPHP 没有 nullable 规则，若写入会退化为正则匹配。
        return $ruleParts !== [] ? \implode('|', $ruleParts) : '';
    }
}
