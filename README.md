# amphp/phpunit-util

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
`amphp/phpunit-util` is a small helper package to ease testing with [PHPUnit](https://github.com/sebastianbergmann/phpunit/).

![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require --dev amphp/phpunit-util
```

The package requires PHP 8.1 or later and PHPUnit 9 or 12+ (PHPUnit versions 10 and 11 are not supported). The methods provided by `Amp\PHPUnit\AsyncTestCase` by this library do not differ between supported PHPUnit versions. However, because `AsyncTestCase` extends `PHPUnit\Framework\TestCase`, there will be incompatibilities between the methods provided by different major versions of PHPUnit.

#### PHP and PHPUnit versions supported

| amphp/phpunit-util | PHP  | PHPUnit |
|--------------------|------|---------|
| 3.0                | 8.1+ | 9.0     |
| 3.1                | 8.3+ | 12.0+   |

## Usage

```php
<?php

namespace Foo;

use Amp\ByteStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;

class BarTest extends AsyncTestCase
{
    // Each test case is executed as a coroutine (within a separate fiber) and checked to run to completion
    public function test(): void
    {
        $socket = Socket\connect('tcp://localhost:12345');
        $socket->write('foobar');

        $this->assertSame('foobar', ByteStream\buffer($socket));
    }
}
```
