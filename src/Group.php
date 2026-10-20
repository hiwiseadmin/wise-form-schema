<?php

declare(strict_types=1);

namespace wise\form\schema;

/**
 * Group — 表单字段的逻辑分组。
 *
 * 用于将表单字段按视觉区域（如 Tab / Fieldset）分组展示。
 */
class Group
{
    /**
     * @param string      $name      分组标识名
     * @param string      $title     分组标题
     * @param string|null $icon      分组图标
     * @param bool        $collapsed 是否默认折叠
     */
    public function __construct(
        private string $name,
        private string $title,
        private ?string $icon = null,
        private bool $collapsed = false
    ) {
    }

    /**
     * 获取分组名。
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取分组标题。
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * 转为数组表示。
     *
     * @return array{name: string, title: string, icon: string|null, collapsed: bool}
     */
    public function toArray(): array
    {
        return [
            'name'      => $this->name,
            'title'     => $this->title,
            'icon'      => $this->icon,
            'collapsed' => $this->collapsed,
        ];
    }
}
