<?php

namespace Amp\PHPUnit;

use Amp\Loop;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use function Amp\call;
use function Amp\Promise\wait;

/**
 * A PHPUnit TestCase intended to help facilitate writing async tests by running each test on the amphp Loop and
 * ensuring that the test runs until completion based on your test returning either a Promise or a Generator.
 */
abstract class AsyncTestCase extends PHPUnitTestCase {

    public function runTest() {
        $testTimeout = $this->getTestTimeout();
        $watcherId = Loop::delay($testTimeout, function() use($testTimeout) {
            Loop::stop();
            $this->fail('Expected test to complete before ' . $testTimeout . 'ms time limit');
        });

        $returnValue = wait(call(function() {
            return parent::runTest();
        }));
        Loop::cancel($watcherId);
        return $returnValue;
    }

    protected function getTestTimeout() : int {
        return 1500;
    }

}