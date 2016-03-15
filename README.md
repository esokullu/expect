# Expect

***Note: This package is unstable and might break.**

This package is a pure PHP alternative to [expect](https://en.wikipedia.org/wiki/Expect), the Unix tool.  This package doesn't depend on the [PECL package](https://pecl.php.net/package/expect) either.

Expect lets you script interactions with interactive terminal applications.

## Why?

I wrote this because I wrote an interactive CLI program and needed to write automated tests for it.  Apparently people use the real expect for scripting ftp and telnet workflows, so I guess you could use it for that too.

## Installation

This package isn't in composer right now since it's a WIP and really rough at the moment.  You need to add the repository url to your composer.json use it for now.

```
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/yuloh/expect.git"
    },
],
require": {
    "yuloh/expect": "dev-master"
}
```

## API

Note: all methods return $this for fluent chaining.

```php
Expect::spawn(string $cmd, string $cwd)
```

Spawn a new instance of expect for the given command. You can optionally specify a working directory.

```php
Expect::expect(string $output)
```

Expect the given text to show up on stdout.  Expect will block and keep checking the stdout buffer until your expectation shows up.

You can use [shell wildcards](http://tldp.org/LDP/GNU-Linux-Tools-Summary/html/x11655.htm) to match parts of output.

**There isn't a timeout right now so it may hang indefinitely!**

```php
Expect::send(string $msg)
```

Send the given text on stdin.  A newline is added to each string to simulate pressing enter.  If you want to just send enter you can do `send(PHP_EOL)`

```php
Expect::debug()
```

Enable debug mode.  All activity is logged to the console, so you can see what is happening.

```php
Expect::unbuffer()
```

Attempts to prevent buffering of output using [script](https://en.wikipedia.org/wiki/Script_(Unix)).  Some programs like Composer buffer the output so Expect won't work unless you unbuffer the output.

This method will throw an exception if you aren't using Linux, FreeBSD, or OSX, or if you are missing the program `script`.

A side effect of using script is all of your answers show up too, so you will probably need to add a wildcard before your expectation.

This is pretty hacky; hopefully someone can come up with a better solution.

## Examples

### Simple Example

This example opens `cat` without any arguments, which will simply echo back everything you type to it.

```php
Yuloh\Expect\Expect::spawn('cat')
    ->send('hi')
    ->expect('hi')
    ->send('yo')
    ->expect('yo')
    ->run();
```

### Npm init

This example demonstrates creating a new package.json with npm.  Globs are used to match the expectations so we don't need to type them exactly.

```php
Yuloh\Expect\Expect::spawn('npm init')
    ->expect('*name:*')
    ->send('package')
    ->expect('version*')
    ->send('1.0.0')
    ->expect('description*')
    ->send('awesome')
    ->expect('entry point*')
    ->send('index.js')
    ->expect('test command*')
    ->send('test')
    ->expect('git repository*')
    ->send('yuloh/expect')
    ->expect('keywords*')
    ->send('awesome')
    ->expect('author*')
    ->send('matt')
    ->expect('license*')
    ->send('ISC')
    ->expect('*')
    ->send('yes')
    ->run();
```

### Composer init

This example demonstrates creating a new package with composer.  Since composer does output buffering, we need to disable it using the `unbuffer` method.

```php

Yuloh\Expect\Expect::spawn('composer init')
    ->unbuffer()
    ->expect('*Package name*')
    ->send('yuloh/expect')
    ->expect('*Description*')
    ->send('awesome scripting for cli tasks.')
    ->expect('*Author*')
    ->send('n')
    ->expect('*Minimum Stability*')
    ->send('dev')
    ->expect('*Package Type*')
    ->send(PHP_EOL)
    ->expect('*License*')
    ->send('MIT')
    ->expect('*Would you like to define your dependencies (require) interactively*')
    ->send('no')
    ->expect('*Would you like to define your dev dependencies (require-dev) interactively*')
    ->send('no')
    ->expect('*Do you confirm generation*')
    ->send('yes')
    ->run();
```
