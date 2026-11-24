<?php

declare(strict_types=1);

namespace wise\form\schema\field;

use wise\form\schema\enums\FieldType;

/**
 * Date 字段 — 日期选择。
 */
class Date extends AbstractField
{
    /**
     * {@inheritDoc}
     */
    public function getType(): FieldType
    {
        return FieldType::DATE;
    }

    /**
     * {@inheritDoc}
     */
    protected function buildProps(): array
    {
        return \array_merge(parent::buildProps(), [
            'format' => $this->format ?? 'Y-m-d',
        ]);
    }
}
