<?php

declare(strict_types=1);

namespace wise\form\schema\traits;

use wise\form\schema\Form;

/**
 * HasFormSchema — 为模型或其他类提供便捷的表单 Schema 定义能力。
 *
 * 使用该 trait 的类只需实现 formSchema() 方法，
 * 即可通过 getFormSchema() 获取标准化 Schema 数组。
 *
 * 示例：
 * ```php
 * class UserModel
 * {
 *     use HasFormSchema;
 *
 *     public static function formSchema(Form $form): Form
 *     {
 *         return $form
 *             ->add(Text::make('username')->label('用户名')->required())
 *             ->add(Text::make('email')->label('邮箱')->rules('email'));
 *     }
 * }
 * ```
 */
trait HasFormSchema
{
    /**
     * 定义表单 Schema（由使用方实现）。
     *
     * @param Form $form 空白 Form 实例，通过 fluent API 构建字段
     * @return Form
     */
    abstract public static function formSchema(Form $form): Form;

    /**
     * 获取表单 Schema 数组。
     *
     * @param array|null $data 可选的行数据，用于填充字段默认值
     * @return array Form::toArray() 输出的标准化 Schema 数组
     */
    public static function getFormSchema(?array $data = null): array
    {
        $form = new Form(basename(str_replace('\\', '/', static::class)));
        static::formSchema($form);

        // 有数据时注入为字段默认值
        if ($data !== null) {
            foreach ($form->getFields() as $fieldName => $field) {
                if (\array_key_exists($fieldName, $data)) {
                    $field->default($data[$fieldName]);
                }
            }
        }

        return $form->toArray();
    }
}
