<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ProductManufacturerTranslationWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'product_manufacturer_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_manufacturer_translation';
    }
}
