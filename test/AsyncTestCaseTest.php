<?php

namespace Amp\PHPUnit\Test;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\LoopCaughtException;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use PHPUnit\Framework\AssertionFailedError;
use function Amp\async;
use function Amp\await;
use function Amp\defer;
use function Amp\delay;

class AsyncTestCaseTest extends AsyncTestCase
{
    public function testThatMethodRunsInLoopContext(): Promise
    {
        $returnDeferred = new Deferred; // make sure our test runs to completion
        $testDeferred = new Deferred; // used by our defer callback to ensure we're running on the Loop
        $testDeferred->promise()->onResolve(function ($err = null, $data = null) use ($returnDeferred) {
            $this->assertEquals('foobar', $data, 'Expected the data to be what was resolved in Loop::defer');
            $returnDeferred->resolve();
        });
        Loop::defer(function () use ($testDeferred) {
            $testDeferred->resolve('foobar');
        });

        return $returnDeferred->promise();
    }

    public function testThatWeHandleNotPromiseReturned(): void
    {
        $testDeferred = new Deferred;
        $testData = new \stdClass;
        $testData->val = null;
        Loop::defer(function () use ($testData, $testDeferred) {
            $testData->val = true;
            $testDeferred->resolve();
        });

        await($testDeferred->promise());

        $this->assertTrue($testData->val, 'Expected our test to run on loop to completion');
    }

    public function testReturningPromise(): Promise
    {
        $returnValue = new Delayed(100, 'value');
        $this->assertInstanceOf(Promise::class, $returnValue); // An assertion is required for the test to pass
        return $returnValue; // Return value used by testReturnValueFromDependentTest
    }

    public function testExpectingAnExceptionThrown(): Promise
    {
        $throwException = function () {
            delay(100);
            throw new \Exception('threw the error');
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('threw the error');

        return async($throwException);
    }

    public function testExpectingAnErrorThrown(): Promise
    {
        $this->expectException(\Error::class);

        return async(function () {
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
        $this->assertSame('foo', $foo);
        $this->assertSame(42, $bar);
        $this->assertTrue($baz);
    }

    /**
     * @param string|null $value
     *
     * @depends testReturningPromise
     */
    public function testReturnValueFromDependentTest(string $value = null): void
    {
        $this->assertSame('value', $value);
    }

    public function testSetTimeout(): void
    {
        $this->setTimeout(100);
        $this->expectNotToPerformAssertions();
        delay(50);
    }

    public function testSetTimeoutWithPromise(): Promise
    {
        $this->setTimeout(100);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected test to complete before 100ms time limit');

        return new Delayed(200);
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

        $this->assertSame(2, $mock(1));
    }

    public function testThrowToEventLoop(): void
    {
        defer(function (): void {
            throw new TestException('message');
        });

        $this->expectException(LoopCaughtException::class);
        $pattern = "/(.+) thrown to event loop error handler: (.*)/";
        $this->expectExceptionMessageMatches($pattern);

        delay(0);
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
}
