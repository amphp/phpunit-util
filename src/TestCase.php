<?php

namespace Amp\PHPUnit;

/**
 * Abstract test class with methods for creating callbacks and asserting runtimes.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {
    const RUNTIME_PRECISION = 2; // Number of decimals to use in runtime calculations/comparisons.

    /**
     * Creates a callback that must be called $count times or the test will fail.
     *
     * @param int $count Number of times the callback should be called.
     *
     * @return callable|\PHPUnit_Framework_MockObject_MockObject Object that is callable and expects to be called the
     *     given number of times.
     */
    public function createCallback(int $count): callable {
        $mock = $this->createMock(CallbackStub::class);

        $mock->expects($this->exactly($count))
            ->method('__invoke');

        return $mock;
    }

    /**
     * Asserts that the given callback takes no more than $maxRunTime to run.
     *
     * @param callable     $callback
     * @param int          $maxRunTime
     * @param mixed[]|null $args       Function arguments.
     */
    public function assertRunTimeLessThan(callable $callback, int $maxRunTime, array $args = null) {
        $this->assertRunTimeBetween($callback, 0, $maxRunTime, $args);
    }

    /**
     * Asserts that the given callback takes more than $minRunTime to run.
     *
     * @param callable     $callback
     * @param int          $minRunTime
     * @param mixed[]|null $args       Function arguments.
     */
    public function assertRunTimeGreaterThan(callable $callback, int $minRunTime, array $args = null) {
        $this->assertRunTimeBetween($callback, $minRunTime, 0, $args);
    }

    /**
     * Asserts that the given callback takes between $minRunTime and $maxRunTime to execute.
     * Rounds to the nearest 100 ms.
     *
     * @param callable     $callback
     * @param int          $minRunTime
     * @param int          $maxRunTime
     * @param mixed[]|null $args       Function arguments.
     */
    public function assertRunTimeBetween(callable $callback, int $minRunTime, int $maxRunTime, array $args = null) {
        $start = \microtime(true);

        \call_user_func_array($callback, $args ?: []);

        $runTime = \round(\microtime(true) - $start, self::RUNTIME_PRECISION) * 1000;

        if (0 < $maxRunTime) {
            $this->assertLessThanOrEqual(
                $maxRunTime,
                $runTime,
                \sprintf('The run time of %dms was greater than the max run time of %dms.', $runTime, $maxRunTime)
            );
        }

        if (0 < $minRunTime) {
            $this->assertGreaterThanOrEqual(
                $minRunTime,
                $runTime,
                \sprintf('The run time of %dms was less than the min run time of %dms.', $runTime, $minRunTime)
            );
        }
    }

    /**
     * Runs the given callback in a separate fork.
     *
     * @param callable $function
     *
     * @return int
     */
    final protected function doInFork(callable $function) {
        switch ($pid = \pcntl_fork()) {
            case -1:
                $this->fail('Failed to fork process.');
                break;
            case 0:
                $status = (int) $function();
                exit($status);
            default:
                if (\pcntl_waitpid($pid, $status) === -1) {
                    $this->fail('Failed to fork process.');
                }
                return $status;
        }
    }
}
