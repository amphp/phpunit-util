<?php declare(strict_types=1);

namespace Amp\PHPUnit;

/**
 * Generic exception to be used in cases where an exception should be thrown and caught, but if the exception is not
 * thrown, the test should fail using $this->fail(...).
 *
 * try {
 *     functionCallThatShouldThrow();
 *     $this->fail("Expected functionCallThatShouldThrow() to throw.");
 * } catch (TestException $e) {
 *     $this->assertSame($expectedExceptionInstance, $e);
 * }
 */
class TestException extends \Exception
{
    // nothing
}
