<?php

namespace Amp\PHPUnit\Test;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use PHPUnit\Framework\AssertionFailedError;
use function Amp\call;

class AsyncTestCaseWithSetUpAndTearDownTest extends AsyncTestCase
{
    protected $setupCalled = false;
    protected $tearDownCalled = false;

    protected function setUpAsync(): Promise
    {
        $this->setupCalled = true;

        return new Success();
    }

    protected function tearDownAsync(): Promise
    {
        $this->tearDownCalled = true;

        return new Success();
    }

    public function testThatSetupAndTearDownIsInvoked(): Promise
    {
        $this->assertTrue($this->setupCalled);

        $promise = new Delayed(100);
        $promise->onResolve(function () {
            $this->assertTrue($this->tearDownCalled);
        });

        return new Success();
    }
}
