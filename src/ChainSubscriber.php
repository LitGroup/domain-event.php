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

use function count;

class ChainSubscriber implements DomainEventSubscriber
{
    /** @var string */
    private $listenedClass;

    /** @var DomainEventSubscriber[] */
    private $subscribers = [];

    /**
     * ChainSubscriber constructor.
     *
     * @param string $eventClass
     * @param DomainEventSubscriber[] $subscribers
     */
    public function __construct(string $eventClass, array $subscribers)
    {
        if ($eventClass === '') {
            throw new \InvalidArgumentException('Event class must not be empty.');
        }
        $this->listenedClass = $eventClass;

        if (count($subscribers) === 0) {
            throw new \InvalidArgumentException('List of subscribers must not be empty.');
        }
        foreach ($subscribers as $subscriber) {
            $this->addSubscriber($subscriber);
        }
    }

    public function getListenedEventClass(): string
    {
        return $this->listenedClass;
    }

    public function handleEvent(DomainEvent $event): void
    {
        foreach ($this->getSubscribers() as $subscriber) {
            $subscriber->handleEvent($event);
        }
    }

    private function addSubscriber(DomainEventSubscriber $subscriber): void
    {
        if ($subscriber->getListenedEventClass() !== $this->getListenedEventClass()) {
            throw new \InvalidArgumentException('Listened event classes mismatch.');
        }
        $this->subscribers[] = $subscriber;
    }

    /**
     * @return DomainEventSubscriber[]
     */
    private function getSubscribers(): array
    {
        return $this->subscribers;
    }
}