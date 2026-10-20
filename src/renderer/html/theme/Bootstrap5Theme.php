<?php

declare(strict_types=1);

namespace wise\form\schema\renderer\html\theme;

use wise\form\schema\renderer\html\AbstractFormTheme;

/**
 * Bootstrap5Theme — Bootstrap 5 表单主题（参考实现）。
 *
 * 目标契约：**WiseAdmin 后台表单**
 *   - 标记：Bootstrap 5（form-control / form-select / form-check / form-switch / invalid-feedback）
 *   - 校验：控件上的 `data-validation` / `data-validation-message-*`（由 WSFormValidator 读取）
 *   - 联动：字段行容器上的 `data-conditions` + `.form-field-container`（由 BootstrapForm 读取）
 *
 * 字段行结构与 `app/admin/view/{模块}/form.html` 保持一致，便于与手写页面共存：
 * ```
 * <div class="row g-3 align-items-center form-field-container">
 *   <div class="col-auto label"><label class="col-form-label" for="..">标签 *</label></div>
 *   <div class="col">控件</div>
 *   <div class="col-auto tips"><div class="invalid-feedback">…</div></div>
 * </div>
 * ```
 *
 * ## 已知取舍（骨架版本）
 *
 * 1. **下拉/日期使用原生控件**，而非 `data-role="wsselect"` / `ws-date-picker`。
 *    原因是这两个组件均为"JS 自渲染 + 自管理提交值"，其提交与校验契约尚未确认，
 *    贸然接入会产出"校验挂不上、值提交不上"的假集成。
 *    宿主可以继承本类，在确认契约后覆写 {@see select()} / {@see control()} 接入。
 * 2. **不含 repeater（可重复组）渲染**：需要与宿主重复项 JS 约定配合，另行设计。
 * 3. 需要外层由调用方包裹 `<form id="wsForm" class="ws-form needs-validation" novalidate>`，
 *    并初始化 `window.WsFormValidator` 与 `BootstrapForm`。
 */
class Bootstrap5Theme extends AbstractFormTheme
{
    /**
     * 渲染控件本体。
     */
    public function control(array $field, array $context): string
    {
        $type = (string) $context['type'];

        return match ($type) {
            'hidden'                                    => $this->input('hidden', $context),
            'textarea'                                  => $this->textarea($context, 4),
            'editor'                                    => $this->textarea($context, 8),
            'number'                                    => $this->number($context),
            'password'                                  => $this->input('password', $context),
            'email'                                     => $this->input('email', $context),
            'color'                                     => $this->input('color', $context, ['class' => 'form-control form-control-color']),
            'date'                                      => $this->input('date', $context),
            'time'                                      => $this->input('time', $context),
            'datetime'                                  => $this->datetime($context),
            'select'                                    => $this->select($context),
            'radio'                                     => $this->choices($context, 'radio'),
            'checkbox'                                  => $this->choices($context, 'checkbox'),
            'switch'                                    => $this->switchControl($context),
            'image', 'file'                             => $this->upload($context),
            default                                     => $this->input('text', $context),
        };
    }

    /**
     * 将控件包装为完整字段行。隐藏域不占行，直接返回控件本体。
     */
    public function field(array $field, string $control, array $context): string
    {
        if (($context['type'] ?? '') === 'hidden') {
            return $control;
        }

        $id       = (string) $context['id'];
        $label    = (string) $context['label'];
        $required = !empty($context['required']);
        $help     = (string) $context['help'];

        $containerAttrs = [
            'class'      => 'row g-3 align-items-center form-field-container',
            'data-field' => (string) $context['name'],
        ];

        $html = '<div' . $this->attrString($containerAttrs)
            . $this->conditionsAttr($context['conditions'] ?? null) . '>';

        // label 列
        $html .= '<div class="col-auto label">';
        $html .= sprintf(
            '<label class="col-form-label" for="%s">%s%s</label>',
            $this->escape($id),
            $this->escape($label),
            $required ? ' <span class="text-danger">*</span>' : ''
        );
        $html .= '</div>';

        // 控件列
        $html .= '<div class="col">' . $control . '</div>';

        // 反馈 / 帮助列：.invalid-feedback 必须存在，校验器依赖它展示错误
        $html .= '<div class="col-auto tips">';
        $html .= '<div class="invalid-feedback"><span class="validation-text"></span></div>';
        if ($help !== '') {
            $html .= '<div class="form-text">' . $this->escape($help) . '</div>';
        }
        $html .= '</div>';

        return $html . '</div>';
    }

    // ──────────────────────────────────────────────
    //  分组
    // ──────────────────────────────────────────────

    /**
     * 覆写分组包装，使用 Bootstrap 卡片风格标题。
     */
    public function group(string $name, array $group, string $fieldsHtml, array $context): string
    {
        if ($fieldsHtml === '') {
            return '';
        }

        $title = trim((string) ($group['title'] ?? ''));

        if ($name === '' && $title === '') {
            return $fieldsHtml;
        }

        return sprintf(
            '<div class="ws-form-group mb-4" data-group="%s"><h6 class="border-bottom pb-2 mb-3">%s</h6>%s</div>',
            $this->escape($name),
            $this->escape($title !== '' ? $title : $name),
            $fieldsHtml
        );
    }

    // ──────────────────────────────────────────────
    //  各类型控件
    // ──────────────────────────────────────────────

    /**
     * 通用 `<input>`。
     *
     * @param array<string, mixed> $extra 额外属性（会覆盖默认值）
     */
    protected function input(string $type, array $context, array $extra = []): string
    {
        $attrs = array_merge(
            ['type' => $type, 'class' => 'form-control'],
            $context['attrs'],
            $this->placeholderAttrs($context),
            $extra,
            ['value' => $this->stringify($context['value'])]
        );

        return '<input' . $this->attrString($attrs) . '>';
    }

    /**
     * 数字输入：携带 min / max / step。
     */
    protected function number(array $context): string
    {
        $extra = [
            'min'  => $context['min'],
            'max'  => $context['max'],
            'step' => $context['step'],
        ];

        return $this->input('number', $context, $extra);
    }

    /**
     * datetime-local：HTML 要求 `T` 分隔日期与时间，而 schema 默认格式为 `Y-m-d H:i:s`。
     */
    protected function datetime(array $context): string
    {
        $value = $this->stringify($context['value']);

        if ($value !== '') {
            $value = str_replace(' ', 'T', $value);
        }

        $attrs = array_merge(
            ['type' => 'datetime-local', 'class' => 'form-control'],
            $context['attrs'],
            $this->placeholderAttrs($context),
            ['value' => $value]
        );

        return '<input' . $this->attrString($attrs) . '>';
    }

    protected function textarea(array $context, int $rows): string
    {
        $attrs = array_merge(
            ['class' => 'form-control', 'rows' => $rows],
            $context['attrs'],
            $this->placeholderAttrs($context)
        );

        return '<textarea' . $this->attrString($attrs) . '>'
            . $this->escape($this->stringify($context['value']))
            . '</textarea>';
    }

    protected function select(array $context): string
    {
        $multiple = !empty($context['multiple']);

        $attrs = array_merge(['class' => 'form-select'], $context['attrs']);

        if ($multiple) {
            $attrs['multiple'] = true;
        }

        $html = '<select' . $this->attrString($attrs) . '>';

        // 单选且提供了 placeholder 时，补一个空值选项作为"未选择"状态
        if (!$multiple && ($context['placeholder'] ?? '') !== '') {
            $html .= '<option value="">' . $this->escape((string) $context['placeholder']) . '</option>';
        }

        foreach (($context['options'] ?? []) as $optionValue => $optionLabel) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                $this->escape((string) $optionValue),
                $this->isSelected($context['value'], $optionValue) ? ' selected' : '',
                $this->escape((string) $optionLabel)
            );
        }

        return $html . '</select>';
    }

    /**
     * radio / checkbox 选项组。
     *
     * 每个选项复用同一 `name`（多值型已由渲染器解析为 `name[]`），
     * id 使用 `{字段id}_{选项值}` 保证唯一。
     */
    protected function choices(array $context, string $type): string
    {
        $options = $context['options'] ?? [];

        // 无选项的 checkbox 退化为单个布尔勾选框
        if ($options === []) {
            $attrs = array_merge(
                $context['attrs'],
                ['value' => '1', 'checked' => filter_var($context['value'], FILTER_VALIDATE_BOOLEAN)]
            );

            $id = (string) $context['id'];

            return '<div class="form-check">'
                . '<input type="' . $type . '" class="form-check-input"' . $this->attrString($attrs) . '>'
                . '<label class="form-check-label" for="' . $this->escape($id) . '">'
                . $this->escape((string) $context['label']) . '</label></div>';
        }

        $html = '<div class="d-flex flex-wrap gap-3 pt-1">';

        foreach ($options as $optionValue => $optionLabel) {
            $optionId = $context['id'] . '_' . $this->slug((string) $optionValue);

            $attrs = array_merge(
                $context['attrs'],   // 含 name 与 data-validation*，每个选项都要带（校验器按首个命中查找）
                [
                    'id'      => $optionId,
                    'value'   => (string) $optionValue,
                    'checked' => $this->isSelected($context['value'], $optionValue),
                ]
            );

            $html .= '<div class="form-check form-check-inline">'
                . '<input type="' . $type . '" class="form-check-input"' . $this->attrString($attrs) . '>'
                . '<label class="form-check-label" for="' . $this->escape($optionId) . '">'
                . $this->escape((string) $optionLabel) . '</label>'
                . '</div>';
        }

        return $html . '</div>';
    }

    /**
     * 开关。未勾选时浏览器不会提交该字段，由后端 DataFilter 按 schema 补齐默认值。
     */
    protected function switchControl(array $context): string
    {
        $id = (string) $context['id'];

        $attrs = array_merge(
            $context['attrs'],
            ['value' => '1', 'checked' => filter_var($context['value'], FILTER_VALIDATE_BOOLEAN)]
        );

        return '<div class="form-check form-switch">'
            . '<input type="checkbox" role="switch" class="form-check-input"' . $this->attrString($attrs) . '>'
            . '<label class="form-check-label" for="' . $this->escape($id) . '"></label>'
            . '</div>';
    }

    /**
     * 图片 / 文件上传。
     *
     * 原生 file 控件无法回填历史值，因此在已有值时额外展示一行当前值提示。
     */
    protected function upload(array $context): string
    {
        $type = (string) $context['type'];

        $attrs = array_merge(['type' => 'file', 'class' => 'form-control'], $context['attrs']);

        if (!empty($context['multiple'])) {
            $attrs['multiple'] = true;
        }

        if ($type === 'image') {
            $attrs['accept'] = 'image/*';
        }

        $html = '<input' . $this->attrString($attrs) . '>';

        $current = $this->stringify($context['value']);

        if ($current !== '') {
            $html .= '<div class="form-text">当前：' . $this->escape($current) . '</div>';
        }

        return $html;
    }

    // ──────────────────────────────────────────────
    //  内部工具
    // ──────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    private function placeholderAttrs(array $context): array
    {
        $placeholder = (string) ($context['placeholder'] ?? '');

        return $placeholder === '' ? [] : ['placeholder' => $placeholder];
    }

    /**
     * 选项值 → 可用作 DOM id 的片段。
     */
    private function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]/', '_', $value);

        return ($slug === null || $slug === '') ? 'x' : $slug;
    }
}
