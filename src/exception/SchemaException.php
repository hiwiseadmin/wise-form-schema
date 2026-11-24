<?php
declare(strict_types=1);

namespace wise\form\schema\exception;

use wise\support\exception\WiseException;

/**
 * SchemaException — wise-form-schema 专用异常。
 *
 * 提供带错误码、字段名和上下文的异常，便于上层调用者定位问题。
 * 错误码统一定义在 {@see ErrorCode} 枚举中（号段 190000~199999），
 * 本类不再重复定义错误码常量。
 */
class SchemaException extends WiseException
{
    /**
     * 发生异常的字段名（可选）。
     */
    private ?string $fieldName;

    /**
     * @param string      $message   异常消息
     * @param int         $code      错误码
     * @param string|null $fieldName 相关字段名
     * @param array       $context   上下文数据
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?string $fieldName = null,
        array $context = []
    ) {
        $code = $code ?: ErrorCode::INVALID_FIELD->value;
        parent::__construct($code, $message, 400, null, $context);
        $this->fieldName = $fieldName;
    }

    /**
     * 创建"重复字段"异常。
     *
     * @param string $name 重复的字段名
     * @return self
     */
    public static function duplicateField(string $name): self
    {
        return new self(
            \sprintf('Duplicate field name "%s". Each field name must be unique within a form.', $name),
            ErrorCode::DUPLICATE_FIELD->value,
            $name,
            ['field' => $name]
        );
    }

    /**
     * 创建"无效字段"异常。
     *
     * @param string $name   字段名
     * @param string $reason 可选原因
     * @return self
     */
    public static function invalidField(string $name, string $reason = ''): self
    {
        $message = \sprintf('Invalid field "%s".', $name);
        if ($reason !== '') {
            $message .= ' ' . $reason;
        }
        return new self(
            $message,
            ErrorCode::INVALID_FIELD->value,
            $name,
            ['field' => $name, 'reason' => $reason]
        );
    }

    /**
     * 创建"过滤错误"异常。
     *
     * @param string $message 错误描述
     * @param array  $context 上下文数据
     * @return self
     */
    public static function filterError(string $message, array $context = []): self
    {
        return new self(
            $message,
            ErrorCode::FILTER_ERROR->value,
            null,
            $context
        );
    }

    /**
     * 创建"未知类型"异常。
     *
     * @param string $type      未知的字段类型
     * @param string $fieldName 相关字段名（可选）
     * @return self
     */
    public static function unknownType(string $type, string $fieldName = ''): self
    {
        $message = \sprintf('Unknown field type "%s"', $type);
        if ($fieldName !== '') {
            $message .= \sprintf(' for field "%s"', $fieldName);
        }
        return new self($message, ErrorCode::UNKNOWN_TYPE->value, $fieldName, ['type' => $type]);
    }

    /**
     * 获取关联的字段名。
     *
     * @return string|null
     */
    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }
}
