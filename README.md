# amphp/phpunit-util

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
`amphp/phpunit-util` is a small helper package to ease testing with PHPUnit.

![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require --dev amphp/phpunit-util
```

The package requires PHP 8.1 or later.

## Usage

```php
<?php

namespace Foo;

use Amp\ByteStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;

class BarTest extends AsyncTestCase
{
    // Each test case is executed as a coroutine and checked to run to completion
    public function test(): void
    {
        $socket = Socket\connect('tcp://localhost:12345');
        $socket->write('foobar');

        $this->assertSame('foobar', ByteStream\buffer($socket));
    }
}
```
