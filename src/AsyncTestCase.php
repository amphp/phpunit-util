<?php declare(strict_types=1);

namespace Amp\PHPUnit;

use Amp\DeferredFuture;
use Amp\Future;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\TracingDriver;
use function Amp\async;
use function Amp\now;

abstract class AsyncTestCase extends PHPUnitTestCase
{
    private const RUNTIME_PRECISION = 2;

    private DeferredFuture $deferredFuture;

    private string $timeoutId;

    /** @var float Minimum runtime in seconds. */
    private float $minimumRuntime = 0;

    /** @var string Temporary storage for actual test name. */
    private string $realTestName;

    private bool $setUpInvoked = false;

    protected function setUp(): void
    {
        $this->setUpInvoked = true;
        $this->deferredFuture = new DeferredFuture();

        EventLoop::setErrorHandler(function (\Throwable $exception): void {
            if ($this->deferredFuture->isComplete()) {
                return;
            }

            $this->deferredFuture->error(new UnhandledException($exception));
        });
    }

    /** @internal */
    final protected function runAsyncTest(mixed ...$args): mixed
    {
        if (!$this->setUpInvoked) {
            self::fail(\sprintf(
                '%s::setUp() overrides %s::setUp() without calling the parent method',
                \str_replace("\0", '@', static::class), // replace NUL-byte in anonymous class name
                self::class
            ));
        }

        parent::setName($this->realTestName);

        $start = now();

        try {
            [, $returnValue] = Future\await([
                $this->deferredFuture->getFuture(),
                async(function () use ($args): mixed {
                    try {
                        $result = ([$this, $this->realTestName])(...$args);
                        if ($result instanceof Future) {
                            $result = $result->await();
                        }

                        // Force an extra tick of the event loop to ensure any uncaught exceptions are
                        // forwarded to the event loop handler before the test ends.
                        $deferred = new DeferredFuture();
                        EventLoop::defer(static fn () => $deferred->complete());
                        $deferred->getFuture()->await();

                        return $result;
                    } finally {
                        if (!$this->deferredFuture->isComplete()) {
                            $this->deferredFuture->complete();
                        }
                    }
                }),
            ]);
        } finally {
            if (isset($this->timeoutId)) {
                EventLoop::cancel($this->timeoutId);
            }

            \gc_collect_cycles(); // Throw from as many destructors as possible.
        }

        $end = now();

        if ($this->minimumRuntime > 0) {
            $actualRuntime = \round($end - $start, self::RUNTIME_PRECISION);
            $msg = 'Expected test to take at least %0.3fs but instead took %0.3fs';
            self::assertGreaterThanOrEqual(
                $this->minimumRuntime,
                $actualRuntime,
                \sprintf($msg, $this->minimumRuntime, $actualRuntime)
            );
        }

        return $returnValue;
    }

    final protected function runTest(): mixed
    {
        $this->realTestName = $this->name();
        parent::setName('runAsyncTest');
        return parent::runTest();
    }

    /**
     * Fails the test if it does not run for at least the given amount of time.
     *
     * @param float $seconds Required runtime in seconds.
     */
    final protected function setMinimumRuntime(float $seconds): void
    {
        if ($seconds <= 0) {
            throw new \Error('Minimum runtime must be greater than 0, got ' . $seconds);
        }

        $this->minimumRuntime = \round($seconds, self::RUNTIME_PRECISION);
    }

    /**
     * Fails the test (and stops the event loop) after the given timeout.
     *
     * @param float $seconds Timeout in seconds.
     */
    final protected function setTimeout(float $seconds): void
    {
        if (isset($this->timeoutId)) {
            EventLoop::cancel($this->timeoutId);
        }

        $this->timeoutId = EventLoop::delay($seconds, function () use ($seconds): void {
            EventLoop::setErrorHandler(null);

            $additionalInfo = '';

            $driver = EventLoop::getDriver();
            if ($driver instanceof TracingDriver) {
                $additionalInfo .= "\r\n\r\n" . $driver->dump();
            } else {
                $additionalInfo .= "\r\n\r\nSet REVOLT_DEBUG_TRACE_WATCHERS=true as environment variable to trace watchers keeping the loop running.";
            }

            if ($this->deferredFuture->isComplete()) {
                return;
            }

            try {
                $this->fail(\sprintf(
                    'Expected test to complete before %0.3fs time limit%s',
                    $seconds,
                    $additionalInfo
                ));
            } catch (AssertionFailedError $e) {
                $this->deferredFuture->error($e);
            }
        });

        EventLoop::unreference($this->timeoutId);
    }

    /**
     * @param int           $invocationCount Number of times the callback must be invoked or the test will fail.
     * @param callable|null $returnCallback Callable providing a return value for the callback.
     * @param array         $expectArgs Arguments expected to be passed to the callback.
     */
    final protected function createCallback(
        int $invocationCount,
        ?callable $returnCallback = null,
        array $expectArgs = [],
    ): \Closure {
        $mock = $this->createMock(CallbackStub::class);
        $invocationMocker = $mock->expects(self::exactly($invocationCount))
            ->method('__invoke');

        if ($returnCallback) {
            $invocationMocker->willReturnCallback($returnCallback);
        }

        if ($expectArgs) {
            $invocationMocker->with(...$expectArgs);
        }

        return \Closure::fromCallable($mock);
    }
}
