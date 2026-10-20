<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\Dependency;
use wise\form\schema\exception\ErrorCode;
use wise\form\schema\exception\SchemaException;
use wise\form\schema\field\Text;
use wise\form\schema\field\Number;
use wise\form\schema\field\Select;
use wise\form\schema\field\Textarea;
use wise\form\schema\field\SwitchField;
use wise\form\schema\field\Hidden;
use wise\form\schema\Form;
use wise\form\schema\tests\TestCase;

class FormTest extends TestCase
{
    public function testFormCreation(): void
    {
        $form = new Form('user_edit');
        $this->assertSame('user_edit', $form->getName());
    }

    public function testAddField(): void
    {
        $form = new Form('test');
        $form->add(Text::make('username', '用户名'));

        $fields = $form->getFields();
        $this->assertCount(1, $fields);
        $this->assertArrayHasKey('username', $fields);
    }

    public function testAddDuplicateFieldThrowsException(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionCode(ErrorCode::DUPLICATE_FIELD->value);

        $form = new Form('test');
        $form->add(Text::make('username'));
        $form->add(Text::make('username'));
    }

    public function testFormToArrayBasicStructure(): void
    {
        $form = new Form('user_edit');
        $form->add(Text::make('username', '用户名')->required());

        $arr = $form->toArray();

        $this->assertSame('user_edit', $arr['form']);
        $this->assertArrayHasKey('fields', $arr);
        $this->assertArrayHasKey('groups', $arr);
        $this->assertArrayHasKey('repeaters', $arr);
        $this->assertArrayHasKey('username', $arr['fields']);
        $this->assertSame('用户名', $arr['fields']['username']['label']);
    }

    public function testFormToArrayFieldsKeyedByName(): void
    {
        $form = new Form('test');
        $form->add(Text::make('a', 'A'));
        $form->add(Text::make('b', 'B'));
        $form->add(Text::make('c', 'C'));

        $arr = $form->toArray();

        $this->assertSame(['a', 'b', 'c'], \array_keys($arr['fields']));
    }

    public function testFormGroupRegistration(): void
    {
        $form = new Form('test');
        $form->group('basic', '基本信息');
        $form->group('advanced', '高级设置', 'settings', true);

        $groups = $form->getGroups();
        $this->assertCount(2, $groups);
        $this->assertArrayHasKey('basic', $groups);
        $this->assertArrayHasKey('advanced', $groups);

        $arr = $form->toArray();
        $this->assertCount(2, $arr['groups']);
        $this->assertTrue($arr['groups']['advanced']['collapsed']);
    }

    public function testFormGroupWithCallback(): void
    {
        $form = new Form('test');
        $form->group('basic', '基本信息', null, false, function (Form $f) {
            $f->add(Text::make('name', '姓名'));
        });

        $fields = $form->getFields();
        $this->assertArrayHasKey('name', $fields);
    }

    public function testFormWhenConditionalFields(): void
    {
        $form = new Form('test');
        $form->when('status', '=', 'active', function (Form $f, Dependency $dep) {
            $f->add(Text::make('reason', '原因')->dependency($dep));
        });

        $arr = $form->toArray();
        $this->assertArrayHasKey('reason', $arr['fields']);

        $depArr = $arr['fields']['reason']['dependencies'];
        $this->assertCount(1, $depArr);
        $this->assertSame('status', $depArr[0]['field']);
        $this->assertSame('=', $depArr[0]['operator']);
        $this->assertSame('active', $depArr[0]['value']);
    }

    public function testFormRepeater(): void
    {
        $form = new Form('test');
        $form->repeater('items', '列表项', function (Form $f) {
            $f->add(Text::make('name', '名称'));
            $f->add(Number::make('qty', '数量'));
        });

        $repeaters = $form->getRepeaters();
        $this->assertCount(1, $repeaters);
        $this->assertArrayHasKey('items', $repeaters);

        $arr = $form->toArray();
        $this->assertArrayHasKey('items', $arr['repeaters']);
        $this->assertSame('列表项', $arr['repeaters']['items']['label']);
        $this->assertArrayHasKey('name', $arr['repeaters']['items']['fields']);
        $this->assertArrayHasKey('qty', $arr['repeaters']['items']['fields']);
    }

    public function testComplexFormToArray(): void
    {
        $form = new Form('user_profile');

        // Add basic fields
        $form->add(Text::make('username', '用户名')->required()->rules('alpha_dash'));
        $form->add(Text::make('email', '邮箱')->required()->rules('email'));
        $form->add(Select::make('role', '角色')->options(['admin' => '管理员', 'user' => '用户']));
        $form->add(Textarea::make('bio', '简介')->placeholder('自我介绍'));
        $form->add(SwitchField::make('active', '激活')->default(true));
        $form->add(Hidden::make('id', 'ID'));

        // Add group
        $form->group('basic', '基本信息');
        $form->group('preferences', '偏好设置', 'preferences', true);

        // Add conditional
        $form->when('role', '=', 'admin', function (Form $f, Dependency $dep) {
            $f->add(Text::make('admin_note', '管理备注')->dependency($dep));
        });

        // Add repeater
        $form->repeater('educations', '教育经历', function (Form $f) {
            $f->add(Text::make('school', '学校'));
            $f->add(Text::make('degree', '学位'));
        });

        $arr = $form->toArray();

        // Verify structure
        $this->assertSame('user_profile', $arr['form']);
        $this->assertCount(7, $arr['fields']);
        $this->assertArrayHasKey('username', $arr['fields']);
        $this->assertArrayHasKey('admin_note', $arr['fields']);
        $this->assertCount(2, $arr['groups']);
        $this->assertCount(1, $arr['repeaters']);

        // JSON serializable
        $json = \json_encode($arr);
        $this->assertIsString($json);
        $this->assertNotFalse($json);
    }

    public function testFormToArrayHasEmptyArraysByDefault(): void
    {
        $form = new Form('empty');
        $arr = $form->toArray();

        $this->assertSame([], $arr['fields']);
        $this->assertSame([], $arr['groups']);
        $this->assertSame([], $arr['repeaters']);
    }

    public function testFormChaining(): void
    {
        $form = (new Form('test'))
            ->add(Text::make('name', '姓名'))
            ->add(Text::make('email', '邮箱'))
            ->group('basic', '基本信息');

        $this->assertCount(2, $form->getFields());
        $this->assertCount(1, $form->getGroups());
    }
}
