<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\field\Text;
use wise\form\schema\field\Select;
use wise\form\schema\field\Number;
use wise\form\schema\field\Textarea;
use wise\form\schema\field\Date;
use wise\form\schema\field\Datetime;
use wise\form\schema\field\Time;
use wise\form\schema\field\Checkbox;
use wise\form\schema\field\Radio;
use wise\form\schema\field\File;
use wise\form\schema\field\Image;
use wise\form\schema\field\SwitchField;
use wise\form\schema\field\Hidden;
use wise\form\schema\enums\FieldType;
use wise\form\schema\Dependency;
use wise\form\schema\tests\TestCase;

class FieldTest extends TestCase
{

    public function testTextFieldType(): void
    {
        $field = Text::make('username');
        $this->assertSame(FieldType::TEXT, $field->getType());
    }

    public function testTextFieldToArray(): void
    {
        $field = Text::make('username', '用户名')
            ->placeholder('请输入用户名')
            ->default('admin')
            ->required()
            ->help('3-20位字符')
            ->rules('alpha_dash')
            ->format(null);

        $arr = $field->toArray();

        $this->assertSame('username', $arr['name']);
        $this->assertSame('text', $arr['type']);
        $this->assertSame('用户名', $arr['label']);
        $this->assertSame('请输入用户名', $arr['placeholder']);
        $this->assertSame('admin', $arr['default']);
        $this->assertTrue($arr['required']);
        $this->assertSame('3-20位字符', $arr['help']);
        $this->assertSame(['alpha_dash'], $arr['rules']);
        $this->assertSame('text', $arr['type']);
    }

    public function testTextFieldDefaultLabelIsName(): void
    {
        $field = Text::make('email');
        $this->assertSame('email', $field->toArray()['label']);
    }


    public function testSelectFieldType(): void
    {
        $field = Select::make('status');
        $this->assertSame(FieldType::SELECT, $field->getType());
    }

    public function testSelectFieldWithOptions(): void
    {
        $field = Select::make('status', '状态')
            ->options([1 => '启用', 0 => '禁用']);

        $arr = $field->toArray();

        $this->assertSame('select', $arr['type']);
        $this->assertSame([1 => '启用', 0 => '禁用'], $arr['props']['options']);
    }


    public function testNumberFieldType(): void
    {
        $field = Number::make('age');
        $this->assertSame(FieldType::NUMBER, $field->getType());
    }

    public function testNumberFieldWithConstraints(): void
    {
        $field = Number::make('age', '年龄')
            ->min(0)
            ->max(150)
            ->step(1);

        $arr = $field->toArray();

        $this->assertSame(0, $arr['props']['min']);
        $this->assertSame(150, $arr['props']['max']);
        $this->assertSame(1.0, $arr['props']['step']);
    }


    public function testTextareaFieldType(): void
    {
        $field = Textarea::make('description');
        $this->assertSame(FieldType::TEXTAREA, $field->getType());
    }


    public function testDateFieldType(): void
    {
        $field = Date::make('birthday');
        $this->assertSame(FieldType::DATE, $field->getType());
    }

    public function testDateFieldWithFormat(): void
    {
        $field = Date::make('birthday', '生日')
            ->format('Y-m-d');

        $arr = $field->toArray();
        $this->assertSame('Y-m-d', $arr['props']['format']);
    }


    public function testDatetimeFieldType(): void
    {
        $field = Datetime::make('created_at');
        $this->assertSame(FieldType::DATETIME, $field->getType());
    }

    public function testDatetimeDefaultFormat(): void
    {
        $field = Datetime::make('created_at', '创建时间');
        $arr = $field->toArray();
        $this->assertSame('Y-m-d H:i:s', $arr['props']['format']);
    }

    public function testDateDefaultFormat(): void
    {
        $field = Date::make('birthday', '生日');
        $arr = $field->toArray();
        $this->assertSame('Y-m-d', $arr['props']['format']);
    }

    public function testDateCustomFormatOverridesDefault(): void
    {
        $field = Date::make('birthday', '生日')->format('d/m/Y');
        $arr = $field->toArray();
        $this->assertSame('d/m/Y', $arr['props']['format']);
    }


    public function testTimeFieldType(): void
    {
        $field = Time::make('start_time');
        $this->assertSame(FieldType::TIME, $field->getType());
    }

    public function testTimeDefaultFormat(): void
    {
        $field = Time::make('start_time', '开始时间');
        $arr = $field->toArray();
        $this->assertSame('H:i:s', $arr['props']['format']);
    }

    public function testTimeCustomFormat(): void
    {
        $field = Time::make('start_time', '开始时间')->format('H:i');
        $arr = $field->toArray();
        $this->assertSame('H:i', $arr['props']['format']);
    }


    public function testRangeSetsPropTrue(): void
    {
        $field = Date::make('date_range', '日期区间')->range();
        $arr = $field->toArray();
        $this->assertTrue($arr['props']['range']);
    }

    public function testRangeSetsDefaultToEmptyArray(): void
    {
        $field = Date::make('date_range', '日期区间')->range();
        $arr = $field->toArray();
        $this->assertSame([], $arr['default']);
    }

    public function testRangeDefaultCanBeOverridden(): void
    {
        $field = Date::make('date_range', '日期区间')
            ->range(['2025-01-01', '2025-12-31']);
        $arr = $field->toArray();
        $this->assertSame(['2025-01-01', '2025-12-31'], $arr['default']);
    }

    public function testRangeDefaultFalse(): void
    {
        $field = Date::make('single_date', '单日期');
        $arr = $field->toArray();
        $this->assertFalse($arr['props']['range']);
        $this->assertSame('', $arr['default']);
    }

    public function testRangeWorksWithDatetime(): void
    {
        $field = Datetime::make('event_time', '活动时间')->range();
        $arr = $field->toArray();
        $this->assertTrue($arr['props']['range']);
        $this->assertSame('Y-m-d H:i:s', $arr['props']['format']);
        $this->assertSame([], $arr['default']);
    }

    public function testRangeWorksWithTime(): void
    {
        $field = Time::make('work_time', '营业时间')->range();
        $arr = $field->toArray();
        $this->assertTrue($arr['props']['range']);
        $this->assertSame('H:i:s', $arr['props']['format']);
        $this->assertSame([], $arr['default']);
    }


    public function testImageFieldType(): void
    {
        $field = Image::make('cover');
        $this->assertSame(FieldType::IMAGE, $field->getType());
    }

    public function testImageMultipleDefaultFalse(): void
    {
        $field = Image::make('cover', '封面');
        $arr = $field->toArray();
        $this->assertFalse($arr['props']['multiple']);
    }

    public function testImageExplicitMultipleTrue(): void
    {
        $field = Image::make('gallery', '图集')->multiple();
        $arr = $field->toArray();
        $this->assertTrue($arr['props']['multiple']);
    }


    public function testCheckboxFieldType(): void
    {
        $field = Checkbox::make('hobbies');
        $this->assertSame(FieldType::CHECKBOX, $field->getType());
    }

    public function testCheckboxMultipleDefault(): void
    {
        $field = Checkbox::make('tags', '标签')
            ->options(['php' => 'PHP', 'js' => 'JavaScript'])
            ->multiple();

        $arr = $field->toArray();
        $this->assertTrue($arr['props']['multiple']);
        $this->assertSame(['php' => 'PHP', 'js' => 'JavaScript'], $arr['props']['options']);
    }


    public function testRadioFieldType(): void
    {
        $field = Radio::make('gender');
        $this->assertSame(FieldType::RADIO, $field->getType());
    }


    public function testFileFieldType(): void
    {
        $field = File::make('avatar');
        $this->assertSame(FieldType::FILE, $field->getType());
    }


    public function testSwitchFieldType(): void
    {
        $field = SwitchField::make('enabled');
        $this->assertSame(FieldType::SWITCH, $field->getType());
    }

    public function testSwitchFieldDefault(): void
    {
        $field = SwitchField::make('enabled', '启用')
            ->default(true);

        $arr = $field->toArray();
        $this->assertTrue($arr['default']);
    }


    public function testHiddenFieldType(): void
    {
        $field = Hidden::make('token');
        $this->assertSame(FieldType::HIDDEN, $field->getType());
    }

    public function testHiddenFieldDefault(): void
    {
        $field = Hidden::make('csrf_token', 'CSRF')
            ->default('abc123');

        $arr = $field->toArray();
        $this->assertSame('abc123', $arr['default']);
    }


    public function testFluentApiReturnsSelf(): void
    {
        $field = Text::make('name');

        $this->assertSame($field, $field->label('Name'));
        $this->assertSame($field, $field->placeholder('Enter'));
        $this->assertSame($field, $field->default(''));
        $this->assertSame($field, $field->required());
        $this->assertSame($field, $field->help('Help text'));
        $this->assertSame($field, $field->rules(['required', 'string']));
    }

    public function testRulesAcceptsStringOrArray(): void
    {
        $a = Text::make('a')->rules('required');
        $this->assertSame(['required'], $a->toArray()['rules']);

        $b = Text::make('b')->rules(['required', 'email']);
        $this->assertSame(['required', 'email'], $b->toArray()['rules']);
    }

    public function testGroupAssignment(): void
    {
        $field = Text::make('city', '城市')
            ->group('location');

        $arr = $field->toArray();
        $this->assertSame('location', $arr['group']);
        $this->assertSame('location', $field->getGroup());
    }

    public function testDependencyAttachment(): void
    {
        $dep = new Dependency('status', '=', 'active');
        $field = Text::make('reason', '原因')
            ->dependency($dep);

        $arr = $field->toArray();
        $this->assertCount(1, $arr['dependencies']);
        $this->assertSame('status', $arr['dependencies'][0]['field']);
        $this->assertSame('=', $arr['dependencies'][0]['operator']);
        $this->assertSame('active', $arr['dependencies'][0]['value']);
    }

    public function testDependencyAttachmentIsIdempotent(): void
    {
        $field = Text::make('reason', '原因')
            ->dependency(new Dependency('status', '=', 'active'))
            ->dependency(new Dependency('status', '=', 'active'));

        $this->assertCount(1, $field->toArray()['dependencies']);

        $field->dependency(new Dependency('status', '=', 'active', 'disable'));
        $this->assertCount(2, $field->toArray()['dependencies']);
    }

    public function testRenderableConfiguration(): void
    {
        $field = Text::make('name')
            ->renderable(['component' => 'MyInput']);

        $arr = $field->toArray();
        $this->assertSame(['component' => 'MyInput'], $arr['renderable']);
    }

    public function testRenderableDefaultTrue(): void
    {
        $field = Text::make('name');
        $arr = $field->toArray();
        $this->assertTrue($arr['renderable']);
    }

    public function testMakeStaticFactory(): void
    {
        $field = Text::make('test', 'Test');
        $this->assertInstanceOf(Text::class, $field);
        $this->assertSame('test', $field->getName());
    }

    public function testIsRequired(): void
    {
        $field = Text::make('name');
        $this->assertFalse($field->isRequired());

        $field->required();
        $this->assertTrue($field->isRequired());
    }

    public function testGetRules(): void
    {
        $field = Text::make('email')->rules(['required', 'email']);
        $this->assertSame(['required', 'email'], $field->getRules());
    }

    public function testToArrayIsJsonSerializable(): void
    {
        $field = Text::make('username', '用户名')
            ->placeholder('请输入')
            ->required()
            ->rules('required');

        $json = \json_encode($field->toArray());
        $this->assertIsString($json);
        $this->assertNotFalse($json);

        $decoded = \json_decode($json, true);
        $this->assertSame('username', $decoded['name']);
    }
}
