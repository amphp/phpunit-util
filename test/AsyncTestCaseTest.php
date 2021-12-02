<?php

namespace Amp\PHPUnit\Test;

use Amp\Deferred;
use Amp\Future;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\LoopCaughtException;
use Amp\PHPUnit\TestException;
use PHPUnit\Framework\AssertionFailedError;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\delay;

class AsyncTestCaseTest extends AsyncTestCase
{
    public function cleanup(): void
    {
        parent::cleanup();

        if ($this->getName() === 'testCleanupInvoked') {
            $exception = new TestException;
            $this->expectExceptionObject($exception);
            throw $exception;
        }
    }

    public function testThatMethodRunsInLoopContext(): Future
    {
        $returnDeferred = new Deferred; // make sure our test runs to completion
        $testDeferred = new Deferred; // used by our defer callback to ensure we're running on the Loop

        EventLoop::queue(function () use ($testDeferred, $returnDeferred): void {
            $data = $testDeferred->getFuture()->await();
            self::assertEquals('foobar', $data, 'Expected the data to be what was resolved in EventLoop::defer');
            $returnDeferred->complete(null);
        });

        EventLoop::queue(function () use ($testDeferred): void {
            $testDeferred->complete('foobar');
        });

        return $returnDeferred->getFuture();
    }

    public function testThatWeHandleNullReturn(): void
    {
        $testDeferred = new Deferred;
        $testData = new \stdClass;
        $testData->val = null;
        EventLoop::defer(function () use ($testData, $testDeferred) {
            $testData->val = true;
            $testDeferred->complete(null);
        });

        $testDeferred->getFuture()->await();

        self::assertTrue($testData->val, 'Expected our test to run on loop to completion');
    }

    public function testReturningFuture(): Future
    {
        $deferred = new Deferred;

        EventLoop::delay(0.1, fn () => $deferred->complete('value'));

        $returnValue = $deferred->getFuture();
        self::assertInstanceOf(Future::class, $returnValue); // An assertion is required for the test to pass
        return $returnValue; // Return value used by testReturnValueFromDependentTest
    }

    public function testExpectingAnExceptionThrown(): Future
    {
        $throwException = function (): void {
            delay(0.1);
            throw new \Exception('threw the error');
        };

        $this->expectException(\Exception::class);
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

    public function argumentSupportProvider(): array
    {
        return [
            ['foo', 42, true],
        ];
    }

    /**
     * @param string $foo
     * @param int    $bar
     * @param bool   $baz
     *
     * @dataProvider argumentSupportProvider
     */
    public function testArgumentSupport(string $foo, int $bar, bool $baz): void
    {
        self::assertSame('foo', $foo);
        self::assertSame(42, $bar);
        self::assertTrue($baz);
    }

    /**
     * @param string|null $value
     *
     * @depends testReturningFuture
     */
    public function testReturnValueFromDependentTest(string $value = null): void
    {
        self::assertSame('value', $value);
    }

    public function testSetTimeout(): void
    {
        $this->setTimeout(0.1);
        $this->expectNotToPerformAssertions();
        delay(0.05);
    }

    public function testSetTimeoutWithFuture(): Future
    {
        $this->setTimeout(0.1);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected test to complete before 0.100s time limit');

        $deferred = new Deferred;

        EventLoop::delay(0.2, fn () => $deferred->complete(null));

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
        $this->setMinimumRuntime(0.1);

        $this->expectException(AssertionFailedError::class);
        $pattern = "/Expected test to take at least 0.100s but instead took 0.(\d+)s/";
        $this->expectExceptionMessageMatches($pattern);

        delay(0.05);
    }

    public function testCreateCallback(): void
    {
        $mock = $this->createCallback(1, function (int $value): int {
            return $value + 1;
        });

        self::assertSame(2, $mock(1));
    }

    public function testThrowToEventLoop(): void
    {
        $this->setTimeout(0.1);

        EventLoop::queue(static fn () => throw new TestException('message'));

        $this->expectException(LoopCaughtException::class);
        $pattern = "/(.+) thrown to event loop error handler: (.*)/";
        $this->expectExceptionMessageMatches($pattern);

        (new Deferred)->getFuture()->await();
    }

    public function testCleanupInvoked(): void
    {
        // Exception thrown in cleanup() to assert method is invoked.
    }
}
