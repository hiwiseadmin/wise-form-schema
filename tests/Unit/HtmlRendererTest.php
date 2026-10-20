<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\field\Text;
use wise\form\schema\Form;
use wise\form\schema\renderer\html\AbstractFormTheme;
use wise\form\schema\renderer\html\HtmlRenderer;
use wise\form\schema\tests\TestCase;

class HtmlRendererTest extends TestCase
{
    // ──────────────────────────────────────────────
    //  基础
    // ──────────────────────────────────────────────

    public function testReturnsEmptyForEmptySchema(): void
    {
        $renderer = new HtmlRenderer();

        $this->assertSame('', $renderer->render(['fields' => []]));
        $this->assertSame('', $renderer->render([]));
    }

    public function testRendersTextFieldFromForm(): void
    {
        $form = new Form('article');
        $form->add(Text::make('title', '标题')->placeholder('请输入标题'));

        $html = (new HtmlRenderer())->render($form->toArray());

        $this->assertStringContainsString('name="title"', $html);
        $this->assertStringContainsString('id="wsf-title"', $html);
        $this->assertStringContainsString('>标题<', $html);
        $this->assertStringContainsString('placeholder="请输入标题"', $html);
        $this->assertStringContainsString('form-field-container', $html);
        $this->assertStringContainsString('invalid-feedback', $html);
    }

    public function testValuesOverrideFieldDefaults(): void
    {
        $schema = $this->schema(['q' => $this->field('q', 'text', ['default' => 'DEFAULT'])]);

        $html = $this->render($schema, ['values' => ['q' => 'FROM_DB']]);

        $this->assertStringContainsString('value="FROM_DB"', $html);
        $this->assertStringNotContainsString('DEFAULT', $html);
    }

    // ──────────────────────────────────────────────
    //  安全
    // ──────────────────────────────────────────────

    public function testEscapesLabelAndValue(): void
    {
        $schema = $this->schema(['x' => $this->field('x', 'text', ['label' => '<b>bold</b>'])]);

        $html = $this->render($schema, ['values' => ['x' => '" onfocus="evil()']]);

        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
        $this->assertStringNotContainsString('onfocus="evil()"', $html);
    }

    // ──────────────────────────────────────────────
    //  校验规则映射：后端(TP) → 前端(ws-form-validator)
    // ──────────────────────────────────────────────

    public function testRequiredUsesFrontendRuleName(): void
    {
        $schema = $this->schema(['title' => $this->field('title', 'text', ['label' => '标题', 'required' => true])]);

        $html = $this->render($schema);

        // 前端规则名是 required（后端 TP 是 require，二者不可混用）
        $this->assertStringContainsString('data-validation="required"', $html);
        $this->assertStringContainsString('data-validation-message-required="请输入标题"', $html);
    }

    public function testChoiceFieldUsesSelectVerbInMessage(): void
    {
        $schema = $this->schema([
            'status' => $this->field('status', 'select', ['label' => '状态', 'required' => true]),
        ]);

        $html = $this->render($schema);

        $this->assertStringContainsString('data-validation-message-required="请选择状态"', $html);
    }

    public function testStringBetweenRuleMapsToMinMaxLength(): void
    {
        $schema = $this->schema([
            'title' => $this->field('title', 'text', ['rules' => ['between:3,20']]),
        ]);

        $html = $this->render($schema);

        $this->assertStringContainsString('data-validation="minLength:3|maxLength:20"', $html);
    }

    public function testNumberMaxRuleStaysNumericMax(): void
    {
        $schema = $this->schema([
            'age' => $this->field('age', 'number', ['rules' => ['max:100']]),
        ]);

        $html = $this->render($schema);

        // 数值字段的 max 不应被误映射为 maxLength
        $this->assertStringContainsString('data-validation="number|max:100"', $html);
    }

    public function testValidationMessageKeyIsRuleNameWithoutParameter(): void
    {
        $schema = $this->schema([
            'title' => $this->field('title', 'text', ['label' => '标题', 'required' => true, 'rules' => ['max:100']]),
        ]);

        $html = $this->render($schema);

        // 前端按规则名读取消息：data-validation-message-maxLength（不可带 :100）
        $this->assertStringContainsString('data-validation-message-maxLength="标题最多 100 个字符"', $html);
        $this->assertStringNotContainsString('data-validation-message-maxLength:', $html);
    }

    public function testNumberPropsMinMaxBecomeValidationRules(): void
    {
        $schema = $this->schema([
            'sort' => $this->field('sort', 'number', [
                'required' => true,
                'props'    => $this->props(['min' => 0, 'max' => 9999]),
            ]),
        ]);

        $html = $this->render($schema);

        $this->assertStringContainsString('data-validation="required|number|min:0|max:9999"', $html);
        $this->assertStringContainsString('data-validation-message-min="sort不能小于 0"', $html);
        $this->assertStringContainsString('data-validation-message-max="sort不能大于 9999"', $html);
    }

    public function testDatePropsMinMaxAreNotMappedToValidation(): void
    {
        // 前端 min/max 走 parseFloat，日期入参会得到 NaN，故不映射，仅保留 HTML 属性
        $schema = $this->schema([
            'day' => $this->field('day', 'date', [
                'props' => $this->props(['min' => '2026-01-01', 'max' => '2026-12-31']),
            ]),
        ]);

        $html = $this->render($schema);

        $this->assertStringContainsString('data-validation="date"', $html);
        $this->assertStringNotContainsString('min:2026', $html);
    }

    public function testUnmappableBackendRulesAreSkipped(): void
    {
        $schema = $this->schema([
            'title' => $this->field('title', 'text', ['rules' => ['alpha_dash', 'unique:article']]),
            'file'  => $this->field('file', 'file', ['rules' => ['fileSize:2048000']]),
        ]);

        $html = $this->render($schema);

        // 前端无法表达的规则一律跳过，仅由后端强制
        $this->assertStringNotContainsString('data-validation', $html);
        $this->assertStringNotContainsString('alpha_dash', $html);
        $this->assertStringNotContainsString('fileSize', $html);
    }

    public function testEmailFieldGetsEmailRule(): void
    {
        $schema = $this->schema(['mail' => $this->field('mail', 'email')]);

        $html = $this->render($schema);

        $this->assertStringContainsString('data-validation="email"', $html);
    }

    // ──────────────────────────────────────────────
    //  表单语义
    // ──────────────────────────────────────────────

    public function testMultipleSelectUsesArrayName(): void
    {
        $schema = $this->schema([
            'tags' => $this->field('tags', 'select', [
                'props' => $this->props(['multiple' => true, 'options' => ['a' => 'A']]),
            ]),
        ]);

        $html = $this->render($schema);

        $this->assertStringContainsString('name="tags[]"', $html);
    }

    public function testSingleSelectKeepsPlainName(): void
    {
        $schema = $this->schema([
            'status' => $this->field('status', 'select', [
                'props' => $this->props(['options' => ['a' => 'A']]),
            ]),
        ]);

        $html = $this->render($schema);

        $this->assertStringContainsString('name="status"', $html);
    }

    public function testHiddenFieldIsNotWrappedInRow(): void
    {
        $schema = $this->schema(['id' => $this->field('id', 'hidden', ['default' => 7])]);

        $html = $this->render($schema);

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('value="7"', $html);
        $this->assertStringNotContainsString('form-field-container', $html);
    }

    // ──────────────────────────────────────────────
    //  分组
    // ──────────────────────────────────────────────

    public function testGroupsRenderInDeclarationOrder(): void
    {
        $schema = $this->schema(
            [
                'adv_field'   => $this->field('adv_field', 'text', ['group' => 'adv']),
                'basic_field' => $this->field('basic_field', 'text', ['group' => 'basic']),
            ],
            [
                'basic' => ['name' => 'basic', 'title' => '基础信息', 'icon' => null, 'collapsed' => false],
                'adv'   => ['name' => 'adv', 'title' => '高级设置', 'icon' => null, 'collapsed' => false],
            ]
        );

        $html = $this->render($schema);

        $this->assertStringContainsString('data-group="basic"', $html);
        $this->assertStringContainsString('data-group="adv"', $html);

        // 输出顺序应遵循 groups 声明顺序，而非字段出现顺序
        $this->assertLessThan(strpos($html, '高级设置'), strpos($html, '基础信息'));
    }

    public function testUngroupedFieldsRenderWithoutGroupWrapper(): void
    {
        $schema = $this->schema(['loose' => $this->field('loose', 'text')]);

        $html = $this->render($schema);

        $this->assertStringContainsString('name="loose"', $html);
        $this->assertStringNotContainsString('data-group=', $html);
    }

    // ──────────────────────────────────────────────
    //  联动：Dependency → data-conditions
    // ──────────────────────────────────────────────

    public function testShowDependencyBecomesEqualsCondition(): void
    {
        $schema = $this->schema([
            'company_name' => $this->field('company_name', 'text', [
                'dependencies' => [['field' => 'type', 'operator' => '=', 'value' => 'company', 'action' => 'show']],
            ]),
        ]);

        $conditions = $this->conditionsFrom($this->render($schema));

        $this->assertSame([
            ['field' => 'type', 'operator' => 'equals', 'value' => 'company', 'relation' => 'AND'],
        ], $conditions);
    }

    public function testHideDependencyIsNegated(): void
    {
        $schema = $this->schema([
            'x' => $this->field('x', 'text', [
                'dependencies' => [['field' => 'type', 'operator' => '=', 'value' => 'company', 'action' => 'hide']],
            ]),
        ]);

        $conditions = $this->conditionsFrom($this->render($schema));

        $this->assertSame('not_equals', $conditions[0]['operator']);
    }

    public function testGreaterThanDependencyIsMapped(): void
    {
        $schema = $this->schema([
            'x' => $this->field('x', 'text', [
                'dependencies' => [['field' => 'age', 'operator' => '>', 'value' => 18, 'action' => 'show']],
            ]),
        ]);

        $conditions = $this->conditionsFrom($this->render($schema));

        $this->assertSame('greater_than', $conditions[0]['operator']);
    }

    public function testUnsupportedOperatorIsDropped(): void
    {
        $schema = $this->schema([
            'x' => $this->field('x', 'text', [
                'dependencies' => [['field' => 'age', 'operator' => '>=', 'value' => 18, 'action' => 'show']],
            ]),
        ]);

        // 前端无 greater_or_equal，宁可不渲染也不产出假联动
        $this->assertNull($this->conditionsFrom($this->render($schema)));
    }

    public function testUnsupportedActionIsDropped(): void
    {
        $schema = $this->schema([
            'x' => $this->field('x', 'text', [
                'dependencies' => [['field' => 'type', 'operator' => '=', 'value' => 'a', 'action' => 'disable']],
            ]),
        ]);

        // enable/disable 前端无法单独表达
        $this->assertNull($this->conditionsFrom($this->render($schema)));
    }

    public function testHideOnComparisonOperatorIsDropped(): void
    {
        $schema = $this->schema([
            'x' => $this->field('x', 'text', [
                'dependencies' => [['field' => 'age', 'operator' => '>', 'value' => 18, 'action' => 'hide']],
            ]),
        ]);

        // 取反 "> 18" 应等价于 "<= 18"，前端只能表达 "< 18"，语义不符 → 丢弃
        $this->assertNull($this->conditionsFrom($this->render($schema)));
    }

    // ──────────────────────────────────────────────
    //  主题可替换
    // ──────────────────────────────────────────────

    public function testCustomThemeFromContextIsUsed(): void
    {
        $theme = new class extends AbstractFormTheme {
            public function control(array $field, array $context): string
            {
                return 'CTRL:' . $context['name'];
            }

            public function field(array $field, string $control, array $context): string
            {
                return '[' . $control . ']';
            }
        };

        $schema = $this->schema(['a' => $this->field('a', 'text')]);
        $html   = $this->render($schema, ['theme' => $theme]);

        $this->assertSame('[CTRL:a]', $html);
    }

    // ──────────────────────────────────────────────
    //  测试辅助
    // ──────────────────────────────────────────────

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function field(string $name, string $type, array $overrides = []): array
    {
        return array_merge([
            'name'             => $name,
            'type'             => $type,
            'label'            => $name,
            'placeholder'      => '',
            'default'          => '',
            'required'         => false,
            'help'             => '',
            'rules'            => [],
            'renderable'       => true,
            'props'            => $this->props(),
            'dependencies'     => [],
            'conditional_rules' => [],
            'group'            => null,
        ], $overrides);
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

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $groups
     * @return array<string, mixed>
     */
    private function schema(array $fields, array $groups = []): array
    {
        return ['form' => 'test', 'fields' => $fields, 'groups' => $groups, 'repeaters' => []];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(array $schema, array $context = []): string
    {
        return (new HtmlRenderer())->render($schema, $context);
    }

    /**
     * 从渲染结果中解出 data-conditions（含 HTML 实体还原）。
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function conditionsFrom(string $html): ?array
    {
        if (!preg_match('/data-conditions="([^"]*)"/', $html, $matches)) {
            return null;
        }

        $json = html_entity_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return json_decode($json, true);
    }
}
