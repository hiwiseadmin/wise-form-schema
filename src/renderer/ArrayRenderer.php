<?php

declare(strict_types=1);

namespace wise\form\schema\renderer;

/**
 * ArrayRenderer — 最基本的透传渲染器。
 *
 * 直接将 Schema 数组原样返回，可用作中间态或调试。
 */
class ArrayRenderer implements RendererInterface
{
    /**
     * {@inheritDoc}
     */
    public function render(array $schema, array $context = []): array
    {
        return $schema;
    }
}
