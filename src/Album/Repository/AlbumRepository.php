<?php declare(strict_types=1);

namespace Shopware\Album\Repository;

use Shopware\Album\Event\AlbumBasicLoadedEvent;
use Shopware\Album\Event\AlbumDetailLoadedEvent;
use Shopware\Album\Event\AlbumWrittenEvent;
use Shopware\Album\Reader\AlbumBasicReader;
use Shopware\Album\Reader\AlbumDetailReader;
use Shopware\Album\Searcher\AlbumSearcher;
use Shopware\Album\Searcher\AlbumSearchResult;
use Shopware\Album\Struct\AlbumBasicCollection;
use Shopware\Album\Struct\AlbumDetailCollection;
use Shopware\Album\Writer\AlbumWriter;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AlbumRepository
{
    /**
     * @var AlbumDetailReader
     */
    protected $detailReader;

    /**
     * @var AlbumBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AlbumSearcher
     */
    private $searcher;

    /**
     * @var AlbumWriter
     */
    private $writer;

    public function __construct(
        AlbumDetailReader $detailReader,
        AlbumBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        AlbumSearcher $searcher,
        AlbumWriter $writer
    ) {
        $this->detailReader = $detailReader;
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): AlbumBasicCollection
    {
        if (empty($uuids)) {
            return new AlbumBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            AlbumBasicLoadedEvent::NAME,
            new AlbumBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): AlbumDetailCollection
    {
        if (empty($uuids)) {
            return new AlbumDetailCollection();
        }
        $collection = $this->detailReader->readDetail($uuids, $context);

        $this->eventDispatcher->dispatch(
            AlbumDetailLoadedEvent::NAME,
            new AlbumDetailLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function search(Criteria $criteria, TranslationContext $context): AlbumSearchResult
    {
        /** @var AlbumSearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            AlbumBasicLoadedEvent::NAME,
            new AlbumBasicLoadedEvent($result, $context)
        );

        return $result;
    }

    public function searchUuids(Criteria $criteria, TranslationContext $context): UuidSearchResult
    {
        return $this->searcher->searchUuids($criteria, $context);
    }

    public function aggregate(Criteria $criteria, TranslationContext $context): AggregationResult
    {
        $result = $this->searcher->aggregate($criteria, $context);

        return $result;
    }

    public function update(array $data, TranslationContext $context): AlbumWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): AlbumWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): AlbumWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
