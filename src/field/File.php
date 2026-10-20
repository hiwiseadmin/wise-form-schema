<?php

declare(strict_types=1);

namespace wise\form\schema\field;

use wise\form\schema\enums\FieldType;

/**
 * File 字段 — 文件上传。
 */
class File extends AbstractField
{
    /**
     * {@inheritDoc}
     */
    public function getType(): FieldType
    {
        return FieldType::FILE;
    }
}
