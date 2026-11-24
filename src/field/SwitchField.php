<?php

declare(strict_types=1);

namespace wise\form\schema\field;

use wise\form\schema\enums\FieldType;

/**
 * SwitchField — 开关切换（避免 PHP switch 关键字命名冲突）。
 */
class SwitchField extends AbstractField
{
    /**
     * {@inheritDoc}
     */
    public function getType(): FieldType
    {
        return FieldType::SWITCH;
    }
}
