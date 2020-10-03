<?php

namespace Amp\PHPUnit;

use Amp\Deferred;
use Amp\Loop;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use function Amp\await;
use function Amp\async;
use function Amp\call;
use function Amp\sleep;

abstract class AsyncTestCase extends PHPUnitTestCase
{
    const RUNTIME_PRECISION = 2;

    private Deferred $timeoutDeferred;

    private string $timeoutId;

    /** @var int Minimum runtime in milliseconds. */
    private int $minimumRuntime = 0;

    /** @var string Temporary storage for actual test name. */
    private string $realTestName;

    private bool $setUpInvoked = false;

    private bool $ignoreWatchers = false;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->timeoutDeferred = new Deferred;
    }

    protected function setUp(): void
    {
        $this->setUpInvoked = true;
        Loop::get()->clear(); // remove all watchers from the event loop
        \gc_collect_cycles(); // extensions using an event loop may otherwise leak the file descriptors to the loop
    }

    /**
     * Called before each test. Similar to {@see TestCase::setUp()}, except the method may return a promise or
     * coroutine (@see \Amp\call()} that will be awaited before executing the test.
     */
    protected function setUpAsync()
    {
        // Empty method to be overloaded by inheriting class if desired.
    }

    /**
     * Called after each test. Similar to {@see TestCase::tearDown()}, except the method may return a promise or
     * coroutine (@see \Amp\call()} that will be awaited before executing the next test.
     */
    protected function tearDownAsync()
    {
        // Empty method to be overloaded by inheriting class if desired.
    }

    /**
     * @codeCoverageIgnore Invoked before code coverage data is being collected.
     */
    final public function setName(string $name): void
    {
        parent::setName($name);
        $this->realTestName = $name;
    }

    /** @internal */
    final protected function runAsyncTest(mixed ...$args): mixed
    {
        if (!$this->setUpInvoked) {
            $this->fail(\sprintf(
                '%s::setUp() overrides %s::setUp() without calling the parent method',
                \str_replace("\0", '@', \get_class($this)), // replace NUL-byte in anonymous class name
                self::class
            ));
        }

        parent::setName($this->realTestName);

        try {
            await(call(\Closure::fromCallable([$this, 'setUpAsync'])));
        } catch (\Throwable $exception) {
            throw new \Error(\sprintf(
                '%s::setUpAsync() failed: %s',
                \str_replace("\0", '@', \get_class($this)), // replace NUL-byte in anonymous class name
                $exception->getMessage()
            ), 0, $exception);
        }

        $start = \microtime(true);

        try {
            [$returnValue] = await([
                async(function () use ($args): mixed {
                    try {
                        return await(call([$this, $this->realTestName], ...$args));
                    } finally {
                        if (!$this->timeoutDeferred->isResolved()) {
                            $this->timeoutDeferred->resolve();
                        }
                    }
                }),
                $this->timeoutDeferred->promise()
            ]);
        } finally {
            if (isset($this->timeoutId)) {
                Loop::cancel($this->timeoutId);
            }

            try {
                await(call(\Closure::fromCallable([$this, 'tearDownAsync'])));
            } catch (\Throwable $exception) {
                throw new \Error(\sprintf(
                    '%s::tearDownAsync() failed: %s',
                    \str_replace("\0", '@', \get_class($this)), // replace NUL-byte in anonymous class name
                    $exception->getMessage()
                ), 0, $exception);
            } finally {
                $this->checkLoop();
            }
        }

        $end = \microtime(true);

        if ($this->minimumRuntime > 0) {
            $actualRuntime = (int) (\round($end - $start, self::RUNTIME_PRECISION) * 1000);
            $msg = 'Expected test to take at least %dms but instead took %dms';
            $this->assertGreaterThanOrEqual(
                $this->minimumRuntime,
                $actualRuntime,
                \sprintf($msg, $this->minimumRuntime, $actualRuntime)
            );
        }

        return $returnValue;
    }

    final protected function runTest(): mixed
    {
        parent::setName('runAsyncTest');
        return parent::runTest();
    }

    /**
     * Fails the test if the loop does not run for at least the given amount of time.
     *
     * @param int $runtime Required run time in milliseconds.
     */
    final protected function setMinimumRuntime(int $runtime): void
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
    final protected function setTimeout(int $timeout): void
    {
        $this->timeoutId = Loop::delay($timeout, function () use ($timeout): void {
            Loop::setErrorHandler(null);

            $additionalInfo = '';

            $loop = Loop::get();
            if ($loop instanceof Loop\TracingDriver) {
                $additionalInfo .= "\r\n\r\n" . $loop->dump();
            } elseif (\class_exists(Loop\TracingDriver::class)) {
                $additionalInfo .= "\r\n\r\nSet AMP_DEBUG_TRACE_WATCHERS=true as environment variable to trace watchers keeping the loop running.";
            } else {
                $additionalInfo .= "\r\n\r\nInstall amphp/amp@^2.3 and set AMP_DEBUG_TRACE_WATCHERS=true as environment variable to trace watchers keeping the loop running. ";
            }

            try {
                $this->fail('Expected test to complete before ' . $timeout . 'ms time limit' . $additionalInfo);
            } catch (AssertionFailedError $e) {
                $this->timeoutDeferred->fail($e);
            }
        });
        Loop::unreference($this->timeoutId);
    }

    /**
     * Test will not fail if the event loop contains active watchers when the test ends.
     */
    final protected function ignoreLoopWatchers(): void
    {
        $this->ignoreWatchers = true;
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

    private function checkLoop(): void
    {
        $info = Loop::getInfo();
        $errors = [];

        while (true) {
            try {
                sleep(0);
                break;
            } catch (\Throwable $e) {
                $errors[] = (string) $e;
            }
        }

        try {
            if ($errors && !$this->hasFailed()) {
                $this->fail(\implode("\n", $errors));
            }

            if (!isset($this->timeoutId)
                && !$this->ignoreWatchers
                && !$this->hasFailed()
                && $info['enabled_watchers']['referenced'] + $info['enabled_watchers']['unreferenced'] > 0
            ) {
                $this->fail(
                    "Found enabled watchers at end of test '{$this->getName()}': " . \json_encode($info, \JSON_PRETTY_PRINT),
                );
            }
        } finally {
            Loop::get()->clear();
        }
    }
}
