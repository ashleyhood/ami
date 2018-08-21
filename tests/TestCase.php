<?php

namespace Enniel\Ami\Tests;

use React\Socket\ConnectionInterface;
use Illuminate\Config\Repository;
use React\EventLoop\LoopInterface;
use Illuminate\Container\Container;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Console\Application as Console;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * @var \React\Socket\ConnectionInterface
     */
    protected $stream;

    /**
     * @var bool
     */
    protected $running;

    /**
     * @var \Illuminate\Events\Dispatcher
     */
    protected $events;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $app = new Container();
        $app->instance('config', new Repository());
        (new EventServiceProvider($app))->register();
        (new AmiServiceProvider($app))->register();
        $this->loop = $app[LoopInterface::class];
        $this->loop->futureTick(function () {
            if (!$this->running) {
                $this->loop->stop();
            }
        });
        $this->stream = $app[ConnectionInterface::class];
        $this->events = $app['events'];
        $this->app = $app;
    }

    /**
     * Call console command.
     *
     * @param string $command
     * @param array  $options
     */
    protected function console($command, array $options = [])
    {
        return (new Console($this->app, $this->events, '5.3'))->call($command, $options);
    }

    private function createStreamMock()
    {
        return $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('write', 'close'))->getMock();
    }
}
