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

    /** @var array */
    private $handlers = [];

    /** @var bool */
    private $publishing = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $eventClass Fully-qualified class name of an event.
     * @param callable $handler Function like `function (DomainEvent $event) { ... }`
     *
     * @return DomainEventPublisher
     */
    public function listen(string $eventClass, callable $handler): self
    {
        if ($eventClass !== DomainEvent::class &&
            !is_subclass_of($eventClass, DomainEvent::class, true)) {
            throw new \InvalidArgumentException('Event class must be a subclass of DomainEvent.');
        }

        if (!array_key_exists($eventClass, $this->handlers)) {
            $this->handlers[$eventClass] = [];
        }

        $this->handlers[$eventClass][] = $handler;

        return $this;
    }

    /**
     * @param DomainEvent $event
     *
     * @throws \Exception Rethrows any exception from listener.
     */
    public function publish(DomainEvent $event): void
    {
        if ($this->isPublishing()) {
            throw new \RuntimeException('Recursive publishing of domain events is forbidden.');
        }

        try {
            $this->startPublishing();
            foreach ($this->getHandlersOf(get_class($event)) as $handler) {
                call_user_func($handler, $event);
            }
            foreach ($this->getHandlersOf(DomainEvent::class) as $handler) {
                call_user_func($handler, $event);
            }
        } finally {
            $this->stopPublishing();
        }
    }

    public function reset(): void
    {
        if ($this->isPublishing()) {
            throw new \RuntimeException(
                'Resetting of domain event publisher is restricted during a publication of event.');
        }

        $this->handlers = [];
    }

    private function getHandlersOf(string $eventClass): array
    {
        if (!array_key_exists($eventClass, $this->handlers)) {
            return [];
        }

        return $this->handlers[$eventClass];
    }

    private function isPublishing(): bool
    {
        return $this->publishing;
    }

    private function startPublishing(): void
    {
        $this->publishing = true;
    }

    private function stopPublishing(): void
    {
        $this->publishing = false;
    }

    private function __construct() {}
    public function __sleep() {}
    public function __wakeup() {}
    public function __clone() {}
}