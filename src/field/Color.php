<?php

declare(strict_types=1);

namespace wise\form\schema\field;

use wise\form\schema\enums\FieldType;

/**
 * Color 字段 — 颜色选择器。
 */
class Color extends AbstractField
{
    /**
     * {@inheritDoc}
     */
    public function getType(): FieldType
    {
        return FieldType::COLOR;
    }
}
