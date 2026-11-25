<?php

declare(strict_types=1);

namespace wise\form\schema\renderer;

/**
 * RendererInterface — Schema 渲染器接口。
 *
 * 所有渲染器（Array / HTML / Vue / React 等）必须实现此接口。
 */
interface RendererInterface
{
    /**
     * 将 Schema 渲染为目标格式。
     *
     * @param array $schema  Form::toArray() 输出的 Schema 数组
     * @param array $context 可选上下文（如语言、主题等）
     * @return mixed 渲染结果
     */
    public function render(array $schema, array $context = []): mixed;
}
