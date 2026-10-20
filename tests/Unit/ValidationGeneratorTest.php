<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\field\Text;
use wise\form\schema\field\Number;
use wise\form\schema\field\Select;
use wise\form\schema\field\Checkbox;
use wise\form\schema\field\SwitchField;
use wise\form\schema\field\Hidden;
use wise\form\schema\field\Date;
use wise\form\schema\field\Datetime;
use wise\form\schema\field\Time;
use wise\form\schema\field\Image;
use wise\form\schema\field\File;
use wise\form\schema\field\Textarea;
use wise\form\schema\Form;
use wise\form\schema\ValidationGenerator;
use wise\form\schema\tests\TestCase;

class ValidationGeneratorTest extends TestCase
{
    public function testGenerateFromForm(): void
    {
        $form = new Form('test');
        $form->add(Text::make('username', '用户名')->required()->rules('alpha_dash'));
        $form->add(Text::make('email', '邮箱')->required()->rules('email'));

        $rules = ValidationGenerator::generate($form);

        $this->assertArrayHasKey('username', $rules);
        $this->assertStringContainsString('require', $rules['username']);
        $this->assertStringContainsString('string', $rules['username']);
        $this->assertStringContainsString('alpha_dash', $rules['username']);
    }

    public function testGenerateFromArray(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'name' => [
                    'name' => 'name',
                    'type' => 'text',
                    'required' => true,
                    'rules' => [],
                ],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $rules = ValidationGenerator::generate($schema);
        $this->assertSame('require|string', $rules['name']);
    }

    public function testGenerateReturnsEmptyForEmptyForm(): void
    {
        $form = new Form('empty');
        $rules = ValidationGenerator::generate($form);
        $this->assertSame([], $rules);
    }

    public function testGenerateReturnsEmptyForInvalidSchema(): void
    {
        $rules = ValidationGenerator::generate(['no_fields' => true]);
        $this->assertSame([], $rules);
    }

    public function testTypeRuleMapping(): void
    {
        $mappings = [
            'text' => 'string',
            'textarea' => 'string',
            'number' => 'number',
            'date' => 'date',
            'datetime' => 'date',
            'time' => 'date',
            'checkbox' => 'array',
            'radio' => 'string',
            'select' => 'string',
            'switch' => 'boolean',
            'file' => 'string',
            'image' => 'string',
            'hidden' => 'string',
        ];

        foreach ($mappings as $type => $expectedRule) {
            $fieldClass = $this->getFieldClassForType($type);
            $field = $fieldClass::make('test_field');
            $form = new Form('test');
            $form->add($field);
            $rules = ValidationGenerator::generate($form);

            $this->assertStringContainsString($expectedRule, $rules['test_field'], "Type '{$type}' should map to rule '{$expectedRule}'");
        }
    }

    public function testRequiredRuleIncluded(): void
    {
        $form = new Form('test');
        $form->add(Text::make('field1', 'Field')->required());
        $form->add(Text::make('field2', 'Field'));

        $rules = ValidationGenerator::generate($form);

        $this->assertStringContainsString('require', $rules['field1']);
        $this->assertStringNotContainsString('require', $rules['field2']);
    }

    public function testRepeaterFieldsUseDotSyntax(): void
    {
        $form = new Form('order');
        $form->add(Text::make('order_no', '订单号')->required());
        $form->repeater('items', '商品', function (Form $f) {
            $f->add(Text::make('product_name', '商品名')->required());
            $f->add(Number::make('quantity', '数量')->required());
        });

        $rules = ValidationGenerator::generate($form);

        $this->assertArrayHasKey('order_no', $rules);
        $this->assertArrayHasKey('items.*.product_name', $rules);
        $this->assertArrayHasKey('items.*.quantity', $rules);
        $this->assertStringContainsString('string', $rules['items.*.product_name']);
        $this->assertStringContainsString('number', $rules['items.*.quantity']);
    }

    public function testLaravelStyleCustomRequiredRuleIsNormalized(): void
    {
        // 用户可能带着 Laravel 习惯写 ->rules('required')，
        // 必须归一化为 ThinkPHP 的 require，否则会被当成正则导致校验误判
        $form = new Form('test');
        $form->add(Text::make('a', 'A')->rules('required'));

        $rules = ValidationGenerator::generate($form);

        $this->assertSame('require|string', $rules['a']);
    }

    public function testRequiredFlagAndCustomRequiredRuleAreDeduplicated(): void
    {
        $form = new Form('test');
        $form->add(Text::make('a', 'A')->required()->rules(['required', 'max:10']));

        $rules = ValidationGenerator::generate($form);

        // require 只保留一条，且规范在首位
        $this->assertSame('require|string|max:10', $rules['a']);
    }

    public function testNormalizationPreservesRuleParameter(): void
    {
        $form = new Form('test');
        $form->add(Number::make('n', 'N')->rules('between:1,10'));

        $rules = ValidationGenerator::generate($form);

        $this->assertStringContainsString('between:1,10', $rules['n']);
    }

    // ──────────────────────────────────────────────
    //  generateFor：消费 conditional_rules（条件必填）
    // ──────────────────────────────────────────────

    public function testGenerateForAddsRequireWhenConditionMet(): void
    {
        $form = new Form('test');
        $form->add(Text::make('type', '类型'));
        $form->add(Text::make('company_name', '企业名称')->requiredIf('type', '=', 'company'));

        $rules = ValidationGenerator::generateFor($form, ['type' => 'company']);

        $this->assertSame('require|string', $rules['company_name']);
    }

    public function testGenerateForOmitsRequireWhenConditionNotMet(): void
    {
        $form = new Form('test');
        $form->add(Text::make('type', '类型'));
        $form->add(Text::make('company_name', '企业名称')->requiredIf('type', '=', 'company'));

        $rules = ValidationGenerator::generateFor($form, ['type' => 'personal']);

        $this->assertSame('string', $rules['company_name']);
    }

    public function testGenerateForDoesNotDuplicateRequire(): void
    {
        $form = new Form('test');
        $form->add(Text::make('type', '类型'));
        $form->add(Text::make('name', '名称')->required()->requiredIf('type', '=', 'company'));

        $rules = ValidationGenerator::generateFor($form, ['type' => 'company']);

        $this->assertSame('require|string', $rules['name']);
    }

    public function testGenerateForRequiredUnlessSemantics(): void
    {
        // requiredUnless(type = personal) ⇒ 当 type != personal 时必填
        $form = new Form('test');
        $form->add(Text::make('type', '类型'));
        $form->add(Text::make('tax_id', '税号')->requiredUnless('type', '=', 'personal'));

        $this->assertSame('require|string', ValidationGenerator::generateFor($form, ['type' => 'company'])['tax_id']);
        $this->assertSame('string', ValidationGenerator::generateFor($form, ['type' => 'personal'])['tax_id']);
    }

    public function testGenerateForFallsBackToGenerateWhenNoConditionalRules(): void
    {
        $form = new Form('test');
        $form->add(Text::make('title', '标题')->required());

        $this->assertSame(
            ValidationGenerator::generate($form),
            ValidationGenerator::generateFor($form, ['title' => 'x'])
        );
    }

    public function testGenerateForDropsRulesForEmptyRepeaterContainer(): void
    {
        $form = new Form('order');
        $form->add(Text::make('order_no', '订单号')->required());
        $form->repeater('items', '商品', function (Form $f) {
            $f->add(Text::make('sku', 'SKU')->required());
        });

        // 容器缺失：子字段规则保留（漏传容器仍会报错）
        $this->assertArrayHasKey('items.*.sku', ValidationGenerator::generateFor($form, ['order_no' => 'A']));

        // 容器为空：子字段规则摘除（无行 = 无必填，真空满足）
        $this->assertArrayNotHasKey('items.*.sku', ValidationGenerator::generateFor($form, ['order_no' => 'A', 'items' => []]));

        // 容器非空：子字段规则保留
        $this->assertArrayHasKey('items.*.sku', ValidationGenerator::generateFor($form, ['order_no' => 'A', 'items' => [['sku' => 'x']]]));
    }

    public function testCustomRulesMerged(): void
    {
        $form = new Form('test');
        $form->add(Text::make('email', '邮箱')->rules(['email', 'max:255']));

        $rules = ValidationGenerator::generate($form);

        $this->assertStringContainsString('email', $rules['email']);
        $this->assertStringContainsString('max:255', $rules['email']);
    }

    public function testImageMultipleUsesArrayRule(): void
    {
        $form = new Form('test');
        $form->add(Image::make('gallery', '图集')->multiple());

        $rules = ValidationGenerator::generate($form);
        $this->assertStringContainsString('array', $rules['gallery']);
        $this->assertStringNotContainsString('string', $rules['gallery']);
    }

    public function testImageSingleUsesStringRule(): void
    {
        $form = new Form('test');
        $form->add(Image::make('cover', '封面'));

        $rules = ValidationGenerator::generate($form);
        $this->assertStringContainsString('string', $rules['cover']);
    }

    public function testFileMultipleUsesArrayRule(): void
    {
        $form = new Form('test');
        $form->add(File::make('attachments', '附件')->multiple());

        $rules = ValidationGenerator::generate($form);
        $this->assertStringContainsString('array', $rules['attachments']);
    }

    public function testSelectMultipleUsesArrayRule(): void
    {
        $form = new Form('test');
        $form->add(Select::make('tags', '标签')->multiple()->options(['a' => 'A', 'b' => 'B']));

        $rules = ValidationGenerator::generate($form);
        $this->assertStringContainsString('array', $rules['tags']);
    }

    public function testTimeFieldType(): void
    {
        $form = new Form('test');
        $form->add(Time::make('start_time', '开始时间'));

        $rules = ValidationGenerator::generate($form);
        $this->assertStringContainsString('date', $rules['start_time']);
    }

    public function testRangeUsesArrayRule(): void
    {
        $form = new Form('test');
        $form->add(Date::make('date_range', '日期区间')->range());

        $rules = ValidationGenerator::generate($form);
        $this->assertStringContainsString('array', $rules['date_range']);
        $this->assertStringNotContainsString('date', $rules['date_range']);
    }

    public function testDatetimeRangeUsesArrayRule(): void
    {
        $form = new Form('test');
        $form->add(Datetime::make('event_time', '活动时间')->range());

        $rules = ValidationGenerator::generate($form);
        $this->assertStringContainsString('array', $rules['event_time']);
    }

    public function testTimeRangeUsesArrayRule(): void
    {
        $form = new Form('test');
        $form->add(Time::make('work_time', '营业时间')->range());

        $rules = ValidationGenerator::generate($form);
        $this->assertStringContainsString('array', $rules['work_time']);
    }

    /**
     * Get a field class instance for the given type string.
     */
    private function getFieldClassForType(string $type): string
    {
        return match ($type) {
            'text'     => Text::class,
            'textarea' => Textarea::class,
            'number'   => Number::class,
            'date'     => Date::class,
            'datetime' => Datetime::class,
            'time'     => Time::class,
            'checkbox' => Checkbox::class,
            'radio'    => \wise\form\schema\field\Radio::class,
            'select'   => Select::class,
            'switch'   => SwitchField::class,
            'file'     => File::class,
            'hidden'   => Hidden::class,
            'image'    => Image::class,
            default    => Text::class,
        };
    }
}
