<?php

namespace Enniel\Ami\Tests;

use Clue\React\Ami\Factory;
use React\Stream\Stream;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;
use React\Socket\Connection;
use React\Socket\ConnectionInterface;

class AmiServiceProvider extends \Enniel\Ami\Providers\AmiServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register()
    {
        $this->registerStream();
        parent::register();
    }

    /**
     * Register stream.
     */
    protected function registerStream()
    {
        $this->app->singleton(ConnectionInterface::class, function ($app) {
            return new Connection(fopen('php://memory', 'r+'), $app[LoopInterface::class]);
        });
        $this->app->alias(ConnectionInterface::class, 'ami.stream');
    }

    /**
     * {@inheritdoc}
     */
    protected function registerFactory()
    {
        $this->app->singleton(Factory::class, function ($app) {
            return new TestFactory($app[LoopInterface::class], $app[ConnectorInterface::class], $app[ConnectionInterface::class]);
        });
        $this->app->alias(Factory::class, 'ami.factory');
    }
}
