<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Functional;

use wise\form\schema\DataFilter;
use wise\form\schema\Dependency;
use wise\form\schema\field\Checkbox;
use wise\form\schema\field\Hidden;
use wise\form\schema\field\Number;
use wise\form\schema\field\Select;
use wise\form\schema\field\SwitchField;
use wise\form\schema\field\Text;
use wise\form\schema\field\Textarea;
use wise\form\schema\Form;
use wise\form\schema\renderer\ArrayRenderer;
use wise\form\schema\ValidationGenerator;
use wise\form\schema\tests\TestCase;

class EndToEndTest extends TestCase
{
    /**
     * 完整流程：构建 Form → toArray → ValidationGenerator → DataFilter
     */
    public function testFullLifecycle(): void
    {
        $form = new Form('user_profile');

        $form->add(
            Text::make('username', '用户名')
                ->placeholder('请输入用户名')
                ->required()
                ->rules(['alpha_dash', 'length:3,20'])
        );

        $form->add(
            Text::make('email', '邮箱')
                ->placeholder('请输入邮箱')
                ->required()
                ->rules('email')
        );

        $form->add(
            Select::make('role', '角色')
                ->options(['admin' => '管理员', 'editor' => '编辑', 'viewer' => '访客'])
                ->default('viewer')
        );

        $form->add(
            Number::make('age', '年龄')
                ->min(0)
                ->max(150)
                ->placeholder('请输入年龄')
        );

        $form->add(
            Textarea::make('bio', '简介')
                ->placeholder('自我介绍（最多500字）')
                ->rules('max:500')
        );

        $form->add(
            Checkbox::make('skills', '技能')
                ->options(['php' => 'PHP', 'js' => 'JavaScript', 'go' => 'Go'])
                ->multiple()
                ->default([])
        );

        $form->add(
            SwitchField::make('newsletter', '订阅推送')
                ->default(true)
        );

        $form->add(Hidden::make('id', 'ID'));

        $form->group('basic', '基本信息', 'user', false);
        $form->group('preferences', '偏好设置', 'settings', true);

        $form->when('role', '=', 'admin', function ($f, Dependency $dep) {
            $f->add(Text::make('admin_level', '管理级别')->dependency($dep));
        });

        $form->repeater('addresses', '收货地址', function (Form $f) {
            $f->add(Text::make('province', '省份')->required());
            $f->add(Text::make('city', '城市')->required());
            $f->add(Text::make('detail', '详细地址'));
        });

        $schema = $form->toArray();

        $this->assertSame('user_profile', $schema['form']);
        $this->assertCount(9, $schema['fields']);
        $this->assertCount(2, $schema['groups']);
        $this->assertCount(1, $schema['repeaters']);

        $json = \json_encode($schema, JSON_UNESCAPED_UNICODE);
        $this->assertIsString($json);
        $this->assertNotFalse($json);
        $decoded = \json_decode($json, true);
        $this->assertSame('user_profile', $decoded['form']);

        $rules = ValidationGenerator::generate($form);

        $this->assertStringContainsString('require', $rules['username']);
        $this->assertStringContainsString('string', $rules['username']);
        $this->assertStringContainsString('alpha_dash', $rules['username']);
        $this->assertStringContainsString('length:3,20', $rules['username']);

        $this->assertStringContainsString('require', $rules['email']);
        $this->assertStringContainsString('email', $rules['email']);

        $this->assertStringContainsString('number', $rules['age']);
        $this->assertStringContainsString('array', $rules['skills']);
        $this->assertStringContainsString('boolean', $rules['newsletter']);

        $this->assertArrayHasKey('addresses.*.province', $rules);
        $this->assertArrayHasKey('addresses.*.city', $rules);
        $this->assertArrayHasKey('addresses.*.detail', $rules);

        $filter = new DataFilter();

        $input = [
            'username' => 'john_doe',
            'email' => 'john@example.com',
            'role' => 'admin',
            'age' => '28',
            'bio' => 'Hello, I am John.',
            'skills' => ['php', 'js'],
            'newsletter' => '1',
            'id' => 100,
            'admin_level' => 'super',
            'hack_field' => 'evil',
            'addresses' => [
                [
                    'province' => '广东',
                    'city' => '深圳',
                    'detail' => '南山区',
                    'extra' => 'drop_me',
                ],
                [
                    'province' => '北京',
                    'city' => '北京',
                    'detail' => '朝阳区',
                ],
            ],
        ];

        $filtered = $filter->filter($schema, $input);

        $this->assertSame('john_doe', $filtered['username']);
        $this->assertSame('john@example.com', $filtered['email']);
        $this->assertSame(28, $filtered['age']);
        $this->assertTrue($filtered['newsletter']);
        $this->assertSame('100', $filtered['id']);

        $this->assertArrayNotHasKey('hack_field', $filtered);

        $this->assertCount(2, $filtered['addresses']);
        $this->assertSame('广东', $filtered['addresses'][0]['province']);
        $this->assertSame('深圳', $filtered['addresses'][0]['city']);
        $this->assertArrayNotHasKey('extra', $filtered['addresses'][0]);

        $renderer = new ArrayRenderer();
        $rendered = $renderer->render($schema, ['lang' => 'zh-CN']);
        $this->assertSame($schema, $rendered);

        $rulesFromArray = ValidationGenerator::generate($schema);
        $this->assertSame($rules, $rulesFromArray);
    }

    /**
     * 测试空表单场景。
     */
    public function testEmptyFormLifecycle(): void
    {
        $form = new Form('empty_form');
        $schema = $form->toArray();

        $rules = ValidationGenerator::generate($schema);
        $this->assertSame([], $rules);

        $filter = new DataFilter();
        $filtered = $filter->filter($schema, ['foo' => 'bar']);
        $this->assertSame([], $filtered);
    }
}
