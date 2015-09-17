<?php

namespace WyriHaximus\React\Filesystem\S3;

use React\Filesystem\Stream\GenericStreamInterface;
use React\Stream\ThroughStream as StreamThroughStream;

class ThroughStream extends StreamThroughStream implements GenericStreamInterface
{
    /**
     * @return resource
     */
    public function getFiledescriptor()
    {
        return spl_object_hash($this);
    }
}
