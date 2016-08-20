<?php

namespace Kraken\Stream;

use Kraken\Throwable\Exception\Runtime\Io\IoReadException;
use Kraken\Throwable\Exception\Logic\InvalidArgumentException;

class StreamReader extends StreamSeeker implements StreamReaderInterface
{
    /**
     * @var bool
     */
    protected $readable;

    /**
     * @var int
     */
    protected $bufferSize;

    /**
     * @param resource $resource
     * @param bool $autoClose
     * @throws InvalidArgumentException
     */
    public function __construct($resource, $autoClose = true)
    {
        parent::__construct($resource, $autoClose);

        $this->readable = true;
        $this->bufferSize = 4096;

        if (function_exists('stream_set_read_buffer'))
        {
            stream_set_read_buffer($this->resource, 0);
        }
    }

    /**
     *
     */
    public function __destruct()
    {
        unset($this->readable);
        unset($this->bufferSize);

        parent::__destruct();
    }

    /**
     * @override
     */
    public function isReadable()
    {
        return $this->readable;
    }

    /**
     * @override
     */
    public function setBufferSize($bufferSize)
    {
        $this->bufferSize = $bufferSize;
    }

    /**
     * @override
     */
    public function getBufferSize()
    {
        return $this->bufferSize;
    }

    /**
     * @override
     */
    public function read($length = null)
    {
        if (!$this->readable)
        {
            return $this->throwAndEmitException(
                new IoReadException('Stream is no longer readable.')
            );
        }

        if ($length === null)
        {
            $length = $this->bufferSize;
        }

        $ret = fread($this->resource, $length);

        if ($ret === false)
        {
            return $this->throwAndEmitException(
                new IoReadException('Cannot read stream.')
            );
        }
        else if ($ret !== '')
        {
            $this->emit('data', [ $this, $ret ]);
        }

        return $ret;
    }

    /**
     * @override
     */
    public function close()
    {
        if ($this->closing)
        {
            return;
        }

        $this->closing = true;
        $this->readable = false;
        $this->writable = false;

        $this->handleClose();
        $this->emit('close', [ $this ]);
    }
}
