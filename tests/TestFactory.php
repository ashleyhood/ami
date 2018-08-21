<?php

namespace Enniel\Ami\Tests;

use React\Socket\ConnectionInterface;
use Clue\React\Ami\Client;
use React\EventLoop\LoopInterface;
use React\Promise\FulfilledPromise;
use React\Socket\ConnectorInterface;
use Clue\React\Ami\Factory;

class TestFactory extends Factory
{
    /**
     * @param \React\EventLoop\LoopInterface    $loop
     * @param \React\Socket\ConnectorInterface  $connector
     * @param \React\Socket\ConnectionInterface $stream
     */
    public function __construct(LoopInterface $loop, ConnectorInterface $connector, ConnectionInterface $stream)
    {
        parent::__construct($loop, $connector);
        $this->stream = $stream;
    }

    /**
     * Create client.
     *
     * @param string $url
     *
     * @return \React\Promise\Promise
     */
    public function createClient($url = '')
    {
        return new FulfilledPromise(new Client($this->stream));
    }
}
