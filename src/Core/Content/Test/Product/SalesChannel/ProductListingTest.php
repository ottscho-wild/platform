<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGateway;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Component\HttpFoundation\Request;

class ProductListingTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ProductListingGateway
     */
    private $listingGateway;

    /**
     * @var string
     */
    private $categoryId;

    /**
     * @var ListingTestData
     */
    private $testData;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listingGateway = $this->getContainer()->get(ProductListingGateway::class);

        $this->connection = $this->getContainer()->get(Connection::class);

        $parent = $this->connection->fetchColumn(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL)]
        );

        $this->categoryId = Uuid::randomHex();

        $this->getContainer()->get('category.repository')
            ->create([['id' => $this->categoryId, 'name' => 'test', 'parentId' => $parent]], Context::createDefaultContext());

        $this->testData = new ListingTestData();

        $this->insertOptions();

        $this->insertProducts();
    }

    public function testListing()
    {
        $request = new Request();

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $request->attributes->set('_route_params', [
            'navigationId' => $this->categoryId,
        ]);

        $listing = $this->listingGateway->search($request, $context);

        static::assertSame(10, $listing->getTotal());
        static::assertFalse($listing->has($this->testData->getId('product1')));

        static::assertVariationsInListing($listing, [
            $this->testData->getId('product1-red-l-steel'),
            $this->testData->getId('product1-red-xl-steel'),
            $this->testData->getId('product1-red-l-iron'),
            $this->testData->getId('product1-red-xl-iron'),
        ]);
        static::assertVariationsInListing($listing, [
            $this->testData->getId('product1-green-l-steel'),
            $this->testData->getId('product1-green-xl-steel'),
            $this->testData->getId('product1-green-l-iron'),
            $this->testData->getId('product1-green-xl-iron'),
        ]);

        // product 2 should display only the both color variants
        static::assertFalse($listing->has($this->testData->getId('product2')));
        static::assertTrue($listing->has($this->testData->getId('product2-green')));
        static::assertTrue($listing->has($this->testData->getId('product2-red')));

        // product 3 has no variants
        static::assertTrue($listing->has($this->testData->getId('product3')));

        static::assertVariationsInListing($listing, [
            $this->testData->getId('product4-red-l-iron'),
            $this->testData->getId('product4-red-xl-iron'),
        ]);
        static::assertVariationsInListing($listing, [
            $this->testData->getId('product4-red-l-steel'),
            $this->testData->getId('product4-red-xl-steel'),
        ]);
        static::assertVariationsInListing($listing, [
            $this->testData->getId('product4-green-l-iron'),
            $this->testData->getId('product4-green-xl-iron'),
        ]);
        static::assertVariationsInListing($listing, [
            $this->testData->getId('product4-green-l-steel'),
            $this->testData->getId('product4-green-xl-steel'),
        ]);

        static::assertVariationsInListing($listing, [
            $this->testData->getId('product5-red'),
            $this->testData->getId('product5-green'),
        ]);

        /** @var AggregationResult $options */
        $options = $listing->getAggregations()->get('options');

        $options = $options->getResult()[0];

        /** @var EntityResult $options */
        $options = $options->getEntities();

        /** @var PropertyGroupOptionCollection $options */
        static::assertTrue($options->has($this->testData->getId('green')));
        static::assertTrue($options->has($this->testData->getId('red')));
        static::assertTrue($options->has($this->testData->getId('xl')));
        static::assertTrue($options->has($this->testData->getId('l')));
        static::assertTrue($options->has($this->testData->getId('iron')));
        static::assertTrue($options->has($this->testData->getId('steel')));
        static::assertFalse($options->has($this->testData->getId('yellow')));
        static::assertFalse($options->has($this->testData->getId('cotton')));
    }

    /**
     * Small helper function which asserts the one of the provided pool ids are in the result set but the remaining ids are excluded.
     */
    private static function assertVariationsInListing(EntitySearchResult $result, array $pool)
    {
        $match = null;
        // find matching id
        foreach ($pool as $index => $id) {
            if ($result->has($id)) {
                $match = $id;
                unset($pool[$index]);
                break;
            }
        }
        // assert that one id found
        static::assertNotNull($match);

        // after one id found, assert that all other ids are not inside the result set
        foreach ($pool as $id) {
            static::assertFalse($result->has($id));
        }
    }

    private function insertProducts(): void
    {
        $this->createProduct(
            'product1',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
                [$this->testData->getId('xl'), $this->testData->getId('l')],
                [$this->testData->getId('iron'), $this->testData->getId('steel')],
            ],
            [$this->testData->getId('color')]
        );

        $this->createProduct(
            'product2',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
            ],
            [$this->testData->getId('color')]
        );

        $this->createProduct('product3', [], []);

        $this->createProduct(
            'product4',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
                [$this->testData->getId('xl'), $this->testData->getId('l')],
                [$this->testData->getId('iron'), $this->testData->getId('steel')],
            ],
            [$this->testData->getId('color'), $this->testData->getId('material')]
        );

        $this->createProduct(
            'product5',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
            ],
            []
        );
    }

    private function createProduct(string $key, array $options, array $listingGroups)
    {
        $config = [];
        foreach ($listingGroups as $groupId) {
            $config[] = [
                'id' => $groupId,
                'expressionForListings' => true,
                'representation' => 'box', // box, select, image, color
            ];
        }

        $configurator = [];
        foreach ($options as $grouped) {
            foreach ($grouped as $optionId) {
                $configurator[] = ['optionId' => $optionId];
            }
        }

        $id = $this->testData->createId($key);
        $data = [
            [
                'id' => $id,
                'configuratorGroupConfig' => $config,
                'productNumber' => $id,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'name' => $key,
                'active' => true,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
                ],
                'categories' => [
                    ['id' => $this->categoryId],
                ],
                'configuratorSettings' => $configurator,
                'visibilities' => [
                    [
                        'salesChannelId' => Defaults::SALES_CHANNEL,
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
        ];

        if (!empty($options)) {
            $combinations = $this->combos($options);
            foreach ($combinations as $index => $combination) {
                $variantKey = $key . '-' . implode('-', $this->testData->getKeyList($combination));

                $data[] = [
                    'id' => $this->testData->createId($variantKey),
                    'productNumber' => $key . '.' . $index,
                    'stock' => 10,
                    'name' => $variantKey,
                    'active' => true,
                    'parentId' => $this->testData->getId($key),
                    'options' => array_map(function ($id) {
                        return ['id' => $id];
                    }, $combination),
                ];
            }
        }

        $repo = $this->getContainer()->get('product.repository');

        /* @var EntityRepositoryInterface $repo */
        $repo->create($data, Context::createDefaultContext());
    }

    private function combos($data, &$all = [], $group = [], $val = null, $i = 0)
    {
        if (isset($val)) {
            array_push($group, $val);
        }
        if ($i >= count($data)) {
            array_push($all, $group);
        } else {
            foreach ($data[$i] as $v) {
                $this->combos($data, $all, $group, $v, $i + 1);
            }
        }

        return $all;
    }

    private function insertOptions()
    {
        $data = [
            [
                'id' => $this->testData->createId('color'),
                'name' => 'color',
                'options' => [
                    ['id' => $this->testData->createId('green'), 'name' => 'green'],
                    ['id' => $this->testData->createId('red'), 'name' => 'red'],
                    ['id' => $this->testData->createId('yellow'), 'name' => 'red'],
                ],
            ],
            [
                'id' => $this->testData->createId('size'),
                'name' => 'size',
                'options' => [
                    ['id' => $this->testData->createId('xl'), 'name' => 'XL'],
                    ['id' => $this->testData->createId('l'), 'name' => 'L'],
                ],
            ],
            [
                'id' => $this->testData->createId('material'),
                'name' => 'material',
                'options' => [
                    ['id' => $this->testData->createId('iron'), 'name' => 'iron'],
                    ['id' => $this->testData->createId('steel'), 'name' => 'steel'],
                    ['id' => $this->testData->createId('cotton'), 'name' => 'steel'],
                ],
            ],
        ];

        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('property_group.repository');
        $repo->create($data, Context::createDefaultContext());
    }
}

class ListingTestData
{
    /**
     * @var array
     */
    protected $ids = [];

    public function getId(string $key)
    {
        return $this->ids[$key];
    }

    public function createId(string $key): string
    {
        return $this->ids[$key] = Uuid::randomHex();
    }

    public function getKey(string $id)
    {
        $ids = array_flip($this->ids);

        return $ids[$id];
    }

    public function getKeyList(array $ids)
    {
        $keys = [];
        $flipped = array_flip($this->ids);
        foreach ($ids as $id) {
            $keys[] = $flipped[$id];
        }

        return $keys;
    }

    public function all(): array
    {
        return $this->ids;
    }
}