# ReactPHP S3 Filesystem Adapter

[![Build Status](https://travis-ci.org/WyriHaximus/reactphp-filesystem-s3.png)](https://travis-ci.org/WyriHaximus/reactphp-filesystem-s3)
[![Latest Stable Version](https://poser.pugx.org/WyriHaximus/react-filesystem-s3/v/stable.png)](https://packagist.org/packages/WyriHaximus/react-filesystem-s3)
[![Total Downloads](https://poser.pugx.org/WyriHaximus/react-filesystem-s3/downloads.png)](https://packagist.org/packages/WyriHaximus/react-filesystem-s3)
[![License](https://poser.pugx.org/wyrihaximus/react-filesystem-s3/license.png)](https://packagist.org/packages/wyrihaximus/react-filesystem-s3)

AWS S3 adapter for [react/filesystem](https://github.com/reactphp/filesystem)

### Installation ###

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `~`.

```
composer require wyrihaximus/react-filesystem-s3 
```

### How to use ###

Before we start using the adapter we need to set it up, it requires an event loop and an options array which is passed directly into a new `Aws\Sdk` object. The `Aws\Sdk` object creates a new S3 client based on those options. (If none provided the adapter will create a new handler stack. This behavior can be overwritten by specifying one in `$options['http_handler']`.)

```php
$loop = Factory::create(); // The required event loop
$adapter = new S3Adapter(
    $loop,
    [
        'credentials' => [ // The credentials to use with this adapter
            'key' => 'KEY',
            'secret' => 'SECRET',
        ],
        'region' => 'REGION', // THe region your bucket resides
        'version' => 'latest',
    ],
    'BUCKET' // Your buckets name
);
```

Next all you have to do is create a new `Filesystem` instance with the new adapter:

```php
$filesystem = Filesystem::createFromAdapter($adapter);
```

This filesystem can then be used to, for example, list the contents in the root of your S3 bucket:

```php
$filesystem->dir('')->ls()->then(function (\SplObjectStorage $ls) {
    foreach ($ls as $node) {
        echo $node->getPath(), PHP_EOL;
    }
    echo 'Found ', $ls->count(), ' nodes', PHP_EOL;
}, function ($e) {
    echo $e->getMessage(), PHP_EOL;
});
```

See the [examples](https://github.com/WyriHaximus/reactphp-filesystem-s3/tree/master/examples) directory for complete examples.

## Contributing ##

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License ##

Copyright 2015 [Cees-Jan Kiewiet](http://wyrihaximus.net/)

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
