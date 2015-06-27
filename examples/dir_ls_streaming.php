<?php

use React\EventLoop\Factory;
use React\Filesystem\Filesystem;
use React\Filesystem\Node\NodeInterface;
use WyriHaximus\React\Filesystem\S3\S3Adapter;

require 'vendor/autoload.php';

try {
    $loop = Factory::create();
    $loop->futureTick(function () use ($loop) {
        $adapter = new S3Adapter($loop, [
            'credentials' => [
                'key' => 'KEY',
                'secret' => 'SECRET',
            ],
            'region' => 'REGION',
            'version' => 'latest',
        ], 'BUCKET');
        $filesystem = Filesystem::createFromAdapter($adapter);
        $stream = $filesystem->dir('')->lsStreaming();
        $stream->on('data', function (NodeInterface $node) {
            echo $node->getPath(), PHP_EOL;
        });
    });

    $loop->run();
} catch (Exception $e) {
    var_export($e->getMessage());
}
