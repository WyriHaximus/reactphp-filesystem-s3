<?php

namespace WyriHaximus\React\Filesystem\S3;

use Aws\Result;
use Aws\S3\S3Client;
use Aws\Sdk;
use GuzzleHttp\HandlerStack;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\Filesystem\AdapterInterface;
use React\Filesystem\CallInvokerInterface;
use React\Filesystem\FilesystemInterface;
use React\Filesystem\Node\NodeInterface;
use React\Filesystem\ObjectStream;
use React\Filesystem\PooledInvoker;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\RejectedPromise;
use React\Stream\WritableStreamInterface;
use WyriHaximus\React\GuzzlePsr7\HttpClientAdapter;

class S3Adapter implements AdapterInterface
{
    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var
     */
    protected $queueTimer;

    /**
     * @var S3Client
     */
    protected $s3Client;

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var PooledInvoker
     */
    protected $invoker;

    /**
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * @param LoopInterface $loop
     * @param array $options
     * @param string $bucket
     */
    public function __construct(LoopInterface $loop, $options = [], $bucket = '')
    {
        $this->loop = $loop;
        $options['http_handler'] = HandlerStack::create(new HttpClientAdapter($loop));
        $this->s3Client = (new Sdk($options))->createS3();
        $this->bucket = $bucket;
        $this->invoker = new PooledInvoker($this);
    }

    /**
     * @param FilesystemInterface $filesystem
     */
    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @return LoopInterface
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * @param CallInvokerInterface $invoker
     * @return void
     */
    public function setInvoker(CallInvokerInterface $invoker)
    {
        $this->invoker = $invoker;
    }

    /**
     * @param string $function
     * @param array $args
     * @param int $errorResultCode
     * @return \React\Promise\Promise
     */
    public function callFilesystem($function, $args, $errorResultCode = -1)
    {
        $this->startQueue();
        $deferred = new Deferred();
        $this->s3Client->{$function . 'Async'}($args)->then(function ($result) use ($deferred) {
            $deferred->resolve($result);
        }, function ($error) use ($deferred) {
            $deferred->reject($error);
        });
        return $deferred->promise();
    }

    protected function startQueue()
    {
        if ($this->queueTimer instanceof Timer && $this->loop->isTimerActive($this->queueTimer)) {
            return;
        }

        $this->queueTimer = $this->loop->addPeriodicTimer(HttpClientAdapter::QUEUE_TIMER_INTERVAL, function (Timer $timer) {
            \GuzzleHttp\Promise\queue()->run();

            if ($this->invoker->isEmpty()) {
                $timer->cancel();
            }
        });
    }

    /**
     * @param string $path
     * @param $mode
     * @return \React\Promise\PromiseInterface
     */
    public function mkdir($path, $mode = self::CREATION_MODE)
    {
        return new FulfilledPromise();
    }

    /**
     * @param string $path
     * @return \React\Promise\PromiseInterface
     */
    public function rmdir($path)
    {
        // TODO: Implement rmdir() method.
    }

    /**
     * @param string $filename
     * @return \React\Promise\PromiseInterface
     */
    public function unlink($filename)
    {
        return $this->invoker->invokeCall('deleteObject', [
            'Bucket' => $this->bucket,
            'Key' => $filename,
        ])->then(function () {
            return new FulfilledPromise();
        }, function () {
            return new RejectedPromise();
        });
    }

    /**
     * @param string $path
     * @param int $mode
     * @return \React\Promise\PromiseInterface
     */
    public function chmod($path, $mode)
    {
        // TODO: Implement chmod() method.
    }

    /**
     * @param string $path
     * @param int $uid
     * @param int $gid
     * @return \React\Promise\PromiseInterface
     */
    public function chown($path, $uid, $gid)
    {
        return new RejectedPromise(new \Exception('No implemented, not intended for use'));
    }

    /**
     * @param string $filename
     * @return \React\Promise\PromiseInterface
     */
    public function stat($filename)
    {
        if (substr($filename, -1) === NodeInterface::DS) {
            return new FulfilledPromise([]);
        }

        return $this->invoker->invokeCall('headObject', [
            'Bucket' => $this->bucket,
            'Key' => $filename,
        ])->then(function (Result $result) {
            if ($result->toArray()['@metadata']['statusCode'] !== 200) {
                return new RejectedPromise();
            }

            return new FulfilledPromise([
                'size' => $result->get('ContentLength'),
                'atime' => $result->get('LastModified')->format('U'),
                'ctime' => $result->get('LastModified')->format('U'),
                'mtime' => $result->get('LastModified')->format('U'),
            ]);
        });
    }

    /**
     * @param string $path
     * @param int $flags
     * @return \React\Promise\PromiseInterface
     */
    public function ls($path, $flags = EIO_READDIR_DIRS_FIRST)
    {
        $stream = new ObjectStream();

        $this->invoker->invokeCall('listObjects', [
            'Bucket' => $this->bucket,
            'Delimiter' => '/',
            'Prefix' => $path,
        ])->then(function ($ls) use ($stream) {
            $this->processLsContents($ls->toArray(), $stream);
        });

        return $stream;
    }

    protected function processLsContents($array, $stream)
    {
        if (isset($array['Contents'])) {
            foreach ($array['Contents'] as $file) {
                $stream->emit('data', [
                    $this->filesystem->file($file['Key'], $this),
                ]);
            }
        }

        if (isset($array['CommonPrefixes'])) {
            foreach ($array['CommonPrefixes'] as $file) {
                $stream->emit('data', [
                    $this->filesystem->dir($file['Prefix']),
                ]);
            }
        }

        $stream->close();
    }

    /**
     * @param string $path
     * @param $mode
     * @return \React\Promise\PromiseInterface
     */
    public function touch($path, $mode = self::CREATION_MODE)
    {
        return $this->openWrite($path)->then(function (WritableStreamInterface $stream) {
            $deferred = new Deferred();

            $stream->on('close', function () use ($deferred) {
                $deferred->resolve();
            });
            $stream->end('');

            return $deferred->promise();
        });
    }

    /**
     * @param string $path
     * @param string $flags
     * @param $mode
     * @return \React\Promise\PromiseInterface
     */
    public function open($path, $flags, $mode = self::CREATION_MODE)
    {
        if (strpos($flags, 'r') !== false) {
            return $this->openRead($path);
        }

        if (strpos($flags, 'w') !== false) {
            return $this->openWrite($path);
        }

        throw new \InvalidArgumentException('Open must be used with read or write flag');
    }

    protected function openRead($path)
    {
        return $this->invoker->invokeCall('getObject', [
            'Bucket' => $this->bucket,
            'Key' => $path,
        ])->then(function (Result $result) {
            return \React\Promise\resolve($result['Body']);
        });
    }

    protected function openWrite($path)
    {
        $sink = new BufferedSink();
        $sink->promise()->then(function ($body) use ($path) {
            return $this->invoker->invokeCall('putObject', [
                'Body' => $body,
                'ContentLength' => strlen($body),
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);
        })->then(function ($result) {
            return \React\Promise\resolve($result['Body']);
        });
        return \React\Promise\resolve($sink);
    }

    /**
     * @param resource $fd
     * @return \React\Promise\PromiseInterface
     */
    public function close($fd)
    {
        return new RejectedPromise(new \Exception('No implemented, not intended for use'));
    }

    /**
     * @param $fileDescriptor
     * @param int $length
     * @param int $offset
     * @return \React\Promise\PromiseInterface
     */
    public function read($fileDescriptor, $length, $offset)
    {
        return new RejectedPromise(new \Exception('No implemented, not intended for use'));
    }

    /**
     * @param $fileDescriptor
     * @param string $data
     * @param int $length
     * @param int $offset
     * @return \React\Promise\PromiseInterface
     */
    public function write($fileDescriptor, $data, $length, $offset)
    {
        return new RejectedPromise(new \Exception('No implemented, not intended for use'));
    }

    /**
     * @param string $fromPath
     * @param string $toPath
     * @return \React\Promise\PromiseInterface
     */
    public function rename($fromPath, $toPath)
    {
        return $this->invoker->invokeCall('copyObject', [
            'Bucket' => $this->bucket,
            'Key' => $toPath,
            'CopySource' => $this->bucket . '/' . $fromPath,
        ])->then(function () use ($fromPath) {
            return $this->unlink($fromPath);
        })->then(function () {
            return new FulfilledPromise();
        }, function () {
            return new RejectedPromise();
        });
    }
}
