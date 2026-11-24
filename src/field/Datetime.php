<?php

declare(strict_types=1);

namespace wise\form\schema\field;

use wise\form\schema\enums\FieldType;

/**
 * Datetime 字段 — 日期时间选择。
 */
class Datetime extends AbstractField
{
    /**
     * {@inheritDoc}
     */
    public function getType(): FieldType
    {
        return FieldType::DATETIME;
    }

    /**
     * {@inheritDoc}
     */
    protected function buildProps(): array
    {
        return \array_merge(parent::buildProps(), [
            'format' => $this->format ?? 'Y-m-d H:i:s',
        ]);
    }
}
