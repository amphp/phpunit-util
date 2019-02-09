<?php

namespace Amp\PHPUnit\Test;

use function Amp\call;
use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;

class AsyncTestCaseTest extends AsyncTestCase {

    public function testThatMethodRunsInLoopContext() {
        $returnDeferred = new Deferred(); // make sure our test runs to completion
        $testDeferred = new Deferred(); // used by our defer callback to ensure we're running on the Loop
        $testDeferred->promise()->onResolve(function($err = null, $data = null) use($returnDeferred) {
            $this->assertEquals('foobar', $data, 'Expected the data to be what was resolved in Loop::defer');
            $returnDeferred->resolve();
        });
        Loop::defer(function() use($testDeferred) {
            $testDeferred->resolve('fooba');
        });

        return $returnDeferred->promise();
    }

    public function testThatWeHandleNotPromiseReturned() {
        $testDeferred = new Deferred();
        $testData = new \stdClass();
        $testData->val = null;
        Loop::defer(function() use($testData, $testDeferred) {
            $testData->val = true;
            $testDeferred->resolve();
        });

        yield $testDeferred->promise();

        $this->assertTrue($testData->val, 'Expected our test to run on loop to completion');
    }

    public function testExpectingAnExceptionThrown() {
        $throwException = function() {
            return call(function() {
                yield new Delayed(100);
                throw new \Exception('threw the error');
            });
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('threw the eror');

        yield $throwException();
    }

}