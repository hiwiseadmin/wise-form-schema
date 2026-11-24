<?php

declare(strict_types=1);

namespace wise\form\schema\field;

use wise\form\schema\enums\FieldType;

/**
 * Radio 字段 — 单选框组。
 */
class Radio extends AbstractField
{
    /**
     * {@inheritDoc}
     */
    public function getType(): FieldType
    {
        return FieldType::RADIO;
    }
}
