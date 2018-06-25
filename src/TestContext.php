<?php

namespace Amp\PHPUnit;

use Amp\Deferred;
use Amp\Promise;
use Throwable;

/**
 * A test helper meant to facilitate the returning of Promises to tests in an AsyncTestCase and to
 * ensure that a given number of async tasks are run by requiring a number of checkpoint() method
 * calls to be invoked during the test run.
 */
final class TestContext {

    private $deferred;

    private $expectedCheckpoints;

    private $actualCheckpoints = 0;

    public function __construct() {
        $this->deferred = new Deferred();
    }

    public function promise() : Promise {
        return $this->deferred->promise();
    }

    public function resolve($value = null) {
        $this->deferred->resolve($value);
    }

    public function fail(Throwable $throwable) {
        $this->deferred->fail($throwable);
    }

    public function requireCheckpoints(int $checkpoints) {
        $this->expectedCheckpoints = $checkpoints;
    }

    public function checkpoint() {
        if (++$this->actualCheckpoints === $this->expectedCheckpoints) {
            $this->deferred->resolve();
        }
    }

}