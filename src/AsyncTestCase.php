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

    private $timeoutId;

    public function runTest() {
        $returnValue = wait(call(function() {
            return parent::runTest();
        }));
        if (isset($this->timeoutId)) {
            Loop::cancel($this->timeoutId);
        }

        return $returnValue;
    }

    protected function timeout(int $timeout) {
        $this->timeoutId = Loop::delay($timeout, function() use($timeout) {
            Loop::stop();
            $this->fail('Expected test to complete before ' . $timeout . 'ms time limit');
        });
    }

}