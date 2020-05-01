<?php

namespace Amp\PHPUnit\Test;

use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use Amp\Success;

class AsyncTestCaseWithSetUpAndTearDownTest extends AsyncTestCase
{
    protected function setUpAsync(): Promise
    {
        if ($this->getName() === 'testFailingSetUpAsync') {
            $this->expectException(\Error::class);
            $this->expectExceptionMessage('setUpAsync() failed');

            return new Failure(new TestException);
        }

        return new Success;
    }

    protected function tearDownAsync(): Promise
    {
        if ($this->getName() === 'testFailingTearDownAsync') {
            $this->expectException(\Error::class);
            $this->expectExceptionMessage('tearDownAsync() failed');

            return new Failure(new TestException);
        }

        return new Success;
    }

    public function testExpectedException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        throw new \Exception('Test exception');
    }

    public function testFailingTearDownAsync()
    {
        // Expected exception set in tearDownAsync().
    }

    public function testFailingSetUpAsync()
    {
        // Expected exception set in setUpAsync().
    }
}
