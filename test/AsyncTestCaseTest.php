<?php

namespace Amp\PHPUnit\Test;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use PHPUnit\Framework\AssertionFailedError;
use function Amp\call;

class AsyncTestCaseTest extends AsyncTestCase
{
    public function testThatMethodRunsInLoopContext(): Promise
    {
        $returnDeferred = new Deferred(); // make sure our test runs to completion
        $testDeferred = new Deferred(); // used by our defer callback to ensure we're running on the Loop
        $testDeferred->promise()->onResolve(function ($err = null, $data = null) use ($returnDeferred) {
            $this->assertEquals('foobar', $data, 'Expected the data to be what was resolved in Loop::defer');
            $returnDeferred->resolve();
        });
        Loop::defer(function () use ($testDeferred) {
            $testDeferred->resolve('foobar');
        });

        return $returnDeferred->promise();
    }

    public function testThatWeHandleNotPromiseReturned(): \Generator
    {
        $testDeferred = new Deferred();
        $testData = new \stdClass();
        $testData->val = null;
        Loop::defer(function () use ($testData, $testDeferred) {
            $testData->val = true;
            $testDeferred->resolve();
        });

        yield $testDeferred->promise();

        $this->assertTrue($testData->val, 'Expected our test to run on loop to completion');
    }

    public function testExpectingAnExceptionThrown(): \Generator
    {
        $throwException = function () {
            return call(function () {
                yield new Delayed(100);
                throw new \Exception('threw the error');
            });
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('threw the error');

        yield $throwException();
    }

    public function testExpectingAnErrorThrown(): \Generator
    {
        $this->expectException(\Error::class);

        yield call(function () {
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
    public function testArgumentSupport(string $foo, int $bar, bool $baz)
    {
        $this->assertSame('foo', $foo);
        $this->assertSame(42, $bar);
        $this->assertTrue($baz);
    }

    public function testSetTimeout(): \Generator
    {
        $this->setTimeout(100);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected test to complete before 100ms time limit');

        yield new Delayed(200);
    }

    public function testSetMinimumRunTime(): \Generator
    {
        $this->setMinimumRuntime(100);
        $func = function () {
            yield new Delayed(75);
            return 'finished';
        };

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageRegExp("/Expected test to take at least 100ms but instead took (\d+)ms/");
        yield call($func);
    }

    public function testSetMinimumRunTimeWithWatchersOnly()
    {
        $this->setMinimumRuntime(100);
        Loop::delay(100, $this->createCallback(1));
    }

    public function testCreateCallback()
    {
        $mock = $this->createCallback(1, function (int $value): int {
            return $value + 1;
        });

        $this->assertSame(2, $mock(1));
    }
}
