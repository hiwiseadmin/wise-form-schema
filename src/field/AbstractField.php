<?php

declare(strict_types=1);

namespace wise\form\schema\field;

use wise\form\schema\enums\FieldType;

/**
 * AbstractField — 所有表单字段的抽象基类。
 *
 * 承载全部 Fluent 方法（label / placeholder / default / required / help / rules /
 * options / multiple / step / min / max / format / renderable / dependency / group /
 * requiredIf / requiredUnless），子类仅需覆写 getType() 返回具体字段类型。
 */
abstract class AbstractField
{
    /** 字段名（唯一标识） */
    protected string $name;

    /** 显示标签 */
    protected string $label = '';

    /** 占位提示 */
    protected string $placeholder = '';

    /** 默认值 */
    protected mixed $default = '';

    /** 是否必填 */
    protected bool $required = false;

    /** 帮助文本 */
    protected string $help = '';

    /** 自定义验证规则 */
    protected array $rules = [];

    /** 选项列表（select / radio / checkbox 使用，value => label 关联数组） */
    protected array $options = [];

    /** 是否多选 / 多文件上传 */
    protected bool $multiple = false;

    /** 是否区间模式（date/datetime/time 使用） */
    protected bool $range = false;

    /** 步进值（number 使用） */
    protected ?float $step = null;

    /** 最小值 */
    protected mixed $min = null;

    /** 最大值 */
    protected mixed $max = null;

    /** 格式（如日期格式） */
    protected ?string $format = null;

    /** 是否渲染（true 或自定义渲染配置数组） */
    protected bool|array $renderable = true;

    /** 联动依赖项（Dependency 对象数组或原始数组） */
    protected array $dependencies = [];

    /** 所属分组名 */
    protected ?string $group = null;

    /**
     * 条件验证规则（requiredIf / requiredUnless 产生）。
     *
     * 结构：`[{rule, field, operator, value}]`。
     * 其中 `rule` 是**语义标识**（值为 `'required'`，与前端校验器的规则词汇一致），
     * **不是** ThinkPHP 的规则名 `require` —— 后端规则名由 ValidationGenerator 单独生成。
     */
    protected array $conditionalRules = [];

    /**
     * 构造器。
     *
     * @param string      $name  字段名
     * @param string|null $label 显示标签（可选，默认与 name 相同）
     */
    public function __construct(string $name, ?string $label = null)
    {
        $this->name = $name;
        $this->label = $label ?? $name;
    }

    /**
     * 静态工厂方法。
     *
     * @param string      $name  字段名
     * @param string|null $label 显示标签
     * @return static
     */
    public static function make(string $name, ?string $label = null): static
    {
        return new static($name, $label);
    }

    /**
     * 获取字段类型。
     *
     * @return FieldType
     */
    abstract public function getType(): FieldType;

    // ──────────────────────────────────────────────
    //  Fluent API 方法（16 个），全部返回 $this
    // ──────────────────────────────────────────────

    /**
     * 设置显示标签。
     *
     * @param string $label
     * @return $this
     */
    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /**
     * 设置占位提示文本。
     *
     * @param string $placeholder
     * @return $this
     */
    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * 设置默认值。
     *
     * @param mixed $default
     * @return $this
     */
    public function default(mixed $default): static
    {
        $this->default = $default;
        return $this;
    }

    /**
     * 设置是否必填。
     *
     * @param bool $required
     * @return $this
     */
    public function required(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }

    /**
     * 设置帮助文本。
     *
     * @param string $help
     * @return $this
     */
    public function help(string $help): static
    {
        $this->help = $help;
        return $this;
    }

    /**
     * 设置自定义验证规则。
     *
     * @param string|array $rules 单个规则字符串或规则数组
     * @return $this
     */
    public function rules(string|array $rules): static
    {
        $this->rules = \is_array($rules) ? $rules : [$rules];
        return $this;
    }

    /**
     * 设置选项列表（value => label 关联数组）。
     *
     * @param array $options
     * @return $this
     */
    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    /**
     * 设置是否多选。
     *
     * @param bool $multiple
     * @return $this
     */
    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;
        return $this;
    }

    /**
     * 设为区间模式（date / datetime / time 使用）。
     *
     * 区间模式下：
     * - 默认值自动设为 []，也可直接传入默认值
     * - 验证规则使用 array
     * - DataFilter 将值转为数组
     * - 前端渲染 RangePicker 组件
     *
     * @param array $default 区间默认值，如 ['2025-01-01', '2025-12-31']
     * @return $this
     */
    public function range(array $default = []): static
    {
        $this->range = true;
        $this->default = $default;
        return $this;
    }

    /**
     * 设置步进值。
     *
     * @param float $step
     * @return $this
     */
    public function step(float $step): static
    {
        $this->step = $step;
        return $this;
    }

    /**
     * 设置最小值。
     *
     * @param mixed $min
     * @return $this
     */
    public function min(mixed $min): static
    {
        $this->min = $min;
        return $this;
    }

    /**
     * 设置最大值。
     *
     * @param mixed $max
     * @return $this
     */
    public function max(mixed $max): static
    {
        $this->max = $max;
        return $this;
    }

    /**
     * 设置格式（如日期格式 'Y-m-d'）。
     *
     * @param string|null $format 传 null 表示不指定格式（与属性默认值一致）
     * @return $this
     */
    public function format(?string $format): static
    {
        $this->format = $format;
        return $this;
    }

    /**
     * 设置渲染配置。
     *
     * @param bool|array $renderable true 或自定义渲染配置数组
     * @return $this
     */
    public function renderable(bool|array $renderable = true): static
    {
        $this->renderable = $renderable;
        return $this;
    }

    /**
     * 添加联动依赖（幂等）。
     *
     * 相同 field/operator/value/action 的依赖只保留一条：
     * Form::when() 会自动为回调内新增字段注入 dependency，若回调中又显式调用
     * ->dependency($dep)，两者等价，去重可避免产生重复的联动规则。
     *
     * @param \wise\form\schema\Dependency $dependency
     * @return $this
     */
    public function dependency(\wise\form\schema\Dependency $dependency): static
    {
        foreach ($this->dependencies as $existing) {
            if ($existing instanceof \wise\form\schema\Dependency
                && $existing->toArray() == $dependency->toArray()
            ) {
                return $this;
            }
        }

        $this->dependencies[] = $dependency;
        return $this;
    }

    /**
     * 设置所属分组。
     *
     * @param string $group 分组名
     * @return $this
     */
    public function group(string $group): static
    {
        $this->group = $group;
        return $this;
    }

    /**
     * 条件必填 — 当指定字段满足条件时，当前字段变为必填。
     *
     * @param string $field    依赖字段名
     * @param string $operator 操作符（'=', '!=', 'in', 'not_in', '>', '<', '>=', '<='）
     * @param mixed  $value    期望值
     * @return $this
     */
    public function requiredIf(string $field, string $operator, mixed $value): static
    {
        $this->conditionalRules[] = [
            'rule'     => 'required',
            'field'    => $field,
            'operator' => $operator,
            'value'    => $value,
        ];
        return $this;
    }

    /** @var array<string, string> 操作符取反映射 */
    private static array $operatorNegation = [
        '='      => '!=',
        '!='     => '=',
        '>'      => '<=',
        '>='     => '<',
        '<'      => '>=',
        '<='     => '>',
        'in'     => 'not_in',
        'not_in' => 'in',
    ];

    /**
     * 条件非必填 — 当指定字段满足条件时，当前字段变为非必填。
     *
     * 内部通过取反操作符实现：requiredIf(negatedCondition) 等价于 requiredUnless(condition)。
     *
     * @param string $field    依赖字段名
     * @param string $operator 操作符（'=', '!=', '>', '<', '>=', '<=', 'in', 'not_in'）
     * @param mixed  $value    期望值
     * @return $this
     */
    public function requiredUnless(string $field, string $operator, mixed $value): static
    {
        $negated = self::$operatorNegation[$operator] ?? '!=';
        $this->conditionalRules[] = [
            'rule'     => 'required',
            'field'    => $field,
            'operator' => $negated,
            'value'    => $value,
        ];
        return $this;
    }

    /**
     * 根据字段类型字符串获取对应的字段类名（全限定）。
     *
     * 委托给 FieldType 枚举，枚举中持有唯一的类型→类名映射。
     *
     * @param string $type 字段类型字符串（如 'text', 'select' 等）
     * @return string|null 对应的类名字符串，未知类型返回 null
     */
    public static function classForType(string $type): ?string
    {
        return \wise\form\schema\enums\FieldType::tryFrom($type)?->getClass();
    }

    // ──────────────────────────────────────────────
    //  访问器
    // ──────────────────────────────────────────────

    /**
     * 获取字段名。
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取显示标签。
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * 获取占位提示文本。
     *
     * @return string
     */
    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    /**
     * 获取默认值。
     *
     * @return mixed
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * 判断是否必填。
     *
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * 获取帮助文本。
     *
     * @return string
     */
    public function getHelp(): string
    {
        return $this->help;
    }

    /**
     * 获取自定义验证规则数组。
     *
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * 获取选项列表（value => label 关联数组）。
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * 判断是否多选 / 多文件上传模式。
     *
     * @return bool
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * 判断是否区间模式（date/datetime/time 使用）。
     *
     * @return bool
     */
    public function isRange(): bool
    {
        return $this->range;
    }

    /**
     * 获取步进值。
     *
     * @return float|null
     */
    public function getStep(): ?float
    {
        return $this->step;
    }

    /**
     * 获取最小值。
     *
     * @return mixed
     */
    public function getMin(): mixed
    {
        return $this->min;
    }

    /**
     * 获取最大值。
     *
     * @return mixed
     */
    public function getMax(): mixed
    {
        return $this->max;
    }

    /**
     * 获取格式（如日期格式 'Y-m-d'）。
     *
     * @return string|null
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }

    /**
     * 获取渲染配置。
     *
     * @return bool|array
     */
    public function getRenderable(): bool|array
    {
        return $this->renderable;
    }

    /**
     * 判断是否可渲染。
     *
     * @return bool
     */
    public function isRenderable(): bool
    {
        return (bool) $this->renderable;
    }

    /**
     * 获取联动依赖项列表。
     *
     * @return array
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * 获取所属分组名。
     *
     * @return string|null
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * 获取条件验证规则（requiredIf / requiredUnless 产生的规则）。
     *
     * @return array
     */
    public function getConditionalRules(): array
    {
        return $this->conditionalRules;
    }

    // ──────────────────────────────────────────────
    //  toArray
    // ──────────────────────────────────────────────

    /**
     * 将字段转换为标准化 Schema 数组。
     *
     * 子类可覆写 getExtraProps() 来追加类型特有属性。
     *
     * @return array
     */
    public function toArray(): array
    {
        $dependencyArray = [];
        foreach ($this->dependencies as $dep) {
            $dependencyArray[] = $dep instanceof \wise\form\schema\Dependency
                ? $dep->toArray()
                : $dep;
        }

        return [
            'name'             => $this->name,
            'type'             => $this->getType()->value,
            'label'            => $this->label,
            'placeholder'      => $this->placeholder,
            'default'          => $this->default,
            'required'         => $this->required,
            'help'             => $this->help,
            'rules'            => $this->rules,
            'renderable'       => $this->renderable,
            'props'            => $this->buildProps(),
            'dependencies'     => $dependencyArray,
            'group'            => $this->group,
            'conditional_rules' => $this->conditionalRules,
        ];
    }

    /**
     * 构建字段的 props 子数组。
     *
     * 子类可覆写以追加类型特有属性。
     *
     * @return array
     */
    protected function buildProps(): array
    {
        return [
            'multiple' => $this->multiple,
            'range'    => $this->range,
            'min'      => $this->min,
            'max'      => $this->max,
            'step'     => $this->step,
            'format'   => $this->format,
            'options'  => $this->options,
        ];
    }
}
