<?php declare(strict_types=1);

/**
 * A PHPUnit TestCase intended to help facilitate writing async tests by running each test on the amphp Loop and
 * ensuring that the test runs until completion based on your test returning either a Promise or a Generator.
 */

namespace Amp\PHPUnit;

use Amp\Loop;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use function Amp\call;

abstract class AsyncTestCase extends PHPUnitTestCase {

    public function runTest() {
        Loop::run(function() {
            $testTimeout = $this->getTestTimeout();
            $watcherId = Loop::delay($testTimeout, function() use($testTimeout) {
                Loop::stop();
                $this->fail('Expected test to complete before ' . $testTimeout . 'ms time limit');
            });
            yield call(function() {
                return parent::runTest();
            });
            Loop::cancel($watcherId);
        });
    }

    protected function getTestTimeout() : int {
        return 1500;
    }

}