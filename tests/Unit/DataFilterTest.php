<?php

declare(strict_types=1);

namespace wise\form\schema\tests\Unit;

use wise\form\schema\DataFilter;
use wise\form\schema\field\Text;
use wise\form\schema\field\Number;
use wise\form\schema\field\Checkbox;
use wise\form\schema\field\SwitchField;
use wise\form\schema\field\Hidden;
use wise\form\schema\Form;
use wise\form\schema\tests\TestCase;

class DataFilterTest extends TestCase
{
    private DataFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new DataFilter();
    }

    public function testFilterOnlyKeepsDefinedFields(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'name' => ['name' => 'name', 'type' => 'text', 'default' => '', 'required' => false, 'rules' => []],
                'email' => ['name' => 'email', 'type' => 'text', 'default' => '', 'required' => false, 'rules' => []],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $input = [
            'name' => 'John',
            'email' => 'john@example.com',
            'hack' => 'evil_data',
            'extra' => 'should_be_dropped',
        ];

        $result = $this->filter->filter($schema, $input);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayNotHasKey('hack', $result);
        $this->assertArrayNotHasKey('extra', $result);
    }

    public function testFilterAppliesDefaultForMissingKeys(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'name' => ['name' => 'name', 'type' => 'text', 'default' => 'Guest', 'required' => false, 'rules' => []],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, []);

        $this->assertSame('Guest', $result['name']);
    }

    public function testFilterNumberTypeCasting(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'age' => ['name' => 'age', 'type' => 'number', 'default' => 0, 'required' => false, 'rules' => []],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['age' => '25']);
        $this->assertSame(25, $result['age']);
        $this->assertIsInt($result['age']);

        $result2 = $this->filter->filter($schema, ['age' => '3.14']);
        $this->assertSame(3.14, $result2['age']);
        $this->assertIsFloat($result2['age']);
    }

    public function testFilterSwitchTypeCasting(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'active' => ['name' => 'active', 'type' => 'switch', 'default' => false, 'required' => false, 'rules' => []],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['active' => '1']);
        $this->assertTrue($result['active']);

        $result2 = $this->filter->filter($schema, ['active' => '0']);
        $this->assertFalse($result2['active']);
    }

    public function testFilterCheckboxTypeCasting(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'tags' => ['name' => 'tags', 'type' => 'checkbox', 'default' => [], 'required' => false, 'rules' => []],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['tags' => 'single_value']);
        $this->assertIsArray($result['tags']);
        $this->assertSame(['single_value'], $result['tags']);

        $result2 = $this->filter->filter($schema, ['tags' => ['a', 'b']]);
        $this->assertSame(['a', 'b'], $result2['tags']);
    }

    public function testFilterRepeaterData(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'title' => ['name' => 'title', 'type' => 'text', 'default' => '', 'required' => false, 'rules' => []],
            ],
            'groups' => [],
            'repeaters' => [
                'items' => [
                    'name' => 'items',
                    'label' => 'Items',
                    'fields' => [
                        'item_name' => ['name' => 'item_name', 'type' => 'text', 'default' => '', 'required' => false, 'rules' => []],
                        'qty' => ['name' => 'qty', 'type' => 'number', 'default' => 0, 'required' => false, 'rules' => []],
                    ],
                ],
            ],
        ];

        $input = [
            'title' => 'Order 1',
            'extra_hack' => 'bad',
            'items' => [
                ['item_name' => 'Apple', 'qty' => '3', 'extra' => 'drop'],
                ['item_name' => 'Banana', 'qty' => '5', 'extra' => 'drop'],
            ],
        ];

        $result = $this->filter->filter($schema, $input);

        $this->assertSame('Order 1', $result['title']);
        $this->assertArrayNotHasKey('extra_hack', $result);
        $this->assertCount(2, $result['items']);
        $this->assertSame('Apple', $result['items'][0]['item_name']);
        $this->assertSame(3, $result['items'][0]['qty']);
        $this->assertArrayNotHasKey('extra', $result['items'][0]);
    }

    public function testFilterRepeaterSingleRow(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [],
            'groups' => [],
            'repeaters' => [
                'item' => [
                    'name' => 'item',
                    'label' => 'Item',
                    'fields' => [
                        'name' => ['name' => 'name', 'type' => 'text', 'default' => '', 'required' => false, 'rules' => []],
                    ],
                ],
            ],
        ];

        $input = [
            'item' => ['name' => 'Single'],
        ];

        $result = $this->filter->filter($schema, $input);
        $this->assertSame('Single', $result['item']['name']);
    }

    public function testFilterTextTypeCasting(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'title' => ['name' => 'title', 'type' => 'text', 'default' => '', 'required' => false, 'rules' => []],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['title' => 123]);
        $this->assertSame('123', $result['title']);
        $this->assertIsString($result['title']);
    }

    public function testFilterNullValueForNullableFields(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'bio' => ['name' => 'bio', 'type' => 'text', 'default' => '', 'required' => false, 'rules' => []],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['bio' => null]);
        $this->assertNull($result['bio']);
    }

    public function testFilterHiddenTypeCasting(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'id' => ['name' => 'id', 'type' => 'hidden', 'default' => '', 'required' => false, 'rules' => []],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['id' => 42]);
        $this->assertSame('42', $result['id']);
    }

    public function testFilterImageMultipleCastsToArray(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'gallery' => [
                    'name' => 'gallery',
                    'type' => 'image',
                    'default' => [],
                    'required' => false,
                    'rules' => [],
                    'props' => ['multiple' => true],
                ],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['gallery' => 'single_url']);
        $this->assertIsArray($result['gallery']);
        $this->assertSame(['single_url'], $result['gallery']);

        $result2 = $this->filter->filter($schema, ['gallery' => ['url1', 'url2']]);
        $this->assertSame(['url1', 'url2'], $result2['gallery']);
    }

    public function testFilterImageSingleCastsToString(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'cover' => [
                    'name' => 'cover',
                    'type' => 'image',
                    'default' => '',
                    'required' => false,
                    'rules' => [],
                    'props' => ['multiple' => false],
                ],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['cover' => '/uploads/pic.jpg']);
        $this->assertIsString($result['cover']);
        $this->assertSame('/uploads/pic.jpg', $result['cover']);
    }

    public function testFilterFileMultipleCastsToArray(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'attachments' => [
                    'name' => 'attachments',
                    'type' => 'file',
                    'default' => [],
                    'required' => false,
                    'rules' => [],
                    'props' => ['multiple' => true],
                ],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['attachments' => '/files/doc.pdf']);
        $this->assertIsArray($result['attachments']);
        $this->assertSame(['/files/doc.pdf'], $result['attachments']);
    }

    public function testFilterSelectMultipleCastsToArray(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'tags' => [
                    'name' => 'tags',
                    'type' => 'select',
                    'default' => [],
                    'required' => false,
                    'rules' => [],
                    'props' => ['multiple' => true],
                ],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['tags' => 'php']);
        $this->assertIsArray($result['tags']);
        $this->assertSame(['php'], $result['tags']);
    }

    public function testFilterRangeCastsToArray(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'date_range' => [
                    'name' => 'date_range',
                    'type' => 'date',
                    'default' => [],
                    'required' => false,
                    'rules' => [],
                    'props' => ['range' => true, 'format' => 'Y-m-d'],
                ],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['date_range' => ['2025-01-01', '2025-12-31']]);
        $this->assertIsArray($result['date_range']);
        $this->assertSame(['2025-01-01', '2025-12-31'], $result['date_range']);
    }

    public function testFilterRangeWrapsScalarToArray(): void
    {
        $schema = [
            'form' => 'test',
            'fields' => [
                'date_range' => [
                    'name' => 'date_range',
                    'type' => 'date',
                    'default' => [],
                    'required' => false,
                    'rules' => [],
                    'props' => ['range' => true, 'format' => 'Y-m-d'],
                ],
            ],
            'groups' => [],
            'repeaters' => [],
        ];

        $result = $this->filter->filter($schema, ['date_range' => '2025-01-01']);
        $this->assertIsArray($result['date_range']);
        $this->assertSame(['2025-01-01'], $result['date_range']);
    }
}
