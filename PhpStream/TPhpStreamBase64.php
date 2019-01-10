<?php

namespace Overblog\ThriftBundle\PhpStream;

use Thrift\Transport\TPhpStream;
use Thrift\Transport\TTransport;
use Thrift\Exception\TException;
use Thrift\Factory\TStringFuncFactory;

class TPhpStreamBase64 extends TTransport
{
    const MODE_R = 1;
    const MODE_W = 2;

    private $inStream_ = null;

    private $outStream_ = null;

    private $read_ = false;

    private $write_ = false;

    public function __construct($mode)
    {
        $this->read_ = $mode & self::MODE_R;
        $this->write_ = $mode & self::MODE_W;
    }

    public function open()
    {
        if ($this->read_) {
            $this->inStream_ = @fopen(self::inStreamName(), 'r');
            if (!is_resource($this->inStream_)) {
                throw new TException('TPhpStream: Could not open php://input');
            }

            if(isset($_SERVER['HTTP_CONTENT_TRANSFER_ENCODING']) &&  $_SERVER['HTTP_CONTENT_TRANSFER_ENCODING'] == 'base64'){
                stream_filter_append($this->inStream_, 'convert.base64-decode');
            }
        }
        if ($this->write_) {
            $this->outStream_ = @fopen('php://output', 'w');
            if (!is_resource($this->outStream_)) {
                throw new TException('TPhpStream: Could not open php://output');
            }
            if(isset($_SERVER['HTTP_CONTENT_TRANSFER_ENCODING']) &&  $_SERVER['HTTP_CONTENT_TRANSFER_ENCODING'] == 'base64'){
                stream_filter_append($this->outStream_, 'convert.base64-encode');
            }
        }
    }

    public function close()
    {
        if ($this->read_) {
            @fclose($this->inStream_);
            $this->inStream_ = null;
        }
        if ($this->write_) {
            @fclose($this->outStream_);
            $this->outStream_ = null;
        }
    }

    public function isOpen()
    {
        return
            (!$this->read_ || is_resource($this->inStream_)) &&
            (!$this->write_ || is_resource($this->outStream_));
    }

    public function read($len)
    {
        $data = @fread($this->inStream_, $len);
        if ($data === FALSE || $data === '') {
            throw new TException('TPhpStream: Could not read '.$len.' bytes');
        }

        return $data;
    }

    public function write($buf)
    {
        while (TStringFuncFactory::create()->strlen($buf) > 0) {
            $got = @fwrite($this->outStream_, $buf);
            if ($got === 0 || $got === FALSE) {
                throw new TException('TPhpStream: Could not write '.TStringFuncFactory::create()->strlen($buf).' bytes');
            }
            $buf = TStringFuncFactory::create()->substr($buf, $got);
        }
    }

    public function flush()
    {
        @fflush($this->outStream_);
    }

    private static function inStreamName()
    {
        if (php_sapi_name() == 'cli') {
            return 'php://stdin';
        }

        return 'php://input';
    }

}