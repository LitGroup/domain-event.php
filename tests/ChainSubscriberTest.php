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

namespace Test\LitGroup\DomainEvent;

use LitGroup\DomainEvent\ChainSubscriber;
use LitGroup\DomainEvent\DomainEventSubscriber;
use PHPUnit\Framework\TestCase;

class ChainSubscriberTest extends TestCase
{
    function testTestInstantiation(): void
    {
        $chain = new ChainSubscriber(TestEvent::class, [
            new TestEventSubscriber(),
        ]);

        self::assertInstanceOf(DomainEventSubscriber::class, $chain);
        self::assertSame(TestEvent::class, $chain->getListenedEventClass());
    }

    function testExceptionForEmptyEventClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChainSubscriber('', [
            new TestEventSubscriber()
        ]);
    }

    function testExceptionForEmptyListOfSubscribers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChainSubscriber(TestEvent::class, []);
    }

    function testExceptionForHeterogeneousSubscribers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChainSubscriber(TestEvent::class, [
            new AnotherTestEventSubscriber()
        ]);
    }

    function testHandling(): void
    {
        $subscriberA = new TestEventSubscriber();
        $subscriberB = new TestEventSubscriber();

        $chain = new ChainSubscriber(TestEvent::class, [
            $subscriberA,
            $subscriberB
        ]);

        $event = new TestEvent();
        $chain->handleEvent($event);

        self::assertCount(1, $subscriberA->getHandledEvents());
        self::assertSame($event, $subscriberA->getHandledEvents()[0]);

        self::assertCount(1, $subscriberB->getHandledEvents());
        self::assertSame($event, $subscriberB->getHandledEvents()[0]);
    }
}
