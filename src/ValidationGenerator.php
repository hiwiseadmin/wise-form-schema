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
    /**
     * 字段名是否可安全用作 ThinkPHP 点语法规则键
     */
    private static function isValidRuleKey(string $name): bool
    {
        return $name !== '' && \preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $name) === 1;
    }

    /**
     * 自定义规则串是否可安全交给 think-validate
     *
     * 允许：内置规则名 / 自定义 `check{Name}` 规则名（标识符）、`type:arg` 形式、
     * 以及显式 `regex:...`。拒绝：裸字符串中夹带正则元字符 ——
     * think-validate 对无法识别的裸规则会按正则执行，是配置驱动的 ReDoS 入口。
     */
    private static function isSafeCustomRule(string $rule): bool
    {
        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $rule) === 1) {
            return true;
        }

        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*:/', $rule) === 1) {
            return true;
        }

        return false;
    }

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

        foreach ($schema['fields'] as $fieldName => $fieldDef) {
            if (!\is_array($fieldDef)) {
                continue;
            }

            if (!self::isValidRuleKey((string) $fieldName)) {
                \error_log('[form-schema] 非法字段名，已跳过其校验规则: ' . (string) $fieldName);
                continue;
            }

            $rule = self::buildFieldRule($fieldDef);
            if ($rule !== '') {
                $rules[$fieldName] = $rule;
            }
        }

        if (isset($schema['repeaters']) && \is_array($schema['repeaters'])) {
            foreach ($schema['repeaters'] as $repeaterName => $repeaterDef) {
                if (!isset($repeaterDef['fields']) || !\is_array($repeaterDef['fields'])) {
                    continue;
                }
                foreach ($repeaterDef['fields'] as $subFieldName => $subFieldDef) {
                    if (!\is_array($subFieldDef)) {
                        continue;
                    }

                    if (!self::isValidRuleKey((string) $repeaterName)
                        || !self::isValidRuleKey((string) $subFieldName)) {
                        \error_log(sprintf(
                            '[form-schema] 非法字段名，已跳过其校验规则: %s.*.%s',
                            (string) $repeaterName,
                            (string) $subFieldName
                        ));
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

        if (!empty($fieldDef['required'])) {
            $ruleParts[] = 'require';
        }

        $type = $fieldDef['type'] ?? 'text';
        if (isset(self::$typeRuleMap[$type])) {
            $rule = self::$typeRuleMap[$type];

            $isRange = !empty($fieldDef['props']['range']);
            if ($isRange) {
                $rule = 'array';
            }

            if (!$isRange && \in_array($type, self::$multipleAwareTypes, true)) {
                $isMultiple = !empty($fieldDef['props']['multiple']);
                $rule = $isMultiple ? 'array' : $rule;
            }

            $ruleParts[] = $rule;
        }

        if (!empty($fieldDef['rules']) && \is_array($fieldDef['rules'])) {
            foreach ($fieldDef['rules'] as $customRule) {
                if (!\is_string($customRule) || $customRule === '') {
                    continue;
                }

                $normalized = self::normalizeRuleName($customRule);

                if (!self::isSafeCustomRule($normalized)) {
                    \error_log('[form-schema] 不安全的校验规则已忽略: ' . $normalized);
                    continue;
                }

                $ruleParts[] = $normalized;
            }
        }

        $ruleParts = \array_values(\array_unique($ruleParts));

        $requiredPos = \array_search('require', $ruleParts, true);
        if ($requiredPos !== false && $requiredPos !== 0) {
            unset($ruleParts[$requiredPos]);
            \array_unshift($ruleParts, 'require');
        }

        return $ruleParts !== [] ? \implode('|', $ruleParts) : '';
    }
}
