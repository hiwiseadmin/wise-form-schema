<?php

declare(strict_types=1);

namespace wise\form\schema\renderer\html;

use wise\form\schema\renderer\html\theme\Bootstrap5Theme;
use wise\form\schema\renderer\RendererInterface;

/**
 * HtmlRenderer — 把 Schema 渲染为 HTML 表单的**编排层**（框架无关）。
 *
 * 职责边界：
 *   - 本类只负责"行为"：字段分组、前端校验规则生成、Dependency → 前端联动条件转换
 *   - 标记结构与 CSS class 交给 {@see FormThemeInterface}（默认 {@see Bootstrap5Theme}）
 *
 * 输出的是**表单内部 HTML**（不含 `<form>` 标签、提交按钮、卡片外壳），由调用方包裹。
 * 典型用法：
 * ```php
 * $html = (new HtmlRenderer())->render($form->toArray(), ['values' => $row ?? []]);
 * ```
 *
 * 前端契约（默认主题面向 wiseadmin 后台，与 wise-form-validator / bootstrap-form 对齐）：
 *   - 校验：控件上 `data-validation="required|maxLength:100"`，消息用 `data-validation-message-{rule}`
 *   - 联动：字段行容器上 `data-conditions='[{"field":..,"operator":"equals","value":..,"relation":"AND"}]'`
 *
 * ⚠️ 前端校验只是 UX 便利，**后端校验才是权威**。无法被前端表达的后端规则会被跳过，
 * 仅由 {@see \wise\form\schema\ValidationGenerator} 生成的规则在服务端强制。
 *
 * 子类可通过覆写 buildValidation() / mapRuleToFrontend() / mapCondition() 定制映射策略。
 */
class HtmlRenderer implements RendererInterface
{
    private FormThemeInterface $theme;

    /**
     * @param FormThemeInterface|null $theme 默认主题；为 null 时使用 Bootstrap5Theme
     */
    public function __construct(?FormThemeInterface $theme = null)
    {
        $this->theme = $theme ?? new Bootstrap5Theme();
    }

    /**
     * 渲染 Schema。
     *
     * @param array<string, mixed> $schema  Form::toArray() 输出
     * @param array<string, mixed> $context 支持：
     *                                      - theme:  FormThemeInterface  覆盖默认主题
     *                                      - values: array               当前数据（字段名 => 值），用于回显
     * @return string 表单内部 HTML；无字段时返回空串
     */
    public function render(array $schema, array $context = []): string
    {
        $theme  = $context['theme'] ?? $this->theme;
        $values = is_array($context['values'] ?? null) ? $context['values'] : [];
        $fields = $schema['fields'] ?? [];

        if (!is_array($fields) || $fields === []) {
            return '';
        }

        $groupsMeta = is_array($schema['groups'] ?? null) ? $schema['groups'] : [];
        $buckets    = $this->bucketByGroup($fields, $groupsMeta);

        $html = '';

        foreach ($buckets as $groupName => $groupFields) {
            $rows = '';

            foreach ($groupFields as $name => $field) {
                if (!is_array($field)) {
                    continue;
                }

                $fieldContext = $this->buildContext((string) $name, $field, $values);
                $control      = $theme->control($field, $fieldContext);
                $rows        .= $theme->field($field, $control, $fieldContext);
            }

            $html .= $theme->group($groupName, $groupsMeta[$groupName] ?? [], $rows, $context);
        }

        return $html;
    }

    /**
     * 按分组切分字段，保持 groups 的声明顺序，未分组字段置底。
     *
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $groupsMeta
     * @return array<string, array<string, mixed>> 分组名 => (字段名 => 字段定义)
     */
    protected function bucketByGroup(array $fields, array $groupsMeta): array
    {
        $buckets = [];

        // 先按声明顺序建立空桶，保证输出顺序稳定
        foreach ($groupsMeta as $groupName => $meta) {
            $buckets[(string) $groupName] = [];
        }

        $ungrouped = [];

        foreach ($fields as $name => $field) {
            if (!is_array($field)) {
                continue;
            }

            $groupName = $field['group'] ?? null;

            if (is_string($groupName) && $groupName !== '' && array_key_exists($groupName, $buckets)) {
                $buckets[$groupName][$name] = $field;
            } else {
                $ungrouped[$name] = $field;
            }
        }

        // 丢弃空分组
        $buckets = array_filter($buckets, static fn (array $groupFields): bool => $groupFields !== []);

        // 未分组字段追加在最后
        if ($ungrouped !== []) {
            $buckets[''] = $ungrouped;
        }

        return $buckets;
    }

    /**
     * 构建单字段的渲染上下文。
     *
     * @param array<string, mixed> $field
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    protected function buildContext(string $name, array $field, array $values): array
    {
        $type     = (string) ($field['type'] ?? 'text');
        $props    = is_array($field['props'] ?? null) ? $field['props'] : [];
        $label    = (string) ($field['label'] ?? $name);
        $required = !empty($field['required']);
        $multiple = !empty($props['multiple']);

        $value = array_key_exists($name, $values) ? $values[$name] : ($field['default'] ?? '');

        $id = 'wsf-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $name);

        [$validation, $messages] = $this->buildValidation($field, $type, $required, $label);

        // 字段级自定义消息覆盖：调用方可在 schema 里注入 $field['messages']
        if (is_array($field['messages'] ?? null)) {
            /** @var array<string, string> $override */
            $override = $field['messages'];
            $messages = array_merge($messages, $override);
        }

        $attrs = [
            'id'   => $id,
            'name' => $this->resolveFieldName($name, $type, $multiple),
        ];

        if ($validation !== '') {
            $attrs['data-validation'] = $validation;
        }

        foreach ($messages as $rule => $message) {
            $attrs['data-validation-message-' . $rule] = $message;
        }

        return [
            'name'        => $name,
            'id'          => $id,
            'value'       => $value,
            'label'       => $label,
            'placeholder' => (string) ($field['placeholder'] ?? ''),
            'help'        => (string) ($field['help'] ?? ''),
            'type'        => $type,
            'required'    => $required,
            'multiple'    => $multiple,
            'options'     => is_array($props['options'] ?? null) ? $props['options'] : [],
            'min'         => $props['min'] ?? null,
            'max'         => $props['max'] ?? null,
            'step'        => $props['step'] ?? null,
            'format'      => $props['format'] ?? null,
            'widget'      => $props['widget'] ?? null,
            'validation'  => $validation,
            'messages'    => $messages,
            'conditions'  => $this->buildConditions($field['dependencies'] ?? null),
            'attrs'       => $attrs,
        ];
    }

    /**
     * 解析表单提交时的字段名。
     *
     * 多值字段（多选 select / checkbox 组 / 多图 / 多文件）需使用 `name[]`，
     * 否则后端只会收到最后一个值，且 {@see \wise\form\schema\DataFilter} 的数组规则无法命中。
     */
    protected function resolveFieldName(string $name, string $type, bool $multiple): string
    {
        if ($multiple && in_array($type, ['select', 'checkbox', 'image', 'file'], true)) {
            return $name . '[]';
        }

        return $name;
    }

    /**
     * 生成前端校验规则串与对应消息表。
     *
     * 后端（ThinkPHP：require/string/array…）与前端（ws-form-validator：required/minLength…）
     * 的规则词汇表不同，此处做**保守映射**：只映射语义明确的一对一（或一对二）规则，
     * 其余一律跳过，交由后端强制。
     *
     * @param array<string, mixed> $field
     * @return array{0: string, 1: array<string, string>} [规则串, 消息表]
     */
    protected function buildValidation(array $field, string $type, bool $required, string $label): array
    {
        $parts    = [];
        $messages = [];

        if ($required) {
            $parts[]              = 'required';
            $messages['required'] = $this->defaultMessage('required', $label, $type);
        }

        foreach ($this->typeRules($type) as $rule) {
            $parts[]          = $rule;
            $messages[$rule] = $this->defaultMessage($rule, $label, $type);
        }

        // 数值字段：把 props.min / props.max 也纳入前端校验，
        // 与手写表单的 data-validation="required|min:0|max:9999" 保持一致。
        // 仅限 number 类型——前端 min/max 走 parseFloat，用于日期会得到 NaN。
        if ($type === 'number') {
            $props = is_array($field['props'] ?? null) ? $field['props'] : [];

            foreach (['min', 'max'] as $bound) {
                if (isset($props[$bound]) && is_numeric($props[$bound])) {
                    $boundRule = $bound . ':' . $props[$bound];
                    $parts[]   = $boundRule;

                    if (!isset($messages[$bound])) {
                        $messages[$bound] = $this->defaultMessage($boundRule, $label, $type);
                    }
                }
            }
        }

        foreach ((array) ($field['rules'] ?? []) as $rule) {
            if (!is_string($rule) || $rule === '') {
                continue;
            }

            foreach ($this->mapRuleToFrontend($rule, $type) as $frontRule) {
                $parts[]  = $frontRule;
                $ruleName = $this->ruleNameOf($frontRule);

                if (!isset($messages[$ruleName])) {
                    $messages[$ruleName] = $this->defaultMessage($frontRule, $label, $type);
                }
            }
        }

        return [implode('|', array_values(array_unique($parts))), $messages];
    }

    /**
     * 字段类型自带的前端基础规则。
     *
     * @return string[]
     */
    protected function typeRules(string $type): array
    {
        return match ($type) {
            'email'                    => ['email'],
            'number'                   => ['number'],
            'date', 'datetime', 'time' => ['date'],
            default                    => [],
        };
    }

    /**
     * 后端规则 → 前端规则（可能一对多）。
     *
     * 无法被前端表达时返回空数组（如 alpha_dash / unique / in / fileExt 等）。
     *
     * @return string[]
     */
    protected function mapRuleToFrontend(string $rule, string $type): array
    {
        $name  = $rule;
        $param = '';

        if (str_contains($rule, ':')) {
            [$name, $param] = explode(':', $rule, 2);
        }

        $isString = in_array($type, ['text', 'textarea', 'editor', 'email', 'password', 'color', 'hidden'], true);

        return match (strtolower($name)) {
            'require', 'required' => ['required'],
            'email'               => ['email'],
            'number'              => $type === 'number' ? ['number'] : [],
            'date'                => ['date'],
            // 字符串长度用 minLength/maxLength，数值用 min/max
            'max'                 => $isString ? ['maxLength:' . $param] : ['max:' . $param],
            'min'                 => $isString ? ['minLength:' . $param] : ['min:' . $param],
            'between', 'length'   => $isString ? $this->splitRangeRule($param) : [],
            // 前端无对应能力（alpha_dash / unique / in / regex / fileSize / fileExt …）→ 仅后端强制
            default               => [],
        };
    }

    /**
     * 把 `between:3,20` / `length:3,20` 的区间参数拆成 minLength + maxLength。
     *
     * @return string[]
     */
    private function splitRangeRule(string $param): array
    {
        if (!str_contains($param, ',')) {
            return [];
        }

        [$min, $max] = explode(',', $param, 2);

        $rules = [];
        if (is_numeric(trim($min))) {
            $rules[] = 'minLength:' . trim($min);
        }
        if (is_numeric(trim($max))) {
            $rules[] = 'maxLength:' . trim($max);
        }

        return $rules;
    }

    /**
     * 取规则名（去掉参数部分）。
     *
     * 前端按**规则名**读取消息属性，即 `data-validation-message-{规则名}`，
     * 因此 `maxLength:100` 对应的消息键是 `maxLength`（不含参数）。
     */
    protected function ruleNameOf(string $rule): string
    {
        return str_contains($rule, ':') ? explode(':', $rule, 2)[0] : $rule;
    }

    /**
     * 生成前端默认错误消息。
     *
     * @param string $frontRule 前端规则名（可含参数，如 `maxLength:100`）
     */
    protected function defaultMessage(string $frontRule, string $label, string $type): string
    {
        $key = $this->ruleNameOf($frontRule);

        $param = str_contains($frontRule, ':') ? explode(':', $frontRule, 2)[1] : '';

        $isChoice = in_array(
            $type,
            ['select', 'radio', 'checkbox', 'switch', 'date', 'datetime', 'time', 'image', 'file'],
            true
        );
        $verb = $isChoice ? '请选择' : '请输入';

        return match ($key) {
            'required'  => $verb . $label,
            'email'     => '请输入有效的邮箱地址',
            'number'    => '请输入有效的数字',
            'date'      => '请选择有效的日期',
            'maxLength' => $label . '最多 ' . $param . ' 个字符',
            'minLength' => $label . '至少 ' . $param . ' 个字符',
            'max'       => $label . '不能大于 ' . $param,
            'min'       => $label . '不能小于 ' . $param,
            default     => '输入无效',
        };
    }

    /**
     * 将 Dependency 元数据转换为前端联动条件（bootstrap-form.js 的 data-conditions）。
     *
     * 前端能力边界（见 public/statics/libs/bootstrap-form/bootstrap-form.js）：
     *   - **操作符**：仅 equals / not_equals / contains / not_contains / greater_than / less_than
     *   - **语义**：前端只实现"不满足即隐藏（并禁用）"，因此 action=hide 用取反表达；
     *     action=enable / disable 无法表达
     *   - **relation**：前端仅识别 'AND' / 'OR'，缺省会被静默忽略（条件失效），故必须显式给出
     *
     * 任何无法忠实表达的条件都会被丢弃，避免产出"看起来生效其实不生效"的假联动。
     *
     * @param mixed $dependencies schema 中的 dependencies 数组
     * @return array<int, array<string, mixed>>|null
     */
    protected function buildConditions(mixed $dependencies): ?array
    {
        if (!is_array($dependencies) || $dependencies === []) {
            return null;
        }

        $conditions = [];

        foreach ($dependencies as $dependency) {
            if (!is_array($dependency)) {
                continue;
            }

            $mapped = $this->mapCondition($dependency);

            if ($mapped !== null) {
                $conditions[] = $mapped;
            }
        }

        return $conditions === [] ? null : $conditions;
    }

    /**
     * 转换单条 Dependency；无法忠实表达时返回 null。
     *
     * @param array<string, mixed> $dependency
     * @return array<string, mixed>|null
     */
    protected function mapCondition(array $dependency): ?array
    {
        $field    = (string) ($dependency['field'] ?? '');
        $operator = (string) ($dependency['operator'] ?? '=');
        $action   = (string) ($dependency['action'] ?? 'show');

        if ($field === '') {
            return null;
        }

        // enable / disable：前端把"禁用"与"隐藏"绑在一起，无法单独表达
        if (!in_array($action, ['show', 'hide'], true)) {
            return null;
        }

        // >= / <= / in / not_in 前端均无对应操作符
        $operatorMap = [
            '='  => 'equals',
            '!=' => 'not_equals',
            '>'  => 'greater_than',
            '<'  => 'less_than',
        ];

        if (!isset($operatorMap[$operator])) {
            return null;
        }

        $frontOperator = $operatorMap[$operator];

        if ($action === 'hide') {
            $negation = [
                'equals'     => 'not_equals',
                'not_equals' => 'equals',
            ];

            // 比较类操作符取反会改变语义（非 "> a" 等价于 "<= a"，而非 "< a"），故不支持
            if (!isset($negation[$frontOperator])) {
                return null;
            }

            $frontOperator = $negation[$frontOperator];
        }

        return [
            'field'    => $field,
            'operator' => $frontOperator,
            'value'    => $dependency['value'] ?? '',
            'relation' => 'AND',
        ];
    }
}
