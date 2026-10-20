<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\Repeater;
use wise\form\schema\field\Text;
use wise\form\schema\field\Number;
use wise\form\schema\tests\TestCase;

class RepeaterTest extends TestCase
{
    public function testRepeaterCreation(): void
    {
        $repeater = new Repeater('items', '列表项', function ($form) {
            $form->add(Text::make('name', '名称'));
        });

        $this->assertSame('items', $repeater->getName());
        $this->assertSame('列表项', $repeater->getLabel());
    }

    public function testRepeaterHasForm(): void
    {
        $repeater = new Repeater('items', '列表项', function ($form) {
            $form->add(Text::make('name', '名称'));
        });

        $this->assertNotNull($repeater->getForm());
        $fields = $repeater->getForm()->getFields();
        $this->assertArrayHasKey('name', $fields);
    }

    public function testRepeaterToArray(): void
    {
        $repeater = new Repeater('items', '列表项', function ($form) {
            $form->add(Text::make('name', '名称')->required());
            $form->add(Number::make('quantity', '数量')->default(1));
        });

        $arr = $repeater->toArray();

        $this->assertSame('items', $arr['name']);
        $this->assertSame('列表项', $arr['label']);
        $this->assertArrayHasKey('name', $arr['fields']);
        $this->assertArrayHasKey('quantity', $arr['fields']);
        $this->assertSame('名称', $arr['fields']['name']['label']);
        $this->assertSame('数量', $arr['fields']['quantity']['label']);
    }

    public function testRepeaterWithMultipleFields(): void
    {
        $repeater = new Repeater('contacts', '联系人', function ($form) {
            $form->add(Text::make('name', '姓名'));
            $form->add(Text::make('phone', '电话'));
            $form->add(Text::make('email', '邮箱'));
        });

        $arr = $repeater->toArray();
        $this->assertCount(3, $arr['fields']);
    }

    public function testRepeaterToArrayIsJsonSerializable(): void
    {
        $repeater = new Repeater('items', 'Items', function ($form) {
            $form->add(Text::make('title', 'Title'));
        });

        $json = \json_encode($repeater->toArray());
        $this->assertIsString($json);
        $this->assertNotFalse($json);
    }
}
