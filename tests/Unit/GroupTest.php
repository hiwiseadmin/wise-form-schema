<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\Group;
use wise\form\schema\tests\TestCase;

class GroupTest extends TestCase
{
    public function testGroupCreation(): void
    {
        $group = new Group('basic', '基本信息');

        $this->assertSame('basic', $group->getName());
        $this->assertSame('基本信息', $group->getTitle());
    }

    public function testGroupWithIcon(): void
    {
        $group = new Group('basic', '基本信息', 'user');

        $arr = $group->toArray();
        $this->assertSame('user', $arr['icon']);
    }

    public function testGroupCollapsedDefault(): void
    {
        $group = new Group('advanced', '高级设置');
        $arr = $group->toArray();
        $this->assertFalse($arr['collapsed']);
    }

    public function testGroupCollapsedTrue(): void
    {
        $group = new Group('advanced', '高级设置', null, true);
        $arr = $group->toArray();
        $this->assertTrue($arr['collapsed']);
    }

    public function testGroupToArrayStructure(): void
    {
        $group = new Group('contact', '联系信息', 'phone', false);

        $arr = $group->toArray();

        $this->assertSame([
            'name'      => 'contact',
            'title'     => '联系信息',
            'icon'      => 'phone',
            'collapsed' => false,
        ], $arr);
    }

    public function testGroupToArrayIsJsonSerializable(): void
    {
        $group = new Group('basic', 'Basic');
        $json = \json_encode($group->toArray());
        $this->assertIsString($json);
        $this->assertNotFalse($json);
    }
}
