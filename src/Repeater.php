<?php

declare(strict_types=1);

namespace wise\form\schema;

use Closure;

/**
 * Repeater — 可重复字段组。
 *
 * 内部使用一个子 Form 实例管理子字段，toArray() 时输出 repeaters 数组。
 */
class Repeater
{
    /** 唯一标识名 */
    private string $name;

    /** 显示标签 */
    private string $label;

    /** 内部子 Form 实例 */
    private Form $form;

    /**
     * @param string  $name     唯一标识名
     * @param string  $label    显示标签
     * @param Closure $callback 接收 Form 实例用于构建子字段的回调
     */
    public function __construct(string $name, string $label, Closure $callback)
    {
        $this->name = $name;
        $this->label = $label;
        $this->form = new Form($name);
        $callback($this->form);
    }

    /**
     * 获取名称。
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取标签。
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * 获取内部 Form 实例。
     *
     * @return Form
     */
    public function getForm(): Form
    {
        return $this->form;
    }

    /**
     * 从数组还原 Repeater 实例。
     *
     * @param array $data 包含 name、label、fields 键的关联数组
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $repeater = new self($data['name'], $data['label'], function () {});
        $repeater->setForm(Form::fromArray([
            'form'      => $data['name'],
            'fields'    => $data['fields'] ?? [],
            'groups'    => [],
            'repeaters' => [],
        ]));
        return $repeater;
    }

    /**
     * 设置内部 Form 实例（包内可见）。
     *
     * @param Form $form
     * @return void
     */
    private function setForm(Form $form): void
    {
        $this->form = $form;
    }

    /**
     * 转为数组表示。
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name'   => $this->name,
            'label'  => $this->label,
            'fields' => $this->form->toArray()['fields'],
        ];
    }
}
