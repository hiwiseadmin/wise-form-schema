# wise-form-schema

> 适用：WiseAdmin 1.0+ / PHP 8.1+（纯 PHP DSL，零框架依赖）
> 状态：稳定
> 可独立使用：是 —— 字段构建器、验证规则生成与数据过滤均可脱离 ThinkPHP 使用
> 直接依赖：无

WiseAdmin 统一表单定义语言内核 — Fluent API 字段构建器 + 验证规则生成 + 数据过滤。

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.1-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-Apache%202.0-green)](LICENSE)

> **定位**：该包是 WiseAdmin 生态的"字段定义"唯一真相来源（Single Source of Truth），
> 被多个工具包（CRUD 生成器、列表过滤器、数据导入等）复用。
>
> **零框架依赖**：核心不依赖 ThinkPHP，仅 `php >= 8.1` + `ext-json`。

---

## 目录

- [安装](#安装)
- [快速开始](#快速开始)
- [字段构建器（Fluent API）](#字段构建器fluent-api)
- [Form 集合](#form-集合)
- [验证规则生成](#验证规则生成)
- [数据过滤](#数据过滤)
- [渲染适配器](#渲染适配器)
- [Schema 输出规范](#schema-输出规范)
- [异常体系](#异常体系)
- [扩展自定义字段](#扩展自定义字段)
- [API 参考](#api-参考)
- [测试](#测试)
- [许可](#许可)

---

## 安装

```bash
composer require wiseadmin/wise-form-schema
```

## 5 分钟最小示例

> 说明：核心零框架依赖，安装后即可使用。

```php
use wise\form\schema\Form;
use wise\form\schema\field\{Text, Select, Number, SwitchField, Textarea};
use wise\form\schema\ValidationGenerator;
use wise\form\schema\DataFilter;

$form = new Form('user_edit');
$form->add(Text::make('username')->label('用户名')->required()->rules('alpha_dash|between:3,20'))
     ->add(Select::make('status')->label('状态')->options(['active' => '激活', 'inactive' => '禁用']))
     ->add(Number::make('age')->label('年龄')->min(0)->max(150))
     ->add(SwitchField::make('vip')->label('VIP')->default(false))
     ->add(Textarea::make('bio')->label('简介')->placeholder('介绍一下自己'));

$schema = $form->toArray();
$rules  = ValidationGenerator::generate($form);
$clean  = (new DataFilter())->filter($schema, $_POST);

print_r([$schema, $rules, $clean]);
```

## 快速开始

```php
use wise\form\schema\Form;
use wise\form\schema\field\{Text, Select, Number, SwitchField, Textarea};
use wise\form\schema\ValidationGenerator;
use wise\form\schema\DataFilter;

// 1. 定义表单
$form = new Form('user_edit');
$form->add(Text::make('username')->label('用户名')->required()->rules('alpha_dash|between:3,20'))
     ->add(Select::make('status')->label('状态')->options(['active' => '激活', 'inactive' => '禁用']))
     ->add(Number::make('age')->label('年龄')->min(0)->max(150))
     ->add(SwitchField::make('vip')->label('VIP')->default(false))
     ->add(Textarea::make('bio')->label('简介')->placeholder('介绍一下自己'));

// 2. 获取 Schema（前端消费）
$schema = $form->toArray();

// 3. 生成验证规则（后端验证）
$rules = ValidationGenerator::generate($form);
// ['username' => 'require|string|alpha_dash|between:3,20', 'status' => 'string', ...]

// 4. 过滤并清洗输入数据
$clean = (new DataFilter())->filter($schema, $_POST);
```

---

## 字段构建器（Fluent API）

所有 17 种字段类型共享统一的 Fluent API，每个方法返回 `$this`，支持链式调用。

### 字段类型

| 类名 | 类型值 | 说明 |
|------|--------|------|
| `Text` | `text` | 文本输入 |
| `Select` | `select` | 下拉选择（支持多选） |
| `Number` | `number` | 数字输入 |
| `Textarea` | `textarea` | 多行文本 |
| `Date` | `date` | 日期选择（默认格式 Y-m-d） |
| `Datetime` | `datetime` | 日期时间选择（默认格式 Y-m-d H:i:s） |
| `Time` | `time` | 时间选择（默认格式 H:i:s） |
| `Checkbox` | `checkbox` | 复选框 |
| `Radio` | `radio` | 单选按钮 |
| `File` | `file` | 文件上传（支持多文件） |
| `SwitchField` | `switch` | 开关 |
| `Hidden` | `hidden` | 隐藏域 |
| `Image` | `image` | 图片上传（通过 `multiple()` 切换单图/多图） |
| `Editor` | `editor` | 富文本编辑器 |
| `Color` | `color` | 颜色选择器 |
| `Password` | `password` | 密码输入 |
| `Email` | `email` | 邮箱输入 |

总计 **17 种**字段类型。`Image`、`File`、`Select` 统一通过 `multiple()` 方法切换单/多模式，无需独立的 `Images`/`Files`/`Selects` 类。

### 通用方法

所有字段类型继承自 `AbstractField`，提供以下方法：

| 方法 | 签名 | 说明 |
|------|------|------|
| `make()` | `static make(string $name, ?string $label = null): static` | 静态工厂 |
| `label()` | `label(string $label): static` | 显示标签 |
| `placeholder()` | `placeholder(string $text): static` | 占位提示 |
| `default()` | `default(mixed $value): static` | 默认值 |
| `required()` | `required(bool $bool = true): static` | 必填标记 |
| `help()` | `help(string $text): static` | 帮助文本 |
| `rules()` | `rules(string\|array $rules): static` | 附加验证规则 |
| `options()` | `options(array $options): static` | 选项（value => label） |
| `multiple()` | `multiple(bool $bool = true): static` | 多选/多文件 |
| `range()` | `range(array $default = []): static` | 区间模式（date/datetime/time 使用） |
| `step()` | `step(float $step): static` | 步进值 |
| `min()` | `min(mixed $min): static` | 最小值 |
| `max()` | `max(mixed $max): static` | 最大值 |
| `format()` | `format(string $format): static` | 日期格式 |
| `renderable()` | `renderable(bool\|array $config = true): static` | 渲染配置 |
| `dependency()` | `dependency(Dependency $dep): static` | 联动依赖 |
| `toArray()` | `toArray(): array` | 输出 Schema 数组 |

### 使用示例

```php
// 文本
Text::make('title')->label('标题')->required()->placeholder('请输入标题')->rules('max:100');

// 下拉
Select::make('category')->label('分类')->options([
    'news'    => '新闻',
    'blog'    => '博客',
    'product' => '产品',
])->default('news');

// 数字
Number::make('price')->label('价格')->min(0)->max(99999)->step(0.01);

// 多行文本
Textarea::make('content')->label('内容')->placeholder('请输入内容')->rules('min:10');

// 日期
Date::make('publish_date')->label('发布日期')->format('Y-m-d')->required();

// 日期时间
Datetime::make('created_at')->label('创建时间')->format('Y-m-d H:i:s');

// 日期区间（通过 range() 开启，默认值自动设为 []，也可直接传入）
Date::make('activity_period')->label('活动时间')->range();
Date::make('holiday')->label('假期')->range(['2025-01-01', '2025-02-28']);
Datetime::make('event_time')->label('活动时段')->range();
Time::make('work_time')->label('营业时间')->range();
// → 前端渲染 RangePicker，提交值: ['2025-01-01', '2025-12-31']

// 复选框（多选）
Checkbox::make('tags')->label('标签')->options([
    'hot'   => '热门',
    'new'   => '新发布',
    'top'   => '置顶',
])->multiple();

// 单选
Radio::make('gender')->label('性别')->options([
    'male'   => '男',
    'female' => '女',
])->default('male');

// 文件
File::make('avatar')->label('头像')->multiple()->rules('fileSize:2048000|fileExt:jpg,png,gif');

// 开关
SwitchField::make('enabled')->label('启用')->default(true);

// 隐藏域
Hidden::make('id')->default(0);

// 单图片
Image::make('cover')->label('封面图')->rules('fileSize:5120000|fileExt:jpg,png,gif');

// 多图片（通过 multiple() 切换）
Image::make('gallery')->label('图集')->multiple()->rules('fileSize:10240000|fileExt:jpg,png,webp');

// 多文件上传
File::make('attachments')->label('附件')->multiple()->rules('fileSize:10240000');

// 多选下拉
Select::make('tags')->label('标签')->options(['php' => 'PHP', 'js' => 'JavaScript'])->multiple();

// 富文本
Editor::make('content')->label('正文')->placeholder('请输入富文本内容');

// 颜色
Color::make('theme_color')->label('主题色')->default('#1890ff');

// 密码
Password::make('password')->label('密码')->required()->rules('between:6,32');

// 邮箱
Email::make('email')->label('邮箱')->required()->rules('unique:users');
```

---

## Form 集合

`Form` 类是字段的集合容器，支持**分组**、**联动**、**嵌套**。

### 基本操作

```php
$form = new Form('article');

// 添加字段
$form->add(Text::make('title')->label('标题')->required());

// 移除字段（常用于移除预设中不需要的字段）
$form->remove('phone');              // 移除 userBasic 预设中的手机号字段

// 获取字段
$fields = $form->getFields();         // array<string, AbstractField>

// 输出 Schema
$schema = $form->toArray();           // 标准化数组
```

### 预设系统（Presets）

预设是一组常用字段的快捷组合，通过 `usePreset()` 按名称一键添加到表单中。

#### 内置预设

| 预设名 | 包含字段 | 说明 |
|--------|---------|------|
| `timestamps` | `create_time` (Datetime)<br>`update_time` (Datetime) | 实体创建/更新时间戳 |
| `sortable` | `sort_order` (Number, 默认 0)<br>`status` (Select, 1 启用 / 0 禁用) | 排序 + 状态 |
| `softDelete` | `delete_time` (Datetime) | 软删除标记 |
| `userBasic` | `username` (Text)<br>`email` (Text, 规则 email)<br>`phone` (Text)<br>`avatar` (File) | 用户基础信息 |

#### 使用预设

```php
// 使用全部预设字段（默认行为）
$form = new Form('article');
$form->usePreset('timestamps')        // 添加 create_time + update_time
     ->usePreset('sortable')          // 添加 sort_order + status
     ->add(Text::make('title'));      // 继续添加自定义字段

// 仅选择预设中的部分字段
$form->usePreset('userBasic', ['username', 'email']);   // 只需用户名和邮箱
$form->usePreset('timestamps', ['create_time']);         // 只需创建时间
```

#### 注册自定义预设

```php
use wise\form\schema\Form;
use wise\form\schema\field\{Text, Select, Number};

Form::registerPreset('productInfo', function (Form $form): void {
    $form->add(Text::make('product_code')->label('产品编码')->required());
    $form->add(Select::make('category')->label('分类')->options([
        'a' => 'A类', 'b' => 'B类',
    ]));
    $form->add(Number::make('price')->label('价格')->min(0)->step(0.01));
});

// 使用自定义预设（和内置预设完全一样）
$form->usePreset('productInfo');
$form->usePreset('productInfo', ['product_code', 'price']); // 只选部分字段
```

### 分组（Group）

将字段按逻辑区域分组展示。**回调内新增的字段会自动标记 group 归属**，字段本身仍在顶层 `fields` 数组中，通过 `group` 属性标明所属分组：

```php
$form->group('basic', '基本信息', 'icon-user', false, function (Form $form) {
    $form->add(Text::make('name')->label('姓名'));
    $form->add(Text::make('email')->label('邮箱'));
    // 以上两个字段自动标记 group => 'basic'
});
```

`toArray()` 输出结构：
```json
{
    "fields": {
        "name": { "name": "name", "group": "basic", "..." },
        "email": { "name": "email", "group": "basic", "..." }
    },
    "groups": {
        "basic": { "name": "basic", "title": "基本信息", "icon": "icon-user", "collapsed": false }
    }
}
```

> **字段定位规则**：字段在 `fields` 数组（顶层），`group` 属性指向 `groups` 中的同名分组。前端渲染器据此将字段分配到对应视觉区域。

参数：`group(string $name, string $title, ?string $icon = null, bool $collapsed = false, ?Closure $callback = null)`

### 联动（Dependencies）

定义字段之间的显隐/禁用依赖关系：

```php
use wise\form\schema\Dependency;

// 方式一：通过 Form::when()
$form->add(Select::make('type')->label('类型')->options(['personal'=>'个人', 'company'=>'企业']));

$form->when('type', '=', 'company', function (Form $form, Dependency $dep) {
    $form->add(Text::make('company_name')->label('企业名称')->dependency($dep));
    $form->add(Text::make('tax_id')->label('税号')->dependency($dep));
});
```

支持的操作符：`=`、`!=`、`>`、`<`、`>=`、`<=`、`in`、`not_in`

操作标识：`show`（显示）、`hide`（隐藏）、`enable`（启用）、`disable`（禁用）

> **注意**：联动仅记录元数据，由前端渲染器根据 `dependencies` 数组自行实现交互。

### 嵌套可重复组（Repeater）

用于动态添加多行子字段（如表格行）：

```php
$form->repeater('items', '产品列表', function (Form $form) {
    $form->add(Text::make('product_name')->label('产品名')->required());
    $form->add(Number::make('quantity')->label('数量')->min(1)->default(1));
    $form->add(Number::make('price')->label('单价')->min(0)->step(0.01));
});
```

验证规则输出样式：`items.*.product_name`、`items.*.quantity`、`items.*.price`

---

## 验证规则生成

`ValidationGenerator` 从 Form/Schema 自动生成验证规则数组，直接用于 ThinkPHP 控制器验证。

```php
$rules = ValidationGenerator::generate($form);

// 返回
// [
//     'title'    => 'require|string|max:100',
//     'category' => 'string',
//     'price'    => 'number',
//     'items.*.product_name' => 'require|string',
//     'items.*.quantity'     => 'number',
// ]
```

### 规则生成逻辑

1. **require**：字段标记了 `->required()` 时自动添加（ThinkPHP 规则名；Laravel 为 `required`）
2. **类型映射**：根据字段类型自动添加基础规则
3. **自定义**：合并用户通过 `->rules()` 添加的规则；其中的 **Laravel 风格规则名会自动归一化**
   （如 `->rules('required')` → `require`，避免被 think-validate 当作正则导致校验失效）
4. **Repeater**：嵌套字段使用 `repeater.*.field` 点语法
5. **去重与规范顺序**：重复规则只保留一条，且 `require` 始终排在最前（保证输出稳定可比对）

### 条件必填（requiredIf / requiredUnless）

`generate()` 产出的是**静态**规则；条件必填需依据提交数据求值，因此用 `generateFor()`：

```php
// 声明：企业名称在企业类型下必填
Text::make('company_name')->label('企业名称')->requiredIf('type', '=', 'company');
// requiredUnless('type', '=', 'personal')  ⇒ 当 type != personal 时必填

// 生成「针对本次提交数据」的有效规则
$rules = ValidationGenerator::generateFor($form, $requestData);
// ['type' => 'string', 'company_name' => 'require|string']
```

求值由 `ConditionEvaluator` 完成，支持操作符：
`=`、`!=`、`>`、`<`、`>=`、`<=`、`in`、`not_in`
（容忍表单数据的类型差异：`'1'` / `1` / `true` 视为相等；未知操作符一律视为条件不成立）。

配套的 CRUD 写法（`CrudAction::validateBySchema()` 钩子）：

```php
protected function validateBySchema(array $data, bool $isNew): array
{
    $form = new Form('article');
    $this->defineForm($form);
    $schema = $form->toArray();

    $validate = new \think\Validate(ValidationGenerator::generateFor($schema, $data));
    if (!$validate->check($data)) {
        throw new \think\exception\ValidateException($validate->getError());
    }

    return (new DataFilter())->filter($schema, $data);
}
```

> ⚠️ **前端目前不支持条件必填**。`ws-form-validator` 只能静态读取 `data-validation`，没有动态规则能力，
> 因此 `conditional_rules` **只在服务端生效**（服务端是权威，且它同样持有数据）。
> 若要在浏览器里也做即时提示，需要给前端校验器增加动态规则能力——属独立任务。
>
> ⚠️ **Repeater 子字段的条件必填暂不支持**：ThinkPHP 的规则串（`items.*.field`）
> 无法表达「逐行按数据求值」的语义。

### 类型 → 验证规则映射

| 字段类型 | 默认验证规则 | 说明 |
|----------|-------------|------|
| `text`, `textarea`, `radio`, `file`, `hidden`, `color`, `password` | `string` | 字符串 |
| `email` | `email` | 邮箱格式 |
| `select`, `image` | `string`（单）/ `array`（多，即 `multiple()` 后） | 根据 multiple 自动切换 |
| `file` | `string`（单）/ `array`（多） | 根据 multiple 自动切换 |
| `number` | `number` | 数字 |
| `date`, `datetime`, `time` | `date` | 日期/时间 |
| `checkbox` | `array` | 始终为数组 |
| `switch` | `boolean` | 布尔值 |

---

## 数据过滤

`DataFilter` 提供**白名单过滤**和**自动类型转换**：

```php
$schema = $form->toArray();
$input = $_POST; // 用户提交的原始数据

$filter = new DataFilter();
$clean = $filter->filter($schema, $input);
```

### 过滤规则

1. **白名单**：仅保留 Schema 中定义的字段，未定义字段静默丢弃
2. **缺省填充**：提交数据中缺少的字段，使用 `default` 值填充
3. **类型转换**：

| 字段类型 | 转换规则 |
|----------|---------|
| `text`, `textarea`, `radio`, `date`, `datetime`, `time`, `hidden`, `editor`, `color`, `password`, `email` | → `string` (null → null) |
| `number` | → `int`（整数）/ `float`（小数）/ `0`（非数字） |
| `switch` | → `bool` |
| `checkbox` | → `array`（若非数组则包装为数组） |
| `image`, `file`, `select` | 单模式（`multiple: false`）→ `string`；多模式（`multiple: true`）→ `array` |

### 嵌套 Repeater 处理

自动识别索引数组（多行数据）与关联数组（单行数据），递归过滤每一行：

```php
// 输入：['items' => [['product_name'=>'A','quantity'=>2], ['product_name'=>'B','quantity'=>3]]]
// 输入：['items' => ['product_name'=>'A','quantity'=>2]]  ← 单行也支持
// 输入：['items' => []]  ← 空 Repeater，保持不变
```

---

## 渲染适配器

包定义了 `RendererInterface` 接口，并提供三个实现：`ArrayRenderer`（透传）、
`JsonSchemaRenderer`（Draft-07）与 `HtmlRenderer`（HTML 表单，配合可替换的主题）。

### 接口定义

```php
namespace wise\form\schema\renderer;

interface RendererInterface
{
    /**
     * @param array $schema  Form::toArray() 输出的 Schema
     * @param array $context 可选上下文（语言、主题等）
     * @return mixed
     */
    public function render(array $schema, array $context = []): mixed;
}
```

### 默认渲染器

```php
use wise\form\schema\renderer\ArrayRenderer;

$renderer = new ArrayRenderer();
$output = $renderer->render($schema); // 透传 Schema 数组
```

### 自定义渲染器

```php
class VueRenderer implements RendererInterface
{
    public function render(array $schema, array $context = []): mixed
    {
        // 将 Schema 转换为 Vue 组件配置
        return $this->toVueConfig($schema);
    }
}
```

### HTML 渲染器与主题

`HtmlRenderer` 把 Schema 渲染为可直接使用的表单 HTML。它刻意分成两层，
以解决"一套 Schema 要适配 Bootstrap / Layui / Element 等多种前端框架"的问题：

| 层 | 类 | 职责 |
|----|----|------|
| 编排（框架无关） | `HtmlRenderer` | 字段分组、生成前端校验规则、`Dependency` → 联动条件 |
| 主题（框架相关） | `FormThemeInterface` | 决定标记结构与 CSS class |
| 参考实现 | `Bootstrap5Theme` | Bootstrap 5 + `data-validation` / `data-conditions` 契约 |

新增一个前端框架 = 新增一个 `FormThemeInterface` 实现，**无需改动 HtmlRenderer 与 schema**。

```php
use wise\form\schema\renderer\html\HtmlRenderer;
use wise\form\schema\renderer\html\theme\Bootstrap5Theme;

$renderer = new HtmlRenderer(new Bootstrap5Theme());

// $row 为当前记录（用于回显），可省略
$html = $renderer->render($form->toArray(), ['values' => $row ?? []]);
// 输出表单内部 HTML（不含 <form> 标签），由调用方包裹：
// <form id="wsForm" class="ws-form needs-validation" novalidate>...$html...</form>
```

**输出契约**（`Bootstrap5Theme`）：

- 校验：控件上 `data-validation="required|maxLength:100"` + `data-validation-message-{rule}`
- 联动：字段行容器上 `data-conditions='[{"field":..,"operator":"equals","value":..,"relation":"AND"}]'`
- 反馈：字段行的 `.invalid-feedback` 供校验器展示错误

**后端规则 → 前端规则映射**：后端（ThinkPHP：`require`/`string`）与前端
（ws-form-validator：`required`/`minLength`）的规则词汇表不同，`HtmlRenderer` 做**保守映射**，
只转换语义明确的规则：

| 后端规则 | 前端规则 | 说明 |
|----------|----------|------|
| `require` | `required` | **规则名不同，务必区分** |
| `email` / `number` / `date` | 同名 | |
| `max:n` / `min:n` | 字符串 → `maxLength:n` / `minLength:n`；数值 → `max:n` / `min:n` | 按字段类型分流 |
| `between:a,b` / `length:a,b` | `minLength:a` + `maxLength:b` | 仅字符串类型 |
| 其他（`alpha_dash` / `unique` / `in` / `fileSize` …） | — | 前端无法表达，**跳过**，仅由后端强制 |

> ⚠️ 前端校验只是 UX 便利，**后端校验才是权威**。

**`Dependency` → `data-conditions` 的能力边界**（超出即丢弃，不产出"看起来生效其实不生效"的假联动）：

| `Dependency` | 能否表达 | 说明 |
|--------------|----------|------|
| `=` / `!=` / `>` / `<` | ✅ | 映射为 `equals` / `not_equals` / `greater_than` / `less_than` |
| `>=` / `<=` / `in` / `not_in` | ❌ | 前端无对应操作符 |
| `action = show` | ✅ | |
| `action = hide` | ⚠️ 部分 | 仅 `=` / `!=` 可取反；比较类取反会改变语义故丢弃 |
| `action = enable` / `disable` | ❌ | 前端把"禁用"与"隐藏"绑定，无法单独表达 |

**自定义主题**：继承 `AbstractFormTheme`（可直接复用转义、属性拼装、JSON 序列化等工具方法），
只需实现 `control()` / `field()` / `group()`：

```php
class LayuiTheme extends AbstractFormTheme
{
    public function control(array $field, array $context): string { /* layui 标记 */ }
    public function field(array $field, string $control, array $context): string { /* layui 行结构 */ }
}
```

**当前骨架版本的已知取舍**：

1. 下拉/日期使用**原生**控件，未接入 `data-role="wsselect"` / `ws-date-picker`。
   这两个组件均为"JS 自渲染并自管理提交值"，其提交与校验契约尚未确认，
   贸然接入会产出"校验挂不上、值提交不上"的假集成。
   宿主可继承 `Bootstrap5Theme`，确认契约后覆写 `select()` 接入。
2. **未实现 repeater（可重复组）渲染**，需要与宿主的重复项 JS 约定配合，另行设计。

---

## Schema 输出规范

`Form::toArray()` 输出的标准 Schema 结构：

```json
{
    "form": "article",
    "fields": {
        "title": {
            "name": "title",
            "type": "text",
            "label": "标题",
            "placeholder": "请输入标题",
            "default": "",
            "required": true,
            "help": "",
            "rules": ["max:100"],
            "renderable": true,
            "props": {
                "multiple": false,
                "min": null,
                "max": null,
                "step": null,
                "format": null,
                "options": []
            },
            "dependencies": [],
            "conditional_rules": [],
            "group": "basic"
        }
    },
    "groups": {
        "basic": {
            "name": "basic",
            "title": "基本信息",
            "icon": "icon-user",
            "collapsed": false
        }
    },
    "repeaters": {
        "items": {
            "name": "items",
            "label": "产品列表",
            "fields": {
                "product_name": { "...": "..." },
                "quantity": { "...": "..." }
            }
        }
    }
}
```

**字段与分组的关系**：所有字段存储在顶层 `fields` 中，`group` 属性值为分组名（或 `null`）。`groups` 为分组元数据（标题、图标等），前端通过字段的 `group` 值匹配对应分组，将字段渲染到正确的视觉区域。这与某些框架将字段嵌套在分组内的设计不同，好处是字段查找始终是 O(1)。

> **重要**：Schema 结构是核心契约，键名一经发布不可变更。

---

## 异常体系

所有异常继承 `wise\form\schema\exception\SchemaException`（`\RuntimeException`）：

| 错误码 | 常量 | 说明 |
|--------|------|------|
| `1001` | `CODE_DUPLICATE_FIELD` | 重复字段名 |
| `1002` | `CODE_INVALID_FIELD` | 无效字段 |
| `1003` | `CODE_FILTER_ERROR` | 数据过滤错误 |

### 使用示例

```php
use wise\form\schema\exception\SchemaException;

try {
    $form->add(Text::make('name'))->add(Text::make('name')); // 重复
} catch (SchemaException $e) {
    echo $e->getCode();         // 1001
    echo $e->getFieldName();    // "name"
    echo $e->getContext();      // ['field' => 'name']
}
```

---

## 扩展自定义字段

继承 `AbstractField` 并实现 `getType()`：

```php
use wise\form\schema\field\AbstractField;
use wise\form\schema\enums\FieldType;

class ColorPicker extends AbstractField
{
    public function getType(): FieldType
    {
        return FieldType::TEXT; // 复用现有类型，或扩展枚举
    }

    protected function buildProps(): array
    {
        return array_merge(parent::buildProps(), [
            'picker' => 'chrome',
            'presets' => ['#ff0000', '#00ff00', '#0000ff'],
        ]);
    }
}

// 使用
$form->add(ColorPicker::make('theme_color')->label('主题色')->default('#1890ff'));
```

---

## API 参考

### Form

| 方法 | 说明 |
|------|------|
| `__construct(string $name)` | 构造器，指定表单名 |
| `add(AbstractField $field): self` | 添加字段（重复抛异常） |
| `remove(string $name): self` | 移除指定字段 |
| `usePreset(string $name, array $onlyFields = []): self` | 使用预设批量添加字段（可选仅添加部分字段） |
| `static registerPreset(string $name, Closure $callback): void` | 注册自定义预设 |
| `group(name, title, ?icon, ?collapsed, ?callback): self` | 注册分组（回调内新增字段自动标记 group） |
| `when(field, operator, value, callback): self` | 添加联动条件 |
| `repeater(name, label, callback): self` | 添加可重复字段组 |
| `getName(): string` | 获取表单名 |
| `getFields(): array` | 获取所有字段 |
| `getGroups(): array` | 获取所有分组 |
| `getRepeaters(): array` | 获取所有 Repeater |
| `toArray(): array` | 输出标准化 Schema |

### Group

| 方法 | 说明 |
|------|------|
| `__construct(name, title, ?icon, ?collapsed)` | 构造器 |
| `getName(): string` | 获取分组名 |
| `getTitle(): string` | 获取标题 |
| `toArray(): array` | 输出数组 |

### Dependency

| 属性/方法 | 说明 |
|-----------|------|
| `$field` (readonly) | 依赖的字段名 |
| `$operator` (readonly) | 操作符，默认 `=` |
| `$value` (readonly) | 期望值 |
| `$action` (readonly) | 动作，默认 `show` |
| `toArray(): array` | 输出数组 |

### Repeater

| 方法 | 说明 |
|------|------|
| `__construct(name, label, callback)` | 构造器 |
| `getName(): string` | 获取名称 |
| `getLabel(): string` | 获取标签 |
| `getForm(): Form` | 获取内部子 Form |
| `toArray(): array` | 输出数组（含 fields） |

### ValidationGenerator

| 方法 | 说明 |
|------|------|
| `static generate(array\|Form $source): array` | 生成静态验证规则数组 |
| `static generateFor(array\|Form $source, array $data): array` | 生成针对某份数据的有效规则（消费条件必填） |

### ConditionEvaluator

| 方法 | 说明 |
|------|------|
| `evaluate(array $condition, array $data): bool` | 求值单条条件 `{field, operator, value}` |
| `evaluateAll(array $conditions, array $data): bool` | 求值一组条件（隐式 AND） |
| `const OPERATORS` | 支持的操作符：`=` `!=` `>` `<` `>=` `<=` `in` `not_in` |

### DataFilter

| 方法 | 说明 |
|------|------|
| `filter(array $schema, array $input): array` | 白名单过滤 + 类型转换 |

### AbstractField

| 方法 | 说明 |
|------|------|
| `static make(name, ?label): static` | 静态工厂 |
| `label(string): static` | 显示标签 |
| `placeholder(string): static` | 占位提示 |
| `default(mixed): static` | 默认值 |
| `required(bool): static` | 必填标记 |
| `help(string): static` | 帮助文本 |
| `rules(string\|array): static` | 附加验证规则 |
| `options(array): static` | 选项列表 |
| `multiple(bool): static` | 多选开关 |
| `range(array $default = []): static` | 区间模式（date/datetime/time） |
| `step(float): static` | 步进值 |
| `min(mixed): static` | 最小值 |
| `max(mixed): static` | 最大值 |
| `format(string): static` | 格式 |
| `renderable(bool\|array): static` | 渲染配置 |
| `dependency(Dependency): static` | 添加联动依赖 |
| `group(string): static` | 设置分组 |
| `getName(): string` | 获取字段名 |
| `getType(): FieldType` | 获取字段类型 |
| `getRules(): array` | 获取验证规则 |
| `getGroup(): ?string` | 获取分组名 |
| `isRequired(): bool` | 是否必填 |
| `toArray(): array` | 输出 Schema 数组 |

---

## 测试

```bash
# 运行全部测试
composer test

# 仅单元测试
composer test-unit

# 仅功能测试
composer test-functional
```

测试覆盖：

| 套件 | 测试数 | 覆盖范围 |
|------|--------|---------|
| `FieldTest` | 45 | 全部字段类型 + Fluent API + 依赖去重 |
| `FormTest` | 12 | 添加/去重/分组/联动/嵌套 |
| `ValidationGeneratorTest` | 24 | 全类型映射 + require 必填/归一化 + 条件必填（generateFor） |
| `DataFilterTest` | 16 | 白名单 + 类型转换 + 空 Repeater |
| `ConditionEvaluatorTest` | 13 | 条件求值（8 种操作符 + 类型容错 + 数组实际值） |
| `DependencyTest` | 7 | 值对象构造 + toArray |
| `GroupTest` | 6 | 分组模型 |
| `RepeaterTest` | 5 | 可重复组 |
| `ArrayRendererTest` | 4 | 渲染器接口 |
| `SchemaExceptionTest` | 6 | 异常工厂方法 + ErrorCode 码值 |
| `HtmlRendererTest` | 25 | HTML 编排 + 前后端规则映射 + 联动条件转换 |
| `Bootstrap5ThemeTest` | 13 | Bootstrap 5 控件标记 + 转义 |
| `EndToEndTest` | 2 | 完整生命周期 |
| **总计** | **178** | |

---

## 依赖关系

- **运行时依赖**：仅 `php >= 8.1` + `ext-json`（**零框架依赖**，核心不依赖 ThinkPHP，可独立使用）
- **测试依赖**：`phpunit/phpunit`、`mockery/mockery`

## 许可证

Apache-2.0 License. 详见 [LICENSE](LICENSE) 文件。
