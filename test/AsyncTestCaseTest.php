<?php declare(strict_types=1);

namespace Amp\PHPUnit;

use Amp\DeferredFuture;
use Amp\Future;
use PHPUnit\Framework\AssertionFailedError;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\delay;

class AsyncTestCaseTest extends AsyncTestCase
{
    public function testThatMethodRunsInEventLoopContext(): Future
    {
        $returnDeferred = new DeferredFuture; // make sure our test runs to completion
        $testDeferred = new DeferredFuture; // used by our defer callback to ensure we're running on the Loop

        EventLoop::queue(function () use ($testDeferred, $returnDeferred): void {
            $data = $testDeferred->getFuture()->await();
            self::assertSame('foobar', $data);
            $returnDeferred->complete();
        });

        EventLoop::queue(function () use ($testDeferred): void {
            $testDeferred->complete('foobar');
        });

        return $returnDeferred->getFuture();
    }

    public function testHandleNullReturn(): void
    {
        $testDeferred = new DeferredFuture;
        $testData = new \stdClass;
        $testData->val = 0;

        EventLoop::defer(function () use ($testData, $testDeferred) {
            $testData->val++;
            $testDeferred->complete();
        });

        $testDeferred->getFuture()->await();

        self::assertSame(1, $testData->val);
    }

    public function testReturningFuture(): Future
    {
        $deferred = new DeferredFuture;

        EventLoop::delay(0.1, fn () => $deferred->complete('value'));

        $returnValue = $deferred->getFuture();
        $this->expectNotToPerformAssertions();

        return $returnValue; // Return value used by testReturnValueFromDependentTest
    }

    public function testExpectingAnExceptionThrown(): Future
    {
        $throwException = function (): void {
            delay(0.1);

            throw new TestException('threw the error');
        };

        $this->expectException(TestException::class);
        $this->expectExceptionMessage('threw the error');

        return async($throwException);
    }

    public function testExpectingAnErrorThrown(): Future
    {
        $this->expectException(\Error::class);

        return async(function (): void {
            throw new \Error;
        });
    }

    public static function provideArguments(): array
    {
        return [
            ['foo', 42, true],
        ];
    }

    /**
     * @dataProvider provideArguments
     */
    public function testArgumentSupport(string $foo, int $bar, bool $baz): void
    {
        self::assertSame('foo', $foo);
        self::assertSame(42, $bar);
        self::assertTrue($baz);
    }

    /**
     * @depends testReturningFuture
     */
    public function testReturnValueFromDependentTest(string $value = null): void
    {
        self::assertSame('value', $value);
    }

    public function testSetTimeout(): void
    {
        $this->setTimeout(0.5);
        $this->expectNotToPerformAssertions();

        delay(0.25);
    }

    public function testSetTimeoutReplace(): void
    {
        $this->setTimeout(0.5);
        $this->setTimeout(1);

        delay(0.75);

        $this->expectNotToPerformAssertions();
    }

    public function testSetTimeoutWithFuture(): Future
    {
        $this->setTimeout(0.1);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected test to complete before 0.100s time limit');

        $deferred = new DeferredFuture;

        EventLoop::delay(0.2, fn () => $deferred->complete());

        return $deferred->getFuture();
    }

    public function testSetTimeoutWithAwait(): void
    {
        $this->setTimeout(0.1);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected test to complete before 0.100s time limit');

        delay(0.2);
    }

    public function testSetMinimumRunTime(): void
    {
        $this->setMinimumRuntime(1);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches("/Expected test to take at least 1.000s but instead took 0.\d{3}s/");

        delay(0.5);
    }

    public function testCreateCallback(): void
    {
        $mock = $this->createCallback(1, fn (int $value) => $value + 1, [1]);
        self::assertSame(2, $mock(1));
    }

    public function testThrowToEventLoop(): void
    {
        $this->setTimeout(0.1);

        EventLoop::queue(static fn () => throw new TestException('message'));

        $this->expectException(UnhandledException::class);
        $pattern = "/(.+) thrown to event loop error handler: (.*)/";
        $this->expectExceptionMessageMatches($pattern);

        (new DeferredFuture)->getFuture()->await();
    }
}
