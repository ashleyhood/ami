<?php

namespace Enniel\Ami\Commands;

use Exception;
use Clue\React\Ami\Factory;
use Clue\React\Ami\Client;
use Illuminate\Support\Arr;
use Illuminate\Events\Dispatcher;
use React\EventLoop\LoopInterface;
use Clue\React\Ami\Protocol\Response;
use React\Promise\Deferred;

abstract class AmiAbstract extends Command
{
    protected $loop;

    protected $connector;

    protected $client;

    protected $config;

    protected $events;

    protected $dispatcher;

    public function __construct(Dispatcher $dispatcher, LoopInterface $loop, Factory $connector, array $config = [])
    {
        parent::__construct();
        $this->loop = $loop;
        $this->connector = $connector;
        $this->events = Arr::get($config, 'events', []);
        $this->config = $config;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $url = $this->generateUrl($this->options());

        $client = $this->connector->createClient($url);
        $client->then([$this, 'client'], [$this, 'writeException']);
        $this->loop->run();
    }

    public function client(Client $client)
    {
        $this->client = $client;
        $this->client->on('error', [$this, 'writeException']);
    }

    public function writeException(Exception $e)
    {
        $this->warn($e->getMessage());
        $this->stop();
    }

    public function writeResponse(Response $response)
    {
        $message = Arr::get($response->getFields(), 'Message', null);
        $this->line($message);
        $this->stop();
    }

    public function request($action, array $options = [])
    {
        return $this->client->request($this->client->createAction($action, $options));
    }

    public function stop()
    {
        $this->loop->stop();

        return false;
    }

    protected function collectEvents($command, $expectedEndEvent)
    {
        $req = $this->client->createAction($command);
        $ret = $this->client->request($req);
        $id = $req->getActionId();

        $deferred = new Deferred();

        // collect all intermediary channel events with this action ID
        $collected = array();
        $collector = function (Event $event) use ($id, &$collected, $deferred, $expectedEndEvent) {
            if ($event->getActionId() === $id) {
                $collected[] = $event;

                if ($event->getName() === $expectedEndEvent) {
                    $deferred->resolve($collected);
                }
            }
        };
        $this->client->on('event', $collector);

        // unregister collector if client fails
        $client = $this->client;
        $unregister = function () use ($client, $collector) {
            $client->removeListener('event', $collector);
        };
        $ret->then(null, $unregister);

        // stop waiting for events
        $deferred->promise()->then($unregister);

        return $ret->then(function (Response $response) use ($deferred) {
            // final result has been received => merge all intermediary channel events
            return $deferred->promise()->then(function ($collected) use ($response) {
                $last = array_pop($collected);

                return new Collection($response, $collected, $last);
            });
        });
    }

    private function generateUrl($options)
    {
        foreach (['scheme', 'host', 'port', 'username', 'secret'] as $key) {
            $value = Arr::get($options, $key);
            $value = is_null($value) ? Arr::get($this->config, $key) : $value;
            $options[$key] = $value;
        }

        $scheme = isset($options['scheme']) ? $options['scheme'].'://' : '';
        $host = $options['host'] ?? '';
        $port = isset($options['port']) ? ':'.$options['port'] : '';
        $username = $options['username'] ?? '';
        $secret = isset($options['secret']) ? ':'.$options['secret']  : '';
        $secret = ($username || $secret) ? "$secret@" : '';

        return $scheme.$username.$secret.$host.$port;
    }
}
