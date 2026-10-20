<?php

declare(strict_types=1);

namespace wise\form\schema\field;

use wise\form\schema\enums\FieldType;

/**
 * Hidden 字段 — 隐藏域。
 */
class Hidden extends AbstractField
{
    /**
     * {@inheritDoc}
     */
    public function getType(): FieldType
    {
        return FieldType::HIDDEN;
    }
}
