<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ShopifyToFactFinderProductMapper;
use PHPUnit\Framework\TestCase;

class ShopifyToFactFinderProductMapperTest extends TestCase
{
    private ShopifyToFactFinderProductMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ShopifyToFactFinderProductMapper();
    }

    public function testMapSingleVariantProducesOnlyMasterRow(): void
    {
        $product = [
            'legacyResourceId' => '123',
            'title' => 'Test Product',
            'translations' => [],
            'descriptionHtml' => '<p>Short description</p>',
            'vendor' => 'BrandX',
            'handle' => 'test-product',
            'onlineStoreUrl' => null,
            'images' => [
                'edges' => [
                    ['node' => ['url' => 'https://cdn.example.com/img.jpg']]
                ]
            ],
            'category' => null,
            'variants' => [
                'edges' => [
                    ['node' => [
                        'legacyResourceId' => '456',
                        'title' => 'Default Title',
                        'price' => '10.00',
                        'selectedOptions' => [
                            ['name' => 'Title', 'value' => 'Default Title']
                        ],
                        'translations' => []
                    ]]
                ]
            ],
        ];

        $rows = iterator_to_array($this->mapper->map([$product], 'shop.example.com'), false);
        $this->assertCount(1, $rows);
        $master = $rows[0];
        $this->assertEquals('123', $master['ProductNumber']);
        $this->assertEquals('123', $master['Master']);
        $this->assertEquals('Test Product', $master['Name']);
        $this->assertEquals('BrandX', $master['Brand']);
        $this->assertStringContainsString('https://shop.example.com/products/test-product', $master['Deeplink']);
        $this->assertEquals('Short description', $master['Description']); // strip_tags applied
        $this->assertEquals('https://cdn.example.com/img.jpg', $master['ImageUrl']);
        $this->assertEquals('10.00', $master['Price']);
        $this->assertSame('', $master['FilterAttributes']);
    }

    public function testMapMultipleVariantsProducesMasterAndVariantRowsAndFilterAttributes(): void
    {
        $product = [
            'legacyResourceId' => '111',
            'title' => 'Shirt',
            'translations' => [
                ['key' => 'title', 'value' => 'Hemd'],
                ['key' => 'body_html', 'value' => 'Beschreibung']
            ],
            'descriptionHtml' => '<p>irrelevant</p>',
            'vendor' => 'BrandY',
            'handle' => 'shirt-product',
            'onlineStoreUrl' => null,
            'images' => [
                'edges' => [
                    ['node' => ['url' => 'https://cdn.example.com/shirt.jpg']]
                ]
            ],
            'category' => ['fullName' => 'Clothing > Tops'],
            'variants' => [
                'edges' => [
                    ['node' => [
                        'legacyResourceId' => '201',
                        'title' => 'Blue / M',
                        'price' => '20.00',
                        'selectedOptions' => [
                            ['name' => 'color', 'value' => 'Blue'],
                            ['name' => 'size', 'value' => 'M'],
                        ],
                        'translations' => [
                            ['key' => 'option1', 'value' => 'Blue'],
                        ],
                    ]],
                    ['node' => [
                        'legacyResourceId' => '202',
                        'title' => 'Blue / L',
                        'price' => '20.50',
                        'selectedOptions' => [
                            ['name' => 'color', 'value' => 'Blue'],
                            ['name' => 'size', 'value' => 'L'],
                        ],
                        'translations' => [
                            ['key' => 'option1', 'value' => 'Blue'],
                        ],
                    ]],
                    ['node' => [
                        'legacyResourceId' => '203',
                        'title' => 'Green / M',
                        'price' => '21.00',
                        'selectedOptions' => [
                            ['name' => 'color', 'value' => 'Green'],
                            ['name' => 'size', 'value' => 'M'],
                        ],
                        'translations' => [
                            ['key' => 'option1', 'value' => 'Green'],
                        ],
                    ]],
                    ['node' => [
                        'legacyResourceId' => '204',
                        'title' => 'Green / L',
                        'price' => '21.50',
                        'selectedOptions' => [
                            ['name' => 'color', 'value' => 'Green'],
                            ['name' => 'size', 'value' => 'L'],
                        ],
                        'translations' => [
                            ['key' => 'option1', 'value' => 'Green'],
                        ],
                    ]],
                ]
            ],
        ];

        $rows = iterator_to_array($this->mapper->map([$product], 'example-shop.com'), false);
        $this->assertCount(5, $rows);
        $master = $rows[0];
        $this->assertEquals('111', $master['ProductNumber']);
        $this->assertEquals('111', $master['Master']);
        $this->assertEquals('Hemd', $master['Name']); // translated product title
        $this->assertEquals('Beschreibung', $master['Description']); // translated body_html, strip_tags applied
        $this->assertEquals('BrandY', $master['Brand']);
        $this->assertEquals('https://cdn.example.com/shirt.jpg', $master['ImageUrl']);
        $this->assertEquals('Clothing/Tops', $master['CategoryPath']);
        $this->assertStringContainsString('|color=Blue|', $master['FilterAttributes']);
        $this->assertStringContainsString('|color=Green|', $master['FilterAttributes']);
        $this->assertStringContainsString('|size=M|', $master['FilterAttributes']);
        $this->assertStringContainsString('|size=L|', $master['FilterAttributes']);
        $this->assertStringNotContainsString('||', $master['FilterAttributes']);
        $variant1 = $rows[1];
        $this->assertEquals('201', $variant1['ProductNumber']);
        $this->assertEquals('111', $variant1['Master']);
        $this->assertEquals('Hemd Blue', $variant1['Name']);
        $this->assertEquals('20.00', $variant1['Price']);
        $this->assertStringContainsString('|color=Blue|', $variant1['FilterAttributes']);
        $this->assertStringContainsString('|size=M|', $variant1['FilterAttributes']);
    }

    public function testDeeplinkUsesOnlineStoreUrlWhenHandleMissing(): void
    {
        $product = [
            'legacyResourceId' => '999',
            'title' => 'NoHandle',
            'translations' => [],
            'descriptionHtml' => '',
            'vendor' => '',
            'handle' => '',
            'onlineStoreUrl' => 'https://external.example.com/product/999',
            'images' => ['edges' => []],
            'category' => null,
            'variants' => ['edges' => []],
        ];

        $rows = iterator_to_array($this->mapper->map([$product], 'some-shop.com'), false);
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertEquals('https://external.example.com/product/999', $row['Deeplink']);
    }

    public function testCategoryPathReturnsUncategorizedWhenMissing(): void
    {
        $product = [
            'legacyResourceId' => '555',
            'title' => 'NoCategory',
            'translations' => [],
            'descriptionHtml' => '',
            'vendor' => '',
            'handle' => '',
            'onlineStoreUrl' => null,
            'images' => ['edges' => []],
            'category' => null,
            'variants' => ['edges' => []],
        ];

        $rows = iterator_to_array($this->mapper->map([$product], 'shop.com'), false);
        $this->assertEquals('Uncategorized', $rows[0]['CategoryPath']);
    }
}
