<?php

namespace Swpider\Queue;

use Pheanstalk\Socket;
use Pheanstalk\Exception\ConnectionException;
use Pheanstalk\Exception\SocketException;
use Pheanstalk\Socket\WriteHistory;


class SwooleSocket
{

    /**
     * The default timeout for a blocking read on the socket
     */
    const SOCKET_TIMEOUT = 1;

    /**
     * Number of retries for attempted writes which return zero length.
     */
    const WRITE_RETRIES = 8;

    //private $_socket;
    //private $host;
    //private $port;
    //private $connectTimeout;
    private $connectPersistent;


    private static $_client;


    /**
     * @param string $host
     * @param int    $port
     * @param int    $connectTimeout
     * @param bool   $connectPersistent
     *
     * @throws ConnectionException
     */
    public function __construct($host, $port, $connectTimeout, $connectPersistent)
    {
        $this->connectPersistent = $connectPersistent;

        if($this->_client()->isConnected()){
            $this->disconnect();
        }

        if(!$this->_client()->connect($host, $port, $connectTimeout)){
            throw new ConnectionException($this->_client()->errCode, socket_strerror($this->_client()->errCode) . " (connecting to $host:$port)");
        }
    }


    public function write($data)
    {
        if($this->_client()->send($data) == false){
            throw new SocketException(socket_strerror($this->_client()->errCode), $this->_client()->errCode);
        }
    }


    public function read()
    {
        $re = $this->_client()->recv(65535, 1);

        if($re === ''){
            throw new SocketException('Socket closed by server!');
        }elseif($re === false){
            throw new SocketException(socket_strerror($this->_client()->errCode), $this->_client()->errCode);
        }

        return rtrim($re);
    }


    public function disconnect()
    {
        $this->_client()->close();
    }

    // ----------------------------------------

    /**
     * Wrapper class for all stream functions.
     * Facilitates mocking/stubbing stream operations in unit tests.
     */
    private function _client()
    {
        if(is_null(self::$_client)){
            $flag = SWOOLE_TCP;
            if ($this->connectPersistent) {
                $flag |= SWOOLE_KEEP;
            }

            self::$_client = new \swoole_client($flag);
        }
        return self::$_client;
    }
}

