<?php declare(strict_types=1);

/**
 * A PHPUnit TestCase intended to help facilitate writing async tests by running each test on the amphp Loop and
 * ensuring that the test runs until completion based on your test returning either a Promise or a Generator.
 */

namespace Amp\PHPUnit;

use Amp\Coroutine;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Generator;

abstract class AsyncTestCase extends PHPUnitTestCase {

    public function runTest() {
        Loop::run(function() {
            $testTimeout = $this->getTestTimeout();
            $watcherId = Loop::delay($testTimeout, function() use($testTimeout) {
                Loop::stop();
                $this->fail('Expected test to complete before ' . $testTimeout . 'ms time limit');
            });
            $promise = parent::runTest();
            if ($promise instanceof Generator) {
                $promise = new Coroutine($promise);
            } elseif (!$promise instanceof Promise) {
                $promise = new Success($promise);
            }
            yield $promise;
            Loop::cancel($watcherId);
        });
    }

    protected function getTestTimeout() : int {
        return 1500;
    }

}