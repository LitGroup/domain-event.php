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

use LitGroup\DomainEvent\DomainEvent;
use LitGroup\DomainEvent\DomainEventPublisher;
use LitGroup\DomainEvent\DomainEventSubscriber;
use PHPUnit\Framework\TestCase;

class DomainEventPublisherTest extends TestCase
{
    protected function setUp(): void
    {
        DomainEventPublisher::instance()->reset();
    }

    protected function tearDown(): void
    {
        DomainEventPublisher::instance()->reset();
    }

    function testTargetEventListening(): void
    {
        $caughtEvents = [];

        DomainEventPublisher::instance()
            ->listen(TestEvent::class, function (TestEvent $event) use (&$caughtEvents) {
                $caughtEvents[] = $event;
            })
            ->listen(AnotherTestEvent::class, function (AnotherTestEvent $event) use (&$caughtEvents) {
                $caughtEvents[] = $event;
            });

        DomainEventPublisher::instance()
            ->publish(new TestEvent());
        DomainEventPublisher::instance()
            ->publish(new AnotherTestEvent());

        self::assertCount(2, $caughtEvents);
        self::assertInstanceOf(TestEvent::class, $caughtEvents[0]);
        self::assertInstanceOf(AnotherTestEvent::class, $caughtEvents[1]);
    }

    function testArgumentExceptionForInvalidEventClassOnSubscription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DomainEventPublisher::instance()->listen('stdClass', function () {});
    }

    function testBroadcastListening(): void
    {
        $caughtEvents = [];
        DomainEventPublisher::instance()
            ->listen(DomainEvent::class, function (DomainEvent $event) use (&$caughtEvents) {
                $caughtEvents[] = $event;
            });

        DomainEventPublisher::instance()->publish(new TestEvent());
        DomainEventPublisher::instance()->publish(new AnotherTestEvent());

        self::assertCount(2, $caughtEvents);
        self::assertInstanceOf(TestEvent::class, $caughtEvents[0]);
        self::assertInstanceOf(AnotherTestEvent::class, $caughtEvents[1]);
    }

    function testRecursivePublishing(): void
    {
        /** @var DomainEvent[] $handledEvents */
        $handledEvents = [];

        DomainEventPublisher::instance()->listen(TestEvent::class, function (TestEvent $event) use (&$handledEvents) {
            $handledEvents[] = $event;
            DomainEventPublisher::instance()->publish(new AnotherTestEvent());
        });

        DomainEventPublisher::instance()->listen(AnotherTestEvent::class, function (AnotherTestEvent $event) use (&$handledEvents) {
            $handledEvents[] = $event;
        });

        DomainEventPublisher::instance()->publish(new TestEvent());
        self::assertCount(2, $handledEvents);
    }

    function testExceptionRethrowingDuringPublishing(): void
    {
        DomainEventPublisher::instance()->listen(TestEvent::class, function () {
            DomainEventPublisher::instance()->publish(new AnotherTestEvent());
        });

        DomainEventPublisher::instance()->listen(AnotherTestEvent::class, function () {
            throw new ExampleException();
        });

        try {
            DomainEventPublisher::instance()->publish(new TestEvent());
            $this->fail();
        } catch (ExampleException $e) {}

        // Check that DomainEventPublisher state was not corrupted by exception
        // (internal publishing counter must be reset to 0).
        DomainEventPublisher::instance()->reset();
    }

    function testResetting(): void
    {
        DomainEventPublisher::instance()
            ->listen(TestEvent::class, function () { throw new \BadMethodCallException('Must not be called'); })
            ->subscribe(new class implements DomainEventSubscriber {
                public function getListenedEventClass(): string
                {
                    return TestEvent::class;
                }

                public function handleEvent(DomainEvent $event): void
                {
                    throw new \BadMethodCallException('Must not be called');
                }
            });

        DomainEventPublisher::instance()->reset();
        DomainEventPublisher::instance()->publish(new TestEvent());
    }

    function testResettingRestrictionDuringPublishing(): void
    {
        DomainEventPublisher::instance()->listen(DomainEvent::class, function () {
            DomainEventPublisher::instance()->reset();
        });

        $this->expectException(\RuntimeException::class);
        DomainEventPublisher::instance()->publish(new TestEvent());
    }

    function testSubscription(): void
    {
        $testEventSubscriber = new TestEventSubscriber();
        $broadcastSubscriber = new BroadcastSubscriber();

        DomainEventPublisher::instance()
            ->subscribe($testEventSubscriber)
            ->subscribe($broadcastSubscriber);

        DomainEventPublisher::instance()->publish(new TestEvent());
        DomainEventPublisher::instance()->publish(new AnotherTestEvent());

        self::assertCount(1, $testEventSubscriber->getHandledEvents());
        self::assertCount(2, $broadcastSubscriber->getHandledEvents());
    }

    function testSubscriptionExceptionForInvalidEventClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DomainEventPublisher::instance()
            ->subscribe(new class  implements DomainEventSubscriber {
                public function getListenedEventClass(): string
                {
                    return \stdClass::class;
                }

                public function handleEvent(DomainEvent $event): void
                {
                    // Nothing to do.
                }
            });
    }
}

class ExampleException extends \Exception {}
