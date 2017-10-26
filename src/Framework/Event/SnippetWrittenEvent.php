<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class SnippetWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'snippet.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'snippet';
    }
}
