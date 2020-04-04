<?php

namespace Amp\PHPUnit;

use Amp\Coroutine;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use React\Promise\PromiseInterface as ReactPromise;

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

    /** @var \Generator|null */
    private $generator;

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

        $invoked = false;
        $returnValue = null;

        $start = \microtime(true);

        Loop::run(function () use (&$returnValue, &$exception, &$invoked, $args) {
            $promise = $this->call([$this, $this->realTestName], ...$args);
            $promise->onResolve(function ($error, $value) use (&$invoked, &$exception, &$returnValue) {
                $invoked = true;
                $exception = $error;
                $returnValue = $value;
            });
        });

        if (isset($this->timeoutId)) {
            Loop::cancel($this->timeoutId);
        }

        $this->generator = null;

        if (isset($exception)) {
            throw $exception;
        }

        if (!$invoked) {
            $this->fail('Loop stopped without resolving promise or coroutine returned from test method');
        }

        if ($this->minimumRuntime > 0) {
            $actualRuntime = (int) (\round(\microtime(true) - $start, self::RUNTIME_PRECISION) * 1000);
            $msg = 'Expected test to take at least %dms but instead took %dms';
            $this->assertGreaterThanOrEqual(
                $this->minimumRuntime,
                $actualRuntime,
                \sprintf($msg, $this->minimumRuntime, $actualRuntime)
            );
        }

        return $returnValue;
    }

    final protected function runTest()
    {
        parent::setName('runAsyncTest');
        return parent::runTest();
    }

    /**
     * Fails the test if the loop does not run for at least the given amount of time.
     *
     * @param int $runtime Required run time in milliseconds.
     */
    final protected function setMinimumRuntime(int $runtime)
    {
        if ($runtime < 1) {
            throw new \Error('Minimum runtime must be at least 1ms');
        }

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
            Loop::setErrorHandler(null);

            $additionalInfo = '';

            if ($this->generator && $this->generator->valid()) {
                $reflGen = new \ReflectionGenerator($this->generator);
                $exeGen = $reflGen->getExecutingGenerator();
                if ($isSubgenerator = ($exeGen !== $this->generator)) {
                    $reflGen = new \ReflectionGenerator($exeGen);
                }

                $additionalInfo .= \sprintf(
                    "\r\n\r\nTimeout reached on line %s in %s",
                    $reflGen->getExecutingLine(),
                    $reflGen->getExecutingFile()
                );
            }

            $loop = Loop::get();
            if ($loop instanceof Loop\TracingDriver) {
                $additionalInfo .= "\r\n\r\n" . $loop->dump();
            } elseif (\class_exists(Loop\TracingDriver::class)) {
                $additionalInfo .= "\r\n\r\nSet AMP_DEBUG_TRACE_WATCHERS=true as environment variable to trace watchers keeping the loop running.";
            } else {
                $additionalInfo .= "\r\n\r\nInstall amphp/amp@^2.3 and set AMP_DEBUG_TRACE_WATCHERS=true as environment variable to trace watchers keeping the loop running. ";
            }

            $this->fail('Expected test to complete before ' . $timeout . 'ms time limit' . $additionalInfo);
        });

        Loop::unreference($this->timeoutId);
    }

    /**
     * @param int           $invocationCount Number of times the callback must be invoked or the test will fail.
     * @param callable|null $returnCallback Callable providing a return value for the callback.
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

    /**
     * Specialized Amp\call that stores the generator if present for debugging purposes.
     *
     * @param callable $callback
     * @param mixed    ...$args
     *
     * @return Promise
     */
    private function call(callable $callback, ...$args): Promise
    {
        $this->generator = null;

        try {
            $result = $callback(...$args);
        } catch (\Throwable $exception) {
            return new Failure($exception);
        }

        if ($result instanceof \Generator) {
            $this->generator = $result;

            return new Coroutine($result);
        }

        if ($result instanceof Promise) {
            return $result;
        }

        if ($result instanceof ReactPromise) {
            return Promise\adapt($result);
        }

        return new Success($result);
    }
}
