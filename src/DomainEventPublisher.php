<?php
/**
 * Copyright 2017 LitGroup, LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

declare(strict_types=1);

namespace LitGroup\DomainEvent;

use function get_class;
use function is_subclass_of;
use function call_user_func;

final class DomainEventPublisher
{
    /** @var self */
    private static $instance;

    /** @var DomainEventSubscriber[] */
    private $subscribers = [];

    /** @var int */
    private $publishingCounter = 0;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function subscribe(DomainEventSubscriber $subscriber): self
    {
        if ($subscriber->getListenedEventClass() !== DomainEvent::class &&
            !is_subclass_of($subscriber->getListenedEventClass(), DomainEvent::class, true)) {
            throw new \InvalidArgumentException('Listened event class must be of type DomainEvent.');
        }

        $this->subscribers[] = $subscriber;

        return $this;
    }

    /**
     * @param string $eventClass Fully-qualified class name of an event.
     * @param callable $handler Function like `function (DomainEvent $event) { ... }`
     *
     * @return DomainEventPublisher
     */
    public function listen(string $eventClass, callable $handler): self
    {
        return $this->subscribe($this->createCallbackSubscriber($eventClass, $handler));
    }

    /**
     * @param DomainEvent $event
     *
     * @throws \Exception Rethrows any exception from listener.
     */
    public function publish(DomainEvent $event): void
    {
        try {
            $this->incrementPublishingCounter();

            $eventClass = get_class($event);
            foreach ($this->getSubscribers() as $subscriber) {
                if ($subscriber->getListenedEventClass() === $eventClass ||
                    $subscriber->getListenedEventClass() === DomainEvent::class) {
                    $subscriber->handleEvent($event);
                }
            }
        } finally {
            $this->decrementPublishingCounter();
        }
    }

    public function reset(): void
    {
        if ($this->isPublishing()) {
            throw new \RuntimeException(
                'Resetting of domain event publisher is forbidden during a publication of event.');
        }

        $this->unsubscribeAll();
    }

    /**
     * @return DomainEventSubscriber[]
     */
    private function getSubscribers(): array
    {
        return $this->subscribers;
    }

    private function unsubscribeAll(): void
    {
        $this->subscribers = [];
    }

    private function isPublishing(): bool
    {
        return $this->publishingCounter > 0;
    }

    private function incrementPublishingCounter(): void
    {
        $this->publishingCounter++;
    }

    private function decrementPublishingCounter(): void
    {
        $this->publishingCounter = $this->publishingCounter > 0 ? $this->publishingCounter - 1 : 0;
    }

    private function createCallbackSubscriber(string $eventClass, callable $handler): DomainEventSubscriber
    {
        return new class($eventClass, $handler) implements DomainEventSubscriber {
            /** @var string */
            private $eventClass;

            /** @var callable */
            private $handler;

            public function __construct(string $eventClass, callable $handler)
            {
                $this->eventClass = $eventClass;
                $this->handler = $handler;
            }

            public function getListenedEventClass(): string
            {
                return $this->eventClass;
            }

            public function handleEvent(DomainEvent $event): void
            {
                call_user_func($this->getHandler(), $event);
            }

            private function getHandler(): callable
            {
                return $this->handler;
            }
        };
    }

    private function __construct() {}
    public function __sleep() {}
    public function __wakeup() {}
    public function __clone() {}
}