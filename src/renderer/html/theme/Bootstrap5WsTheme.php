<?php

declare(strict_types=1);

namespace wise\form\schema\renderer\html\theme;

/**
 * Bootstrap5WsTheme — 把「有对应组件、且契约已确认」的字段换成 ws-* 组件。
 *
 * 继承 {@see Bootstrap5Theme}，只覆写需要替换的控件方法，其余（radio / checkbox /
 * switch / number / email / password / textarea / file）沿用父类的原生控件 ——
 * 这些类型没有等价组件，换成自绘反而降低可用性与可访问性。
 *
 * 接入对照：
 *
 *   date      → ws-date-picker（`data-role="wsdatepicker"`）
 *   time      → ws-date-picker `data-type="time"`
 *   datetime  → ws-date-picker `data-type="datetime"`
 *   select    → ws-select（原生 `<select data-role="wsselect">` 就地提升）
 *   color     → ws-color-picker（`data-role="wscolor"`）
 *   image / file → ws-upload（`data-role="wsupload"`，多值走原生 `name[]`）
 *
 * ## 为什么现在可以接（原 Bootstrap5Theme 注释里"契约未确认"的前提已消除）
 *
 * 1. **值提交**：div 宿主给 `data-name`，组件生成 `input[type=hidden][data-ws-carrier]`，
 *    `serialize()` 能收到；原生 `<select>` 由 `convertNativeSelect()` 原地提升，
 *    服务端 `<option>` 渲染代码一行都不用改。
 * 2. **校验**：宿主上的 `data-validation` / `data-validation-message-*` 由
 *    `copyValidation(host, carrier, true)` **迁移**到载体，不留两份规则。
 * 3. **实时校验**：组件值变更走**原生** `dispatchEvent`，`WsFormValidator` 的
 *    `addEventListener` 才收得到（jQuery `.trigger()` 不产生原生事件）。
 *
 * ## 初始化顺序（调用方必须保证）
 *
 * 先 `WsDatePicker/WsSelect/WsColorPicker.autoInitialize()`（或 `Wise.ws.autoInitializeAll()`），
 * **再** `new WsFormValidator('wsForm')`。反了的话规则还挂在宿主 div 上，
 * 而 div 没有 name，校验器扫描时会直接跳过，表现成"校验完全没反应"。
 *
 * ## 上传接口
 *
 * 不写 `props.uploadUrl` 时前端 ws-upload 使用框架默认
 * `/api/attachment/upload`（后端可在 `config/wise-attachment.php` 的
 * `upload.url` 改，经 `window.__WS_CONFIG__` 注入）；字段级需要换接口时写
 * `props.uploadUrl`，会渲染成 `data-url`。
 *
 * ## 尚未接入的类型
 *
 * 无。剩余类型（radio / checkbox / switch / number / email / password /
 * textarea / editor / hidden）没有等价组件，意图就是保留原生控件。
 */
class Bootstrap5WsTheme extends Bootstrap5Theme
{
    /**
     * 日期：ws-date-picker。
     *
     * 宿主仍是原生 `input`（`id` / `name` / `data-validation` 原样复用），
     * 只把 `type` 从 `date` 改成 `text` —— 否则浏览器原生日期控件会和面板叠加。
     */
    protected function date(array $context): string
    {
        return $this->datePicker($context, ['data-format' => 'yyyy-MM-dd']);
    }

    /**
     * 时间：ws-date-picker（时间视图）。
     *
     * 格式必须是 `HH:mm`：校验规则的 `time` 正则是 `^\d{2}:\d{2}$`，
     * 带秒会被判为非法，且原生 `<input type="time">` 提交的也是 `HH:mm`。
     */
    protected function time(array $context): string
    {
        return $this->datePicker($context, [
            'data-type'   => 'time',
            'data-format' => 'HH:mm',
        ]);
    }

    /**
     * 日期时间：ws-date-picker（两段式：先选日期再选时间）。
     *
     * 输出格式与 schema 的 `Y-m-d H:i:s` 一致，因此不再需要父类那套
     * "空格转 `T` 喂给 datetime-local"的转换 —— 值可以原样回填、原样提交。
     */
    protected function datetime(array $context): string
    {
        return $this->datePicker($context, [
            'data-type'   => 'datetime',
            'data-format' => 'yyyy-MM-dd HH:mm:ss',
        ]);
    }

    /**
     * 下拉：ws-select。
     *
     * 单选与多选都走"原生 select 就地提升"——只加一个 `data-role`，
     * `<option>` 由服务端照常渲染。提升后 name 平移到隐藏载体、原 select 移除 name，
     * 避免同名字段重复提交。
     *
     * 多选的提交形态与原生完全一致：`name[]` 一个值一个隐藏域，
     * 后端 `$_POST['tags']` 直接是数组；未选中任何项时不提交该字段。
     */
    protected function select(array $context): string
    {
        $html = parent::select($context);

        $replaced = preg_replace('/^<select\b/', '<select data-role="wsselect"', $html, 1);

        return $replaced === null ? $html : $replaced;
    }

    /**
     * 颜色：ws-color-picker。
     *
     * 组件本体是纯模态框，表单能力靠宿主 div 提供：
     * `data-name` 生成载体，`data-value` 回填，`data-validation` 迁移到载体。
     */
    protected function color(array $context): string
    {
        $attrs = $context['attrs'];

        $name = (string) ($attrs['name'] ?? '');
        unset($attrs['name']);

        $attrs['data-role']  = 'wscolor';
        $attrs['data-name']  = $name;
        $attrs['data-value'] = $this->stringify($context['value']);

        if (isset($attrs['class'])) {
            unset($attrs['class']);
        }

        return '<div' . $this->attrString($attrs) . '></div>';
    }

    /**
     * 图片 / 文件：ws-upload。
     *
     * 宿主是 div，表单能力由组件提供：
     * - `data-name` 基名 → 组件按 `multiple` 自动决定是否补 `[]`
     *   （多值提交 `images[]=a&images[]=b`，与原生 `<input type="file" multiple name="images[]">` 一致）
     * - `data-value` 回填历史路径：单值是路径串，多值是 JSON 数组
     * - 宿主的 `data-validation` 由组件迁移到隐藏载体
     *
     * 注意：`label for` 指向宿主 div 不再能把焦点带到文件选择框（div 不可聚焦），
     * 点击文字标签不再触发选择——这是组件层面的可访问性缺口，后续由组件内部
     * file input 的 id 透传解决。
     */
    protected function upload(array $context): string
    {
        $type     = (string) $context['type'];
        $multiple = !empty($context['multiple']);

        $attrs = [
            'data-role'   => 'wsupload',
            'data-name'   => (string) $context['name'],
            'data-accept' => $type === 'image' ? 'images' : 'file',
        ];

        if ($multiple) {
            $attrs['data-multiple'] = 'true';
        }

        $current = $context['value'];

        if (is_array($current)) {
            $current = array_values(array_filter(
                array_map(static fn ($v): string => (string) $v, $current),
                static fn (string $v): bool => $v !== ''
            ));
            if ($current !== []) {
                $attrs['data-value'] = (string) json_encode($current, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        } else {
            $current = trim((string) $current);
            if ($current !== '') {
                $attrs['data-value'] = $current;
            }
        }

        if (!empty($context['uploadUrl'])) {
            $attrs['data-url'] = (string) $context['uploadUrl'];
        }

        foreach ($context['attrs'] as $key => $value) {
            if ($key === 'name') {
                continue;
            }
            $attrs[$key] = $value;
        }

        return '<div' . $this->attrString($attrs) . '></div>';
    }

    /**
     * 生成 ws-date-picker 的宿主 input。
     *
     * @param array<string, mixed> $context 渲染上下文
     * @param array<string, string> $extra  picker 专属 data-*
     */
    protected function datePicker(array $context, array $extra): string
    {
        $attrs = array_merge(
            ['type' => 'text', 'class' => 'form-control'],
            $context['attrs'],
            $this->placeholderAttrs($context),
            $extra,
            [
                'data-role' => 'wsdatepicker',
                'value'     => $this->stringify($context['value']),
            ]
        );

        return '<input' . $this->attrString($attrs) . '>';
    }
}
