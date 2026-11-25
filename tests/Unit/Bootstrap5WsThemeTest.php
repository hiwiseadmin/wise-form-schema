<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\renderer\html\HtmlRenderer;
use wise\form\schema\renderer\html\theme\Bootstrap5WsTheme;
use wise\form\schema\tests\TestCase;

/**
 * Bootstrap5WsTheme：把 date / time / datetime / select（单选）/ color
 * 换成 ws-* 组件的宿主标记。
 *
 * 断言重点是「组件的接入契约」，而不只是标记长什么样：
 *   - 值能不能提交（name 或 data-name 至少有一个能落到表单数据里）
 *   - 校验能不能挂上（data-validation 必须在最终承载值的元素上）
 */
class Bootstrap5WsThemeTest extends TestCase
{
    public function testDateUsesWsDatePickerWithTextInput(): void
    {
        $html = $this->renderField('published_at', 'date', [
            'default'  => '2026-10-27',
            'required' => true,
        ]);

        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringNotContainsString('type="date"', $html);
        $this->assertStringContainsString('data-role="wsdatepicker"', $html);
        $this->assertStringContainsString('data-format="yyyy-MM-dd"', $html);
        $this->assertStringContainsString('value="2026-10-27"', $html);
    }

    public function testDateKeepsNameAndValidationOnTheInputItself(): void
    {
        $html = $this->renderField('published_at', 'date', ['required' => true]);

        $this->assertStringContainsString('name="published_at"', $html);
        $this->assertStringContainsString('data-validation="required', $html);
        $this->assertStringContainsString('id="wsf-published_at"', $html);
    }

    public function testTimeUsesHourMinuteFormatToMatchValidationRule(): void
    {
        $html = $this->renderField('start_at', 'time', ['default' => '08:30']);

        $this->assertStringContainsString('data-type="time"', $html);
        $this->assertStringContainsString('data-format="HH:mm"', $html);
        $this->assertStringContainsString('value="08:30"', $html);
        $this->assertStringNotContainsString('<input type="time"', $html);
    }

    public function testDatetimeKeepsSchemaValueWithoutTConversion(): void
    {
        $html = $this->renderField('created_at', 'datetime', ['default' => '2026-09-22 14:30:00']);

        $this->assertStringContainsString('data-type="datetime"', $html);
        $this->assertStringContainsString('data-format="yyyy-MM-dd HH:mm:ss"', $html);
        $this->assertStringContainsString('value="2026-09-22 14:30:00"', $html);
        $this->assertStringNotContainsString('datetime-local', $html);
    }

    public function testSingleSelectIsPromotedInPlace(): void
    {
        $html = $this->renderField('status', 'select', [
            'default' => '1',
            'props'   => $this->props(['options' => ['1' => '启用', '0' => '禁用']]),
        ]);

        $this->assertStringContainsString('<select data-role="wsselect"', $html);
        $this->assertStringContainsString('class="form-select"', $html);
        $this->assertStringContainsString('<option value="1" selected>启用</option>', $html);
    }

    public function testMultipleSelectIsAlsoPromoted(): void
    {
        $html = $this->renderField('tags', 'select', [
            'props' => $this->props(['multiple' => true, 'options' => ['a' => 'A', 'b' => 'B']]),
        ]);

        $this->assertStringContainsString('<select data-role="wsselect"', $html);
        $this->assertStringContainsString('name="tags[]"', $html);
        $this->assertStringContainsString(' multiple', $html);
        $this->assertStringContainsString('<option value="a">A</option>', $html);
    }

    public function testColorUsesWsColorPickerHost(): void
    {
        $html = $this->renderField('theme_color', 'color', [
            'default'  => '#ff0000',
            'required' => true,
        ]);

        $this->assertStringContainsString('<div', $html);
        $this->assertStringContainsString('data-role="wscolor"', $html);
        $this->assertStringContainsString('data-name="theme_color"', $html);
        $this->assertStringContainsString('data-value="#ff0000"', $html);
        $this->assertStringContainsString('data-validation="required', $html);
        $this->assertStringNotContainsString(' name="theme_color"', $html);
    }

    public function testUntouchedTypesKeepNativeControls(): void
    {
        $number = $this->renderField('sort', 'number', ['props' => $this->props(['min' => 0, 'max' => 99])]);
        $radio  = $this->renderField('gender', 'radio', [
            'props' => $this->props(['options' => ['male' => '男', 'female' => '女']]),
        ]);

        $this->assertStringContainsString('type="number"', $number);
        $this->assertStringNotContainsString('data-role="ws', $number);

        $this->assertStringContainsString('class="form-check form-check-inline"', $radio);
        $this->assertStringNotContainsString('data-role="ws', $radio);
    }

    public function testImageUsesWsUploadHost(): void
    {
        $html = $this->renderField('cover', 'image', [
            'default'  => '/uploads/a.jpg',
            'required' => true,
        ]);

        $this->assertStringContainsString('data-role="wsupload"', $html);
        $this->assertStringContainsString('data-name="cover"', $html);
        $this->assertStringContainsString('data-accept="images"', $html);
        $this->assertStringContainsString('data-value="/uploads/a.jpg"', $html);
        $this->assertStringContainsString('data-validation="required', $html);
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertStringNotContainsString(' name="cover"', $html);
    }

    public function testMultiImageUsesNativeNameArraySemantics(): void
    {
        $html = $this->renderField('images', 'image', [
            'default' => ['/uploads/a.jpg', '/uploads/b.jpg'],
            'props'   => $this->props(['multiple' => true]),
        ]);

        $this->assertStringContainsString('data-name="images"', $html);
        $this->assertStringContainsString('data-multiple="true"', $html);
        $this->assertMatchesRegularExpression('/data-value="[^"]*uploads\/a\.jpg[^"]*uploads\/b\.jpg[^"]*"/', $html);
    }

    public function testFileFieldUsesGenericAcceptAndUploadUrlOverride(): void
    {
        $html = $this->renderField('attachment', 'file', [
            'props' => $this->props(['uploadUrl' => '/custom/upload']),
        ]);

        $this->assertStringContainsString('data-accept="file"', $html);
        $this->assertStringContainsString('data-url="/custom/upload"', $html);
    }

    public function testEmptyImageOmitsDataValue(): void
    {
        $html = $this->renderField('cover', 'image');

        $this->assertStringContainsString('data-role="wsupload"', $html);
        $this->assertStringNotContainsString('data-value=', $html);
        $this->assertStringNotContainsString('data-url=', $html);
    }


    /**
     * @param array<string, mixed> $overrides
     */
    private function renderField(string $name, string $type, array $overrides = []): string
    {
        $field = array_merge([
            'name'              => $name,
            'type'              => $type,
            'label'             => $name,
            'placeholder'       => '',
            'default'           => '',
            'required'          => false,
            'help'              => '',
            'rules'             => [],
            'renderable'        => true,
            'props'             => $this->props(),
            'dependencies'      => [],
            'conditional_rules' => [],
            'group'             => null,
        ], $overrides);

        return (new HtmlRenderer(new Bootstrap5WsTheme()))->render([
            'form'      => 'test',
            'fields'    => [$name => $field],
            'groups'    => [],
            'repeaters' => [],
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function props(array $overrides = []): array
    {
        return array_merge([
            'multiple' => false,
            'range'    => false,
            'min'      => null,
            'max'      => null,
            'step'     => null,
            'format'   => null,
            'options'  => [],
        ], $overrides);
    }
}
