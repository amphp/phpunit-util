<?php

namespace Amp\PHPUnit;

use Amp\Loop;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use function Amp\call;

/**
 * A PHPUnit TestCase intended to help facilitate writing async tests by running each test on the amphp Loop and
 * ensuring that the test runs until completion based on your test returning either a Promise or a Generator.
 */
abstract class AsyncTestCase extends PHPUnitTestCase
{
    const RUNTIME_PRECISION = 2;

    /** @var string|null Timeout watcher ID. */
    private $timeoutId;

    /** @var string Temporary storage for actual test name. */
    private $realTestName;

    /** @var int Minimum runtime in milliseconds. */
    private $minimumRuntime = 0;

    /**
     * @codeCoverageIgnore Invoked before code coverage data is being collected.
     */
    final public function setName($name)
    {
        parent::setName($name);
        $this->realTestName = $name;
    }

    final protected function runTest()
    {
        parent::setName('runAsyncTest');
        return parent::runTest();
    }

    /** @internal */
    final public function runAsyncTest(...$args)
    {
        parent::setName($this->realTestName);

        $returnValue = null;

        try {
            $start = \microtime(true);

            Loop::run(function () use (&$returnValue, $args) {
                try {
                    $returnValue = yield call([$this, $this->realTestName], ...$args);
                } finally {
                    if (isset($this->timeoutId)) {
                        Loop::cancel($this->timeoutId);
                    }
                }
            });

            $actualRuntime = (int) (\round(\microtime(true) - $start, self::RUNTIME_PRECISION) * 1000);
            if ($this->minimumRuntime > $actualRuntime) {
                $msg = 'Expected test to take at least %dms but instead took %dms';
                $this->fail(\sprintf($msg, $this->minimumRuntime, $actualRuntime));
            }
        } finally {
            Loop::set((new Loop\DriverFactory)->create());
            \gc_collect_cycles(); // extensions using an event loop may otherwise leak the file descriptors to the loop
        }

        return $returnValue;
    }

    /**
     * Fails the test if the loop does not run for at least the given amount of time.
     *
     * @param int $runtime Required run time in milliseconds.
     */
    final protected function setMinimumRuntime(int $runtime)
    {
        $this->minimumRuntime = $runtime;
    }

    /**
     * Fails the test (and stops the loop) after the given timeout.
     *
     * @param int $timeout Timeout in milliseconds.
     */
    final protected function setTimeout(int $timeout)
    {
        $this->timeoutId = Loop::delay($timeout, function () use ($timeout) {
            Loop::stop();
            $this->fail('Expected test to complete before ' . $timeout . 'ms time limit');
        });

        Loop::unreference($this->timeoutId);
    }

    /**
     * @param int           $invocationCount Number of times the callback must be invoked or the test will fail.
     * @param callable|null $returnCallback  Callable providing a return value for the callback.
     *
     * @return callable|MockObject Mock object having only an __invoke method.
     */
    final protected function createCallback(int $invocationCount, callable $returnCallback = null): callable
    {
        $mock = $this->createMock(CallbackStub::class);
        $invocationMocker = $mock->expects($this->exactly($invocationCount))
            ->method('__invoke');

        if ($returnCallback) {
            $invocationMocker->willReturnCallback($returnCallback);
        }

        return $mock;
    }
}
