<?php

declare(strict_types=1);

namespace wise\form\schema\field;

use wise\form\schema\enums\FieldType;

/**
 * Image 字段 — 图片上传，通过 multiple() 切换单图/多图模式。
 *
 * 单图模式下，验证规则为 string，DataFilter 转换为字符串；
 * 多图模式（->multiple(true)）下，验证规则为 array，DataFilter 转换为数组。
 */
class Image extends AbstractField
{
    /**
     * {@inheritDoc}
     */
    public function getType(): FieldType
    {
        return FieldType::IMAGE;
    }
}
