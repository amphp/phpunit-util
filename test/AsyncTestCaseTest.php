<?php

namespace Amp\PHPUnit\Test;

use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\LoopCaughtException;
use Amp\PHPUnit\TestException;
use PHPUnit\Framework\AssertionFailedError;
use Revolt\EventLoop\Loop;
use Revolt\Future\Deferred;
use Revolt\Future\Future;
use function Revolt\EventLoop\defer;
use function Revolt\EventLoop\delay;
use function Revolt\Future\spawn;


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

        defer(function () use ($testDeferred, $returnDeferred): void {
            $data = $testDeferred->getFuture()->join();
            self::assertEquals('foobar', $data, 'Expected the data to be what was resolved in Loop::defer');
            $returnDeferred->complete(null);
        });

        Loop::queue(function () use ($testDeferred): void {
            $testDeferred->complete('foobar');
        });

        return $returnDeferred->getFuture();
    }

    public function testThatWeHandleNullReturn(): void
    {
        $testDeferred = new Deferred;
        $testData = new \stdClass;
        $testData->val = null;
        Loop::defer(function () use ($testData, $testDeferred) {
            $testData->val = true;
            $testDeferred->complete(null);
        });

        $testDeferred->getFuture()->join();

        self::assertTrue($testData->val, 'Expected our test to run on loop to completion');
    }

    public function testReturningFuture(): Future
    {
        $deferred = new Deferred;

        Loop::delay(100, fn () => $deferred->complete('value'));

        $returnValue = $deferred->getFuture();
        self::assertInstanceOf(Future::class, $returnValue); // An assertion is required for the test to pass
        return $returnValue; // Return value used by testReturnValueFromDependentTest
    }

    public function testExpectingAnExceptionThrown(): Future
    {
        $throwException = function () {
            delay(100);
            throw new \Exception('threw the error');
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('threw the error');

        return spawn($throwException);
    }

    public function testExpectingAnErrorThrown(): Future
    {
        $this->expectException(\Error::class);

        return spawn(function (): void {
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
        $this->setTimeout(100);
        $this->expectNotToPerformAssertions();
        delay(50);
    }

    public function testSetTimeoutWithFuture(): Future
    {
        $this->setTimeout(100);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected test to complete before 100ms time limit');

        $deferred = new Deferred;

        Loop::delay(200, fn () => $deferred->complete(null));

        return $deferred->getFuture();
    }

    public function testSetTimeoutWithAwait(): void
    {
        $this->setTimeout(100);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected test to complete before 100ms time limit');

        delay(200);
    }

    public function testSetMinimumRunTime(): void
    {
        $this->setMinimumRuntime(100);

        $this->expectException(AssertionFailedError::class);
        $pattern = "/Expected test to take at least 100ms but instead took (\d+)ms/";
        $this->expectExceptionMessageMatches($pattern);

        delay(75);
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
        defer(function (): void {
            throw new TestException('message');
        });

        $this->expectException(LoopCaughtException::class);
        $pattern = "/(.+) thrown to event loop error handler: (.*)/";
        $this->expectExceptionMessageMatches($pattern);

        (new Deferred)->getFuture()->join();
    }

    public function testFailsWithActiveLoopWatcher(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Found enabled watchers at end of test");

        Loop::delay(10, $this->createCallback(0));
    }

    public function testIgnoreWatchers(): void
    {
        $this->ignoreLoopWatchers();

        Loop::delay(10, $this->createCallback(0));
    }

    public function testIgnoreUnreferencedWatchers(): void
    {
        Loop::unreference(Loop::delay(10, $this->createCallback(0)));
    }

    public function testFailsWithActiveUnresolvedLoopWatcher(): void
    {
        $this->checkUnreferencedLoopWatchers();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Found enabled watchers at end of test");

        Loop::unreference(Loop::delay(10, $this->createCallback(0)));
    }

    public function testCleanupInvoked(): void
    {
        // Exception thrown in cleanup() to assert method is invoked.
    }
}
