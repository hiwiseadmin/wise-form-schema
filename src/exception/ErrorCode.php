<?php
declare(strict_types=1);

namespace wise\form\schema\exception;

use wise\support\exception\ErrorCodeInterface;

/**
 * wise-form-schema 业务错误码（号段 190000~199999）
 *
 *  190000~190099  表单 Schema
 *
 * @package wise\form\schema\exception
 */
enum ErrorCode: int implements ErrorCodeInterface
{
    /** 重复字段名 */
    case DUPLICATE_FIELD = 190001;

    /** 无效字段 */
    case INVALID_FIELD   = 190002;

    /** 数据过滤错误 */
    case FILTER_ERROR    = 190003;

    /** 未知字段类型 */
    case UNKNOWN_TYPE    = 190004;

    public function defaultMessage(): string
    {
        return match ($this) {
            self::DUPLICATE_FIELD => '重复字段名',
            self::INVALID_FIELD   => '无效字段',
            self::FILTER_ERROR    => '数据过滤错误',
            self::UNKNOWN_TYPE    => '未知字段类型',
        };
    }
}
