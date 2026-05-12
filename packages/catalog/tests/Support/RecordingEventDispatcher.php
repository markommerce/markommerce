<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;

class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var array<Event> */
    public array $events = [];

    public function dispatch(Event $event): void
    {
        $this->events[] = $event;
    }
}
