<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\Dependency;
use wise\form\schema\tests\TestCase;

class DependencyTest extends TestCase
{
    public function testDependencyCreation(): void
    {
        $dep = new Dependency('status', '=', 'active');

        $this->assertSame('status', $dep->field);
        $this->assertSame('=', $dep->operator);
        $this->assertSame('active', $dep->value);
        $this->assertSame('show', $dep->action);
    }

    public function testDependencyWithCustomAction(): void
    {
        $dep = new Dependency('status', '!=', 'deleted', 'hide');

        $this->assertSame('hide', $dep->action);
    }

    public function testDependencyToArray(): void
    {
        $dep = new Dependency('role', 'in', ['admin', 'editor'], 'show');

        $arr = $dep->toArray();

        $this->assertSame('role', $arr['field']);
        $this->assertSame('in', $arr['operator']);
        $this->assertSame(['admin', 'editor'], $arr['value']);
        $this->assertSame('show', $arr['action']);
    }

    public function testDependencyDefaultOperatorIsEquality(): void
    {
        $dep = new Dependency('field_name', value: 'test');
        $this->assertSame('=', $dep->operator);
    }

    public function testDependencyDefaultActionIsShow(): void
    {
        $dep = new Dependency('field_name');
        $this->assertSame('show', $dep->action);
    }

    public function testDependencyDefaultValueIsEmptyString(): void
    {
        $dep = new Dependency('field_name');
        $this->assertSame('', $dep->value);
    }

    public function testDependencyPropertiesAreReadonly(): void
    {
        $dep = new Dependency('field', '=', 'value', 'show');
        $arr = $dep->toArray();

        // Verify the public readonly properties can be read
        $this->assertSame('field', $dep->field);
        $this->assertSame('=', $dep->operator);
        $this->assertSame('value', $dep->value);
        $this->assertSame('show', $dep->action);
        $this->assertSame($dep->field, $arr['field']);
    }
}
