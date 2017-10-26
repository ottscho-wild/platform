<?php declare(strict_types=1);

namespace Shopware\Holiday\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\RepositoryInterface;
use Shopware\Framework\Write\EntityWrittenEvent;
use Shopware\Holiday\Event\HolidayBasicLoadedEvent;
use Shopware\Holiday\Event\HolidayWrittenEvent;
use Shopware\Holiday\Reader\HolidayBasicReader;
use Shopware\Holiday\Searcher\HolidaySearcher;
use Shopware\Holiday\Searcher\HolidaySearchResult;
use Shopware\Holiday\Struct\HolidayBasicCollection;
use Shopware\Holiday\Writer\HolidayWriter;
use Shopware\Search\AggregationResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class HolidayRepository implements RepositoryInterface
{
    /**
     * @var HolidayBasicReader
     */
    private $basicReader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var HolidaySearcher
     */
    private $searcher;

    /**
     * @var HolidayWriter
     */
    private $writer;

    public function __construct(
        HolidayBasicReader $basicReader,
        EventDispatcherInterface $eventDispatcher,
        HolidaySearcher $searcher,
        HolidayWriter $writer
    ) {
        $this->basicReader = $basicReader;
        $this->eventDispatcher = $eventDispatcher;
        $this->searcher = $searcher;
        $this->writer = $writer;
    }

    public function readBasic(array $uuids, TranslationContext $context): HolidayBasicCollection
    {
        if (empty($uuids)) {
            return new HolidayBasicCollection();
        }

        $collection = $this->basicReader->readBasic($uuids, $context);

        $this->eventDispatcher->dispatch(
            HolidayBasicLoadedEvent::NAME,
            new HolidayBasicLoadedEvent($collection, $context)
        );

        return $collection;
    }

    public function readDetail(array $uuids, TranslationContext $context): HolidayBasicCollection
    {
        return $this->readBasic($uuids, $context);
    }

    public function search(Criteria $criteria, TranslationContext $context): HolidaySearchResult
    {
        /** @var HolidaySearchResult $result */
        $result = $this->searcher->search($criteria, $context);

        $this->eventDispatcher->dispatch(
            HolidayBasicLoadedEvent::NAME,
            new HolidayBasicLoadedEvent($result, $context)
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

    public function update(array $data, TranslationContext $context): HolidayWrittenEvent
    {
        $event = $this->writer->update($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function upsert(array $data, TranslationContext $context): HolidayWrittenEvent
    {
        $event = $this->writer->upsert($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }

    public function create(array $data, TranslationContext $context): HolidayWrittenEvent
    {
        $event = $this->writer->create($data, $context);

        $container = new EntityWrittenEvent($event, $context);
        $this->eventDispatcher->dispatch($container::NAME, $container);

        return $event;
    }
}
