<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\renderer\html\HtmlRenderer;
use wise\form\schema\tests\TestCase;

class Bootstrap5ThemeTest extends TestCase
{
    public function testSelectRendersFormSelectAndMarksSelection(): void
    {
        $html = $this->renderField('status', 'select', [
            'default' => '1',
            'props'   => $this->props(['options' => ['1' => '启用', '0' => '禁用']]),
        ]);

        $this->assertStringContainsString('class="form-select"', $html);
        $this->assertStringContainsString('<option value="1" selected>启用</option>', $html);
        $this->assertStringContainsString('<option value="0">禁用</option>', $html);
    }

    public function testSelectPlaceholderBecomesEmptyOption(): void
    {
        $html = $this->renderField('status', 'select', [
            'placeholder' => '请选择状态',
            'props'       => $this->props(['options' => ['1' => '启用']]),
        ]);

        $this->assertStringContainsString('<option value="">请选择状态</option>', $html);
    }

    public function testMultipleSelectRendersMultipleAttribute(): void
    {
        $html = $this->renderField('tags', 'select', [
            'props' => $this->props(['multiple' => true, 'options' => ['a' => 'A', 'b' => 'B']]),
        ]);

        $this->assertStringContainsString('name="tags[]"', $html);
        $this->assertStringContainsString(' multiple', $html);
    }

    public function testRadioRendersInlineChecksWithUniqueIds(): void
    {
        $html = $this->renderField('gender', 'radio', [
            'props' => $this->props(['options' => ['male' => '男', 'female' => '女']]),
        ]);

        $this->assertStringContainsString('class="form-check form-check-inline"', $html);
        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringContainsString('id="wsf-gender_male"', $html);
        $this->assertStringContainsString('id="wsf-gender_female"', $html);
        $this->assertStringContainsString('for="wsf-gender_male"', $html);
    }

    public function testCheckboxGroupRendersArrayName(): void
    {
        $html = $this->renderField('tags', 'checkbox', [
            'props' => $this->props(['multiple' => true, 'options' => ['php' => 'PHP', 'js' => 'JS']]),
        ]);

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('name="tags[]"', $html);
    }

    public function testCheckboxWithoutOptionsBecomesSingleToggle(): void
    {
        $html = $this->renderField('agree', 'checkbox', ['label' => '同意协议']);

        $this->assertStringContainsString('class="form-check"', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('同意协议', $html);
    }

    public function testSwitchRendersFormSwitchAndCheckedState(): void
    {
        $html = $this->renderField('enabled', 'switch', ['default' => true]);

        $this->assertStringContainsString('class="form-check form-switch"', $html);
        $this->assertStringContainsString('role="switch"', $html);
        $this->assertStringContainsString(' checked', $html);
    }

    public function testNumberRendersMinMaxStep(): void
    {
        $html = $this->renderField('age', 'number', [
            'props' => $this->props(['min' => 0, 'max' => 150, 'step' => 1.0]),
        ]);

        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('min="0"', $html);
        $this->assertStringContainsString('max="150"', $html);
        $this->assertStringContainsString('step="1"', $html);
    }

    public function testColorUsesFormControlColor(): void
    {
        $html = $this->renderField('theme', 'color', ['default' => '#ffffff']);

        $this->assertStringContainsString('class="form-control form-control-color"', $html);
        $this->assertStringContainsString('value="#ffffff"', $html);
    }

    public function testImageUploadAcceptsImagesAndShowsCurrentValue(): void
    {
        $html = $this->renderField('cover', 'image', ['default' => 'a.jpg']);

        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('accept="image/*"', $html);
        $this->assertStringContainsString('当前：a.jpg', $html);
    }

    public function testDatetimeLocalConvertsSpaceToT(): void
    {
        $html = $this->renderField('published_at', 'datetime', ['default' => '2026-09-22 14:30:00']);

        $this->assertStringContainsString('type="datetime-local"', $html);
        $this->assertStringContainsString('value="2026-09-22T14:30:00"', $html);
    }

    public function testTextareaEscapesValue(): void
    {
        $html = $this->renderField('summary', 'textarea', ['default' => '</textarea><script>x</script>']);

        $this->assertStringContainsString('&lt;/textarea&gt;', $html);
        $this->assertStringNotContainsString('</textarea><script>', $html);
    }

    public function testOptionLabelsAreEscaped(): void
    {
        $html = $this->renderField('evil', 'select', [
            'props' => $this->props(['options' => ['x' => '<script>alert(1)</script>']]),
        ]);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    // ──────────────────────────────────────────────
    //  测试辅助
    // ──────────────────────────────────────────────

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

        return (new HtmlRenderer())->render([
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
