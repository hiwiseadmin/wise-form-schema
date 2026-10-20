<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\exception\ErrorCode;
use wise\form\schema\exception\SchemaException;
use wise\form\schema\tests\TestCase;

class SchemaExceptionTest extends TestCase
{
    public function testDuplicateFieldException(): void
    {
        $ex = SchemaException::duplicateField('username');

        $this->assertSame(ErrorCode::DUPLICATE_FIELD->value, $ex->getCode());
        $this->assertSame('username', $ex->getFieldName());
        $this->assertStringContainsString('username', $ex->getMessage());
        $this->assertArrayHasKey('field', $ex->getContext());
    }

    public function testInvalidFieldException(): void
    {
        $ex = SchemaException::invalidField('email', 'Field type not supported');

        $this->assertSame(ErrorCode::INVALID_FIELD->value, $ex->getCode());
        $this->assertSame('email', $ex->getFieldName());
        $this->assertStringContainsString('email', $ex->getMessage());
        $this->assertStringContainsString('Field type not supported', $ex->getMessage());
    }

    public function testInvalidFieldExceptionWithoutReason(): void
    {
        $ex = SchemaException::invalidField('name');

        $this->assertSame('name', $ex->getFieldName());
        $this->assertStringContainsString('Invalid field "name"', $ex->getMessage());
    }

    public function testFilterErrorException(): void
    {
        $ex = SchemaException::filterError('Type conversion failed', ['value' => 'abc']);

        $this->assertSame(ErrorCode::FILTER_ERROR->value, $ex->getCode());
        $this->assertNull($ex->getFieldName());
        $this->assertSame('Type conversion failed', $ex->getMessage());
        $this->assertSame(['value' => 'abc'], $ex->getContext());
    }

    public function testUnknownTypeException(): void
    {
        $ex = SchemaException::unknownType('foobar', 'my_field');

        $this->assertSame(ErrorCode::UNKNOWN_TYPE->value, $ex->getCode());
        $this->assertSame('my_field', $ex->getFieldName());
        $this->assertStringContainsString('foobar', $ex->getMessage());
        $this->assertSame(['type' => 'foobar'], $ex->getContext());
    }

    public function testExceptionIsRuntimeException(): void
    {
        $ex = SchemaException::duplicateField('test');
        $this->assertInstanceOf(\RuntimeException::class, $ex);
    }
}
