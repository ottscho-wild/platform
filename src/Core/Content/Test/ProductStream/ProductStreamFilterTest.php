<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductStream;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ProductStreamFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var string
     */
    private $streamId;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $productStreamRepository;

    protected function setUp()
    {
        $this->repository = $this->getContainer()->get('product_stream_filter.repository');
        $this->productStreamRepository = $this->getContainer()->get('product_stream.repository');
        $this->streamId = Uuid::uuid4()->getHex();
        $this->context = Context::createDefaultContext();
        $this->productStreamRepository->upsert([['id' => $this->streamId, 'name' => 'Test stream']], $this->context);
    }

    public function testCreateEntity()
    {
        $id = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $id, 'type' => 'equals', 'value' => 'awesome', 'field' => 'product.name', 'productStreamId' => $this->streamId],
        ], $this->context);

        /** @var ProductStreamEntity $entity */
        $entity = $this->productStreamRepository->search(new Criteria([$this->streamId]), $this->context)->get($this->streamId);
        static::assertSame([['type' => 'equals', 'field' => 'product.name', 'value' => 'awesome']], $entity->getFilter());
    }

    public function testUpdateEntity()
    {
        $id = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $id, 'type' => 'equals', 'value' => 'new awesome', 'field' => 'product.name', 'productStreamId' => $this->streamId],
        ], $this->context);
        $this->repository->upsert([
            ['id' => $id, 'type' => 'range', 'field' => 'product.weight', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100], 'productStreamId' => $this->streamId],
        ], $this->context);

        /** @var ProductStreamEntity $entity */
        $entity = $this->productStreamRepository->search(new Criteria([$this->streamId]), $this->context)->get($this->streamId);
        static::assertSame([['type' => 'range', 'field' => 'product.weight', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100]]], $entity->getFilter());
    }

    public function testRangeEntity()
    {
        $id = Uuid::uuid4()->getHex();
        $this->repository->create([
            ['id' => $id, 'type' => 'range', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100], 'field' => 'product.weight', 'productStreamId' => $this->streamId],
        ], $this->context);

        /** @var ProductStreamEntity $entity */
        $entity = $this->productStreamRepository->search(new Criteria([$this->streamId]), $this->context)->get($this->streamId);
        static::assertSame([['type' => 'range', 'field' => 'product.weight', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100]]], $entity->getFilter());
    }
}
