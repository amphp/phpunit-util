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
    private $realTestName;

    public function setName($name) {
        $this->realTestName = $name;
        parent::setName('asyncRunTest');
    }

    final public function asyncRunTest(...$args) {
        parent::setName($this->realTestName);
        $returnValue = null;
        Loop::run(function() use(&$returnValue, $args) {
            try {
                $returnValue = yield call([$this, $this->realTestName], ...$args);
            } finally {
                if (isset($this->timeoutId)) {
                    Loop::cancel($this->timeoutId);
                }
            }
        });
        return $returnValue;
    }

    protected function timeout(int $timeout) {
        $this->timeoutId = Loop::delay($timeout, function() use($timeout) {
            Loop::stop();
            $this->fail('Expected test to complete before ' . $timeout . 'ms time limit');
        });
        Loop::unreference($this->timeoutId);
    }

}