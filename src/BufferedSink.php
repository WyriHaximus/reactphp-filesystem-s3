<?php

namespace WyriHaximus\React\Filesystem\S3;

use React\Filesystem\Stream\GenericStreamInterface;
use React\Stream\BufferedSink as StreamBufferedSink;

class BufferedSink extends StreamBufferedSink implements GenericStreamInterface
{
    /**
     * @return resource
     */
    public function getFiledescriptor()
    {
        return spl_object_hash($this);
    }
}
