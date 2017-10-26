<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ProductConfiguratorGroupWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'product_configurator_group.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_configurator_group';
    }
}
