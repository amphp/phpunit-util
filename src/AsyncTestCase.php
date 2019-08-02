<?php

namespace Amp\PHPUnit;

use Amp\Loop;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use function Amp\call;

/**
 * A PHPUnit TestCase intended to help facilitate writing async tests by running each test as coroutine with Amp's
 * event loop ensuring that the test runs until completion based on your test returning either a Promise or Generator.
 */
abstract class AsyncTestCase extends PHPUnitTestCase
{
    use Internal\AsyncTestSetNameTrait;
    use Internal\AsyncTestSetUpTrait;

    const RUNTIME_PRECISION = 2;

    /** @var string|null Timeout watcher ID. */
    private $timeoutId;

    /** @var int Minimum runtime in milliseconds. */
    private $minimumRuntime = 0;

    /** @var string Temporary storage for actual test name. */
    private $realTestName;

    /** @var bool */
    private $setUpInvoked = false;

    final protected function runTest()
    {
        parent::setName('runAsyncTest');
        return parent::runTest();
    }

    /** @internal */
    final public function runAsyncTest(...$args)
    {
        parent::setName($this->realTestName);

        if (!$this->setUpInvoked) {
            $this->fail(\sprintf(
                '%s::setUp() overrides %s::setUp() without calling the parent method',
                \str_replace("\0", '@', \get_class($this)), // replace NUL-byte in anonymous class name
                self::class
            ));
        }

        $returnValue = null;

        $start = \microtime(true);

        Loop::run(function () use (&$returnValue, &$exception, $args) {
            try {
                $returnValue = yield call([$this, $this->realTestName], ...$args);
            } catch (\Throwable $exception) {
                // Also catches exception from potential nested loop.
                // Exception is rethrown after Loop::run().
            } finally {
                if (isset($this->timeoutId)) {
                    Loop::cancel($this->timeoutId);
                }
            }
        });

        if (isset($exception)) {
            throw $exception;
        }

        $actualRuntime = (int) (\round(\microtime(true) - $start, self::RUNTIME_PRECISION) * 1000);
        if ($this->minimumRuntime > $actualRuntime) {
            $msg = 'Expected test to take at least %dms but instead took %dms';
            $this->fail(\sprintf($msg, $this->minimumRuntime, $actualRuntime));
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
