<?php

declare(strict_types=1);

namespace wise\form\schema\renderer\html;

/**
 * AbstractFormTheme — 主题实现的可选基类。
 *
 * 把与具体 CSS 框架无关的工具方法集中在这里，避免每个主题重复实现：
 *   - HTML 转义（安全底线）
 *   - HTML 属性串拼装（含布尔属性）
 *   - JSON 属性序列化（data-options / data-conditions）
 *   - 值的字符串化（数组 → 换行拼接等）
 *   - 分组容器的默认包装（子类可覆写以使用自己的分组标记）
 *
 * 新主题只需继承本类并实现 {@see control()} 与 {@see field()} 即可。
 */
abstract class AbstractFormTheme implements FormThemeInterface
{
    /**
     * 分组容器默认实现。
     *
     * 未分组（$name === ''）且无标题时直接返回字段 HTML，避免产生无意义的 DOM 层级。
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
            '<div class="ws-form-group" data-group="%s"><h6 class="ws-form-group-title">%s</h6>%s</div>',
            $this->escape($name),
            $this->escape($title !== '' ? $title : $name),
            $fieldsHtml
        );
    }

    /**
     * HTML 转义。所有来自 schema 的动态内容都必须经过本方法。
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 将属性映射拼装为 HTML 属性串。
     *
     * - `null` / `false` / `''` 的属性会被跳过（不输出）
     * - `true` 输出为布尔属性（如 `multiple`、`disabled`）
     *
     * @param array<string, mixed> $attrs
     * @return string 形如 ` id="x" name="y" multiple`
     */
    protected function attrString(array $attrs): string
    {
        $html = '';

        foreach ($attrs as $name => $value) {
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            if ($value === true) {
                $html .= ' ' . $name;
                continue;
            }

            $html .= sprintf(' %s="%s"', $name, $this->escape((string) $value));
        }

        return $html;
    }

    /**
     * 将数据序列化为 JSON，用于 data-options / data-conditions 等属性。
     *
     * 序列化结果不含引号转义，由 {@see attrString()} 统一做 HTML 转义。
     */
    protected function jsonEncode(mixed $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '[]' : $json;
    }

    /**
     * 将值转为可写入表单控件的字符串。
     *
     * - `null` / `false` → `''`
     * - `true` → `'1'`
     * - 数组 → 换行拼接（便于 textarea 展示）
     */
    protected function stringify(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if ($value === true) {
            return '1';
        }

        if (is_array($value)) {
            return implode("\n", array_map('strval', $value));
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $this->jsonEncode($value);
    }

    /**
     * 判断多值字段的当前值是否包含某选项（供 select/checkbox 回显使用）。
     *
     * @param mixed $value    当前值（标量或数组）
     * @param mixed $optionValue 选项值
     */
    protected function isSelected(mixed $value, mixed $optionValue): bool
    {
        $option = (string) $optionValue;

        if (is_array($value)) {
            return in_array($option, array_map('strval', $value), true);
        }

        return (string) $value === $option;
    }

    /**
     * 生成 `data-conditions` 属性串（无联动时返回空串）。
     *
     * @param array<int, array<string, mixed>>|null $conditions
     */
    protected function conditionsAttr(?array $conditions): string
    {
        if ($conditions === null || $conditions === []) {
            return '';
        }

        return sprintf(' data-conditions="%s"', $this->escape($this->jsonEncode($conditions)));
    }
}
