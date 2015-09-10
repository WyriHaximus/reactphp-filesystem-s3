<?php

use React\EventLoop\Factory;
use React\Filesystem\Filesystem;
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
        $localFilesystem = Filesystem::createFromAdapter(new EioAdapter($loop));

        $localFilesystem->file('vendor/autoload.php')->copy($filesystem->file('autoload.php'));
    });

    $loop->run();
} catch (Exception $e) {
    var_export($e->getMessage());
}
