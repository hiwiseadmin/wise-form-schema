<?php

declare(strict_types=1);

namespace wise\form\schema;

use Closure;
use wise\form\schema\exception\SchemaException;
use wise\form\schema\field\AbstractField;
use wise\form\schema\field\Datetime;
use wise\form\schema\field\File;
use wise\form\schema\field\Number;
use wise\form\schema\field\Select;
use wise\form\schema\field\Text;

/**
 * Form — 表单定义容器。
 *
 * 管理字段集合、分组、联动、嵌套 Repeater，提供 toArray() 产出标准化 Schema 数组。
 */
class Form
{
    /** 表单名称 */
    private string $name;

    /** @var array<string, AbstractField> 字段集合，key 为字段名 */
    private array $fields = [];

    /** @var array<string, Group> 分组集合 */
    private array $groups = [];

    /** @var array<string, Repeater> 可重复字段组集合 */
    private array $repeaters = [];

    /** @var array<string, Closure> 预设注册表 */
    private static array $presets = [];

    /** 内置预设是否已注册 */
    private static bool $presetsRegistered = false;

    /**
     * @param string $name 表单名称
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    // ──────────────────────────────────────────────
    //  预设系统
    // ──────────────────────────────────────────────

    /**
     * 注册一个表单预设。
     *
     * 预设是一组常用字段的快捷组合，通过 usePreset() 按名称一键添加到表单中。
     *
     * @param string  $name     预设名称
     * @param Closure $callback 接收 Form 实例的回调，在其中调用 add() 添加字段
     * @return void
     */
    public static function registerPreset(string $name, Closure $callback): void
    {
        self::ensurePresetsRegistered();
        self::$presets[$name] = $callback;
    }

    /**
     * 使用一个已注册的预设。
     *
     * 执行预设回调，将预设中定义的字段批量添加到当前表单。
     * 可通过 $onlyFields 指定仅创建哪些字段，其余字段将被移除。
     *
     * @param string   $name       预设名称
     * @param string[] $onlyFields 仅创建这些字段（空数组 = 全部添加）
     * @return $this
     *
     * @example
     * // 使用全部字段（默认行为）
     * $form->usePreset('timestamps');
     *
     * // 仅创建指定字段
     * $form->usePreset('timestamps', ['create_time']);
     * $form->usePreset('userBasic', ['username', 'email']);
     */
    public function usePreset(string $name, array $onlyFields = []): self
    {
        self::ensurePresetsRegistered();
        if (!isset(self::$presets[$name])) {
            throw SchemaException::invalidField(
                $name,
                \sprintf(
                    'Preset "%s" is not registered. Available presets: %s',
                    $name,
                    \implode(', ', \array_keys(self::$presets))
                )
            );
        }

        // 记录执行前的字段，用于识别预设新增了哪些字段
        $beforeKeys = \array_keys($this->fields);
        \call_user_func(self::$presets[$name], $this);
        $afterKeys = \array_keys($this->fields);

        // 仅保留指定的字段
        if (!empty($onlyFields)) {
            $presetKeys = \array_diff($afterKeys, $beforeKeys);
            foreach ($presetKeys as $key) {
                if (!\in_array($key, $onlyFields, true)) {
                    unset($this->fields[$key]);
                }
            }
        }

        return $this;
    }

    /**
     * 确保内置预设已注册（幂等）。
     *
     * @return void
     */
    private static function ensurePresetsRegistered(): void
    {
        if (self::$presetsRegistered) {
            return;
        }
        self::$presetsRegistered = true;

        // timestamps: 创建时间 + 更新时间
        self::$presets['timestamps'] = function (Form $form): void {
            $form->add(Datetime::make('create_time')->label('创建时间'));
            $form->add(Datetime::make('update_time')->label('更新时间'));
        };

        // sortable: 排序 + 状态
        self::$presets['sortable'] = function (Form $form): void {
            $form->add(Number::make('sort_order')->label('排序')->default(0)->min(0));
            $form->add(Select::make('status')->label('状态')->options(['1' => '启用', '0' => '禁用'])->default('1'));
        };

        // softDelete: 软删除时间
        self::$presets['softDelete'] = function (Form $form): void {
            $form->add(Datetime::make('delete_time')->label('删除时间'));
        };

        // userBasic: 用户基础信息
        self::$presets['userBasic'] = function (Form $form): void {
            $form->add(Text::make('username')->label('用户名'));
            $form->add(Text::make('email')->label('邮箱')->rules('email'));
            $form->add(Text::make('phone')->label('手机号'));
            $form->add(File::make('avatar')->label('头像'));
        };
    }

    // ──────────────────────────────────────────────
    //  静态工厂方法
    // ──────────────────────────────────────────────

    /**
     * 从 Schema 数组还原 Form 实例。
     *
     * 支持 toArray() 输出的反向操作，可还原字段、分组和 Repeater。
     *
     * @param array $schema Form::toArray() 输出的 Schema 数组
     * @return self
     * @throws SchemaException 当遇到未知字段类型时
     */
    public static function fromArray(array $schema): self
    {
        self::ensurePresetsRegistered();
        $form = new self($schema['form'] ?? '');

        // 1. 还原字段
        foreach (($schema['fields'] ?? []) as $fieldName => $fieldData) {
            if (!\is_array($fieldData)) {
                continue;
            }
            $type = $fieldData['type'] ?? 'text';
            $className = AbstractField::classForType($type);
            if ($className === null) {
                throw SchemaException::unknownType($type, (string) $fieldName);
            }
            /** @var AbstractField $field */
            $field = new $className($fieldData['name'] ?? $fieldName);
            // 还原所有 fluent 属性（仅在 key 存在时设置，保留字段类的原始默认值）
            if (\array_key_exists('label', $fieldData)) {
                $field->label($fieldData['label']);
            }
            if (\array_key_exists('placeholder', $fieldData)) {
                $field->placeholder($fieldData['placeholder']);
            }
            if (\array_key_exists('default', $fieldData)) {
                $field->default($fieldData['default']);
            }
            if (!empty($fieldData['required'])) {
                $field->required();
            }
            if (\array_key_exists('help', $fieldData)) {
                $field->help($fieldData['help']);
            }
            if (\array_key_exists('rules', $fieldData)) {
                $field->rules($fieldData['rules']);
            }
            $props = $fieldData['props'] ?? [];
            if (!empty($props['options'])) {
                $field->options($props['options']);
            }
            if (!empty($props['multiple'])) {
                $field->multiple();
            }
            if (!empty($props['range'])) {
                $field->range($fieldData['default'] ?? []);
            }
            if (isset($props['step'])) {
                $field->step((float) $props['step']);
            }
            if (isset($props['min'])) {
                $field->min($props['min']);
            }
            if (isset($props['max'])) {
                $field->max($props['max']);
            }
            if (isset($props['format'])) {
                $field->format($props['format']);
            }
            if (isset($fieldData['renderable'])) {
                $field->renderable($fieldData['renderable']);
            }
            if (!empty($fieldData['group'])) {
                $field->group($fieldData['group']);
            }
            // 还原 dependencies
            foreach (($fieldData['dependencies'] ?? []) as $depData) {
                if (\is_array($depData)) {
                    $dep = new Dependency(
                        $depData['field'] ?? '',
                        $depData['operator'] ?? '=',
                        $depData['value'] ?? '',
                        $depData['action'] ?? 'show'
                    );
                    $field->dependency($dep);
                }
            }
            // 还原 conditional_rules
            foreach (($fieldData['conditional_rules'] ?? []) as $rule) {
                if (!empty($rule['rule']) && !empty($rule['field'])) {
                    $field->requiredIf($rule['field'], $rule['operator'] ?? '=', $rule['value'] ?? '');
                }
            }
            $form->fields[$fieldName] = $field;
        }

        // 2. 还原分组
        foreach (($schema['groups'] ?? []) as $groupName => $groupData) {
            if (!\is_array($groupData)) {
                continue;
            }
            $form->groups[$groupName] = new Group(
                $groupData['name'] ?? $groupName,
                $groupData['title'] ?? '',
                $groupData['icon'] ?? null,
                $groupData['collapsed'] ?? false
            );
        }

        // 3. 还原 repeaters
        foreach (($schema['repeaters'] ?? []) as $repeaterName => $repeaterData) {
            if (!\is_array($repeaterData)) {
                continue;
            }
            $form->repeaters[$repeaterName] = Repeater::fromArray($repeaterData);
        }

        return $form;
    }

    // ──────────────────────────────────────────────
    //  实例方法
    // ──────────────────────────────────────────────

    /**
     * 添加字段。
     *
     * @param AbstractField $field
     * @return $this
     * @throws SchemaException 当字段名重复时
     */
    public function add(AbstractField $field): self
    {
        $fieldName = $field->getName();

        if (isset($this->fields[$fieldName])) {
            throw SchemaException::duplicateField($fieldName);
        }

        $this->fields[$fieldName] = $field;
        return $this;
    }

    /**
     * 移除指定名称的字段。
     *
     * 常用于使用预设后删除不需要的字段。
     *
     * @param string $name 字段名
     * @return $this
     */
    public function remove(string $name): self
    {
        unset($this->fields[$name]);
        return $this;
    }

    /**
     * 注册一个分组。
     *
     * 回调内通过 $this->add() 新增的字段会自动标记 group 归属。
     *
     * @param string      $name      分组标识名
     * @param string      $title     分组标题
     * @param string|null $icon      分组图标
     * @param bool        $collapsed 是否默认折叠
     * @param Closure|null $callback 可选回调（暂留扩展）
     * @return $this
     */
    public function group(
        string $name,
        string $title,
        ?string $icon = null,
        bool $collapsed = false,
        ?Closure $callback = null
    ): self {
        $this->groups[$name] = new Group($name, $title, $icon, $collapsed);

        if ($callback !== null) {
            $beforeKeys = \array_keys($this->fields);
            $callback($this);
            $afterKeys = \array_keys($this->fields);
            foreach (\array_diff($afterKeys, $beforeKeys) as $newKey) {
                $this->fields[$newKey]->group($name);
            }
        }

        return $this;
    }

    /**
     * 添加联动条件。
     *
     * 回调内的所有字段在 toArray() 时都会被输出，
     * 但每条字段会额外携带给定的 Dependency。
     *
     * @param string                                          $field    依赖字段名
     * @param string                                          $operator 操作符
     * @param mixed                                           $value    期望值
     * @param Closure(Form, \wise\form\schema\Dependency): void $callback 回调接收 (Form, Dependency) 两个参数
     * @return $this
     */
    public function when(
        string $field,
        string $operator,
        mixed $value,
        Closure $callback
    ): self {
        $dependency = new Dependency($field, $operator, $value);
        $beforeKeys = \array_keys($this->fields);
        $callback($this, $dependency);
        $afterKeys = \array_keys($this->fields);
        // 自动为回调内新增的字段注入 dependency（与 group() 行为一致）
        foreach (\array_diff($afterKeys, $beforeKeys) as $newKey) {
            $this->fields[$newKey]->dependency($dependency);
        }
        return $this;
    }

    /**
     * 添加可重复字段组。
     *
     * @param string  $name     唯一标识名
     * @param string  $label    显示标签
     * @param Closure $fields   回调接收子 Form 实例
     * @return $this
     */
    public function repeater(string $name, string $label, Closure $fields): self
    {
        $this->repeaters[$name] = new Repeater($name, $label, $fields);
        return $this;
    }

    /**
     * 获取表单名称。
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取所有字段。
     *
     * @return array<string, AbstractField>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * 获取所有分组。
     *
     * @return array<string, Group>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * 获取所有 Repeater。
     *
     * @return array<string, Repeater>
     */
    public function getRepeaters(): array
    {
        return $this->repeaters;
    }

    /**
     * 转为标准化 Schema 数组。
     *
     * 输出格式：
     * [
     *   'form'   => 'form_name',
     *   'fields' => [ 'field_name' => [...], ... ],
     *   'groups'   => [ 'group_name' => [...], ... ],
     *   'repeaters' => [ 'repeater_name' => [...], ... ],
     * ]
     *
     * @return array
     */
    public function toArray(): array
    {
        $fieldArray = [];
        foreach ($this->fields as $fieldName => $field) {
            $fieldArray[$fieldName] = $field->toArray();
        }

        $groupArray = [];
        foreach ($this->groups as $groupName => $group) {
            $groupArray[$groupName] = $group->toArray();
        }

        $repeaterArray = [];
        foreach ($this->repeaters as $repeaterName => $repeater) {
            $repeaterArray[$repeaterName] = $repeater->toArray();
        }

        return [
            'form'      => $this->name,
            'fields'    => $fieldArray,
            'groups'    => $groupArray,
            'repeaters' => $repeaterArray,
        ];
    }
}
