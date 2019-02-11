<?php

namespace Amp\PHPUnit\Test;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\call;

class AsyncTestCaseTest extends AsyncTestCase
{

    public function testThatMethodRunsInLoopContext()
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

    public function testThatWeHandleNotPromiseReturned()
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

    public function testExpectingAnExceptionThrown()
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

    public function argumentSupportProvider()
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

    public function testSetMinimumRunTime() {
        $this->setMinimumRuntime(100);
        $func = function() {
            yield new Delayed(110);
            return 'finished';
        };

        $finished = yield call($func);

        $this->assertSame('finished', $finished);
    }

}
