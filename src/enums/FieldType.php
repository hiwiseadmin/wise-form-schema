<?php

declare(strict_types=1);

namespace wise\form\schema\enums;

use wise\form\schema\field\AbstractField;
use wise\form\schema\field\Checkbox;
use wise\form\schema\field\Color;
use wise\form\schema\field\Date;
use wise\form\schema\field\Datetime;
use wise\form\schema\field\Editor;
use wise\form\schema\field\Email;
use wise\form\schema\field\File;
use wise\form\schema\field\Hidden;
use wise\form\schema\field\Image;
use wise\form\schema\field\Number;
use wise\form\schema\field\Password;
use wise\form\schema\field\Radio;
use wise\form\schema\field\Select;
use wise\form\schema\field\SwitchField;
use wise\form\schema\field\Text;
use wise\form\schema\field\Textarea;
use wise\form\schema\field\Time;

/**
 * 表单字段类型枚举。
 *
 * 每个 case 通过 getClass() 返回对应的字段类名，
 * 这是整个包中唯一的 FieldType → 类名映射点。
 * 新增字段类型只需：1) 添加 case  2) 新增 getClass() match 分支。
 */
enum FieldType: string
{
    case TEXT = 'text';
    case SELECT = 'select';
    case NUMBER = 'number';
    case TEXTAREA = 'textarea';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case CHECKBOX = 'checkbox';
    case RADIO = 'radio';
    case FILE = 'file';
    case SWITCH = 'switch';
    case HIDDEN = 'hidden';
    case IMAGE = 'image';
    case TIME = 'time';
    case EDITOR = 'editor';
    case COLOR = 'color';
    case PASSWORD = 'password';
    case EMAIL = 'email';

    /**
     * 获取该字段类型对应的字段类名。
     *
     * @return class-string<AbstractField>
     */
    public function getClass(): string
    {
        return match ($this) {
            self::TEXT     => Text::class,
            self::SELECT   => Select::class,
            self::NUMBER   => Number::class,
            self::TEXTAREA => Textarea::class,
            self::DATE     => Date::class,
            self::DATETIME => Datetime::class,
            self::CHECKBOX => Checkbox::class,
            self::RADIO    => Radio::class,
            self::FILE     => File::class,
            self::SWITCH   => SwitchField::class,
            self::HIDDEN   => Hidden::class,
            self::IMAGE    => Image::class,
            self::TIME     => Time::class,
            self::EDITOR   => Editor::class,
            self::COLOR    => Color::class,
            self::PASSWORD => Password::class,
            self::EMAIL    => Email::class,
        };
    }

    /**
     * 根据类型字符串解析为枚举实例。
     *
     * @param string $type 类型字符串（如 'text', 'select'）
     * @return self|null 匹配的枚举实例，未知类型返回 null
     */
    public static function tryFromString(string $type): ?self
    {
        return self::tryFrom($type);
    }
}
