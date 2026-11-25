<?php

declare(strict_types=1);

namespace wise\form\schema\field;

use wise\form\schema\enums\FieldType;

/**
 * Time 字段 — 时间选择（如 HH:mm:ss）。
 */
class Time extends AbstractField
{
    /**
     * {@inheritDoc}
     */
    public function getType(): FieldType
    {
        return FieldType::TIME;
    }

    /**
     * {@inheritDoc}
     */
    protected function buildProps(): array
    {
        return \array_merge(parent::buildProps(), [
            'format' => $this->format ?? 'H:i:s',
        ]);
    }
}
