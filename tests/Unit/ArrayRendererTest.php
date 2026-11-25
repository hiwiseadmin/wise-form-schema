<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\field\Text;
use wise\form\schema\Form;
use wise\form\schema\renderer\ArrayRenderer;
use wise\form\schema\renderer\RendererInterface;
use wise\form\schema\tests\TestCase;

class ArrayRendererTest extends TestCase
{
    public function testImplementsRendererInterface(): void
    {
        $renderer = new ArrayRenderer();
        $this->assertInstanceOf(RendererInterface::class, $renderer);
    }

    public function testRenderReturnsSameSchema(): void
    {
        $form = new Form('test');
        $form->add(Text::make('name', '姓名'));

        $schema = $form->toArray();
        $renderer = new ArrayRenderer();

        $result = $renderer->render($schema);
        $this->assertSame($schema, $result);
    }

    public function testRenderWithContext(): void
    {
        $form = new Form('test');
        $form->add(Text::make('name', '姓名'));

        $schema = $form->toArray();
        $renderer = new ArrayRenderer();

        $result = $renderer->render($schema, ['lang' => 'zh-CN']);
        $this->assertSame($schema, $result);
    }

    public function testRenderEmptySchema(): void
    {
        $schema = [
            'form' => 'empty',
            'fields' => [],
            'groups' => [],
            'repeaters' => [],
        ];

        $renderer = new ArrayRenderer();
        $result = $renderer->render($schema);
        $this->assertSame($schema, $result);
    }
}
