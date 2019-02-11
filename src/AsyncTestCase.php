<?php

namespace Amp\PHPUnit;

use Amp\Loop;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use function Amp\call;

/**
 * A PHPUnit TestCase intended to help facilitate writing async tests by running each test on the amphp Loop and
 * ensuring that the test runs until completion based on your test returning either a Promise or a Generator.
 */
abstract class AsyncTestCase extends PHPUnitTestCase
{

    private const RUNTIME_PRECISION = 2;

    private $timeoutId;

    private $realTestName;

    private $minimumRuntime;

    public function setName($name)
    {
        parent::setName($name);
        $this->realTestName = $name;
    }

    protected function runTest()
    {
        parent::setName('runAsyncTest');
        return parent::runTest();
    }

    final public function runAsyncTest(...$args)
    {
        parent::setName($this->realTestName);

        $returnValue = null;

        try {
            Loop::run(function () use (&$returnValue, $args) {
                try {
                    $start = \microtime(true);
                    $returnValue = yield call([$this, $this->realTestName], ...$args);
                    $actualRuntime = (int) (\round(\microtime(true) - $start, self::RUNTIME_PRECISION) * 1000);
                    if ($this->minimumRuntime) {
                        if ($this->minimumRuntime > $actualRuntime) {
                            $msg = 'Expected test to take at least %dms but instead took %dms';
                            $this->fail(sprintf($msg, $this->minimumRuntime, $actualRuntime));
                        }
                    }
                } finally {
                    if (isset($this->timeoutId)) {
                        Loop::cancel($this->timeoutId);
                    }
                }
            });
        } finally {
            Loop::set((new Loop\DriverFactory)->create());
            \gc_collect_cycles(); // extensions using an event loop may otherwise leak the file descriptors to the loop
        }

        return $returnValue;
    }

    final protected function setMinimumRuntime(int $runtime)
    {
        $this->minimumRuntime = $runtime;
    }

    final protected function setTimeout(int $timeout)
    {
        $this->timeoutId = Loop::delay($timeout, function () use ($timeout) {
            Loop::stop();
            $this->fail('Expected test to complete before ' . $timeout . 'ms time limit');
        });

        Loop::unreference($this->timeoutId);
    }
}
