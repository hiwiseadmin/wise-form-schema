<?php

declare(strict_types=1);

namespace wise\form\schema\renderer\html;

/**
 * FormThemeInterface — HTML 表单主题契约。
 *
 * 渲染被刻意拆成两层，以解决"一套 Schema 要适配 Bootstrap / Layui / Element 等多种前端框架"的问题：
 *
 *   - {@see HtmlRenderer}（框架无关）：解析 schema、计算行为属性（前端校验规则、联动条件）、分组
 *   - {@see FormThemeInterface}（框架相关）：决定标记结构与 CSS class
 *
 * 因此新增一个前端框架 = 新增一个本接口的实现类，
 * **不需要改动 HtmlRenderer，也不需要改动任何 schema**。
 * 包内附带 {@see \wise\form\schema\renderer\html\theme\Bootstrap5Theme} 作为参考实现。
 *
 * 约定：实现方必须对**所有**来自 schema 的动态内容做 HTML 转义
 * （可复用 {@see AbstractFormTheme::escape()}），避免 XSS。
 */
interface FormThemeInterface
{
    /**
     * 渲染单个字段的控件本体（不含 label、行容器、反馈区）。
     *
     * @param array<string, mixed> $field   字段定义（Form::toArray() 的单个 field 节点）
     * @param array<string, mixed> $context 渲染上下文，结构见 HtmlRenderer::buildContext()
     * @return string 控件 HTML
     */
    public function control(array $field, array $context): string;

    /**
     * 将控件包装成完整的一行（label + 控件 + 反馈/帮助区）。
     *
     * 字段级联动条件（$context['conditions']）由本方法负责落到容器上，
     * 因为前端的显示/隐藏作用于整个字段行而非单个控件。
     *
     * @param array<string, mixed> $field   字段定义
     * @param string               $control control() 的输出
     * @param array<string, mixed> $context 渲染上下文
     * @return string 字段行 HTML
     */
    public function field(array $field, string $control, array $context): string;

    /**
     * 包装一组字段（分组容器）。
     *
     * @param string               $name       分组名（未分组时为空串）
     * @param array<string, mixed> $group      分组元数据（title/icon/collapsed）；未分组时为空数组
     * @param string               $fieldsHtml 组内字段行 HTML
     * @param array<string, mixed> $context    渲染上下文
     * @return string 分组 HTML
     */
    public function group(string $name, array $group, string $fieldsHtml, array $context): string;
}
