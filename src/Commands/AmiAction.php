<?php

namespace Enniel\Ami\Commands;

use Exception;
use Clue\React\Ami\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Clue\React\Ami\Protocol\Response;
use Clue\React\Ami\Protocol\Collection;

class AmiAction extends AmiAbstract
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ami:action
                                {--host= : Asterisk ami host}
                                {--port= : Asterisk ami port}
                                {--username= : Asterisk ami username}
                                {--secret= : Asterisk ami secret key}
                                {action : Action name}
                                {--arguments=* : Arguments for ami action}
                                {--collection= : Expected end event of collection}
                                {--json : Output as json}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send action from asterisk ami';

    protected $headers = [
        'Field',
        'Value',
    ];

    public function listCommands($options)
    {
        $this->sender('ListCommands', $options);
    }

    public function status($options)
    {
        $this->collector('Status', 'StatusComplete');
    }

    public function client(Client $client)
    {
        parent::client($client);
        $arguments = $this->option('arguments');
        $arguments = is_array($arguments) ? $arguments : [];
        $options = [];
        $isAssoc = Arr::isAssoc($arguments);
        foreach ($arguments as $key => $value) {
            if (Str::contains($value, ':') && !$isAssoc) {
                $array = explode(':', $value);
                if ($key = Arr::get($array, '0')) {
                    $value = Arr::get($array, '1', '');
                    $options[$key] = $value;
                }
            } else {
                $options[$key] = $value;
            }
        }

        $action = $this->argument('action');
        $collectionEndEvent = $this->option('collection');
        $method = Str::camel($action);

        if (method_exists(__CLASS__, $method)) {
            $this->$method($options);
        } elseif ($collectionEndEvent) {
            $collectionEndEvent = 'auto' !== $collectionEndEvent ? $collectionEndEvent : $action.'Complete';
            $this->collector($action, $collectionEndEvent);
        } else {
            $this->sender($action, $options);
        }
    }

    public function responseMonitor(Response $response)
    {
        $fields = [];
        foreach ($response->getFields() as $key => $value) {
            $fields[] = [
                $key,
                $value,
            ];
        }
        $this->table($this->headers, $fields);
    }

    public function collectionMonitor(Collection $collection)
    {
        $this->responseMonitor(new Response($collection->getFields()));

        foreach ($collection->getEntryEvents() as $event) {
            $this->responseMonitor(new Response($event->getFields()));
        }

        $this->responseMonitor(new Response($collection->getCompleteEvent()->getFields()));
    }

    public function writeResponse(Response $response)
    {
        $json = $this->option('json');
        if ($json) {
            $this->line(json_encode($response->getFields()));
        } else {
            $this->line($response->getFieldValue('Output'));
        }
    }

    public function writeCollection(Collection $collection)
    {
        $output = [];
        $output['start'] = $collection->getFields();

        foreach ($collection->getEntryEvents() as $event) {
            $output['events'][] = $event->getFields();
        }

        $output['complete'] = $collection->getCompleteEvent()->getFields();

        $json = $this->option('json');
        if ($json) {
            $this->line(json_encode($output));
        } else {
            foreach ($output as $line) {
                $this->line($line);
                $this->line("\n");
            }
        }
    }

    public function writeException(Exception $e)
    {
        $response = null;

        if (method_exists($e, 'getResponse')) {
            $response = $e->getResponse();
        }

        $json = $this->option('json');

        if ($json) {
            if ($response) {
                $this->line(json_encode($response->getFields()));
            } else {
                $this->line(json_encode(['error' => $e->getMessage(), 'code' => $e->getCode()]));
            }
        } else {
            $this->warn($e->getMessage());
        }
        $this->stop();
    }

    private function sender($action, $options)
    {
        $request = $this->request($action, $options);

        $this->dispatcher->fire('ami.action.sent', [$this, $action, $request]);

        $request->then(
            function (Response $response) use ($action) {
                $this->dispatcher->fire('ami.action.responded', [$this, $action, $response]);

                if ($this->runningInConsole() && !$this->option('json')) {
                    $this->responseMonitor($response);
                } else {
                    $this->writeResponse($response);
                }

                $this->stop();
            },
            [$this, 'writeException']
        );
    }

    private function collector($action, $expectedEndEvent)
    {
        $collector = $this->collectEvents($action, $expectedEndEvent);

        $this->dispatcher->fire('ami.action.sent', [$this, $action, $collector]);

        $collector->then(
            function (Collection $collection) use ($action) {
                $this->dispatcher->fire('ami.action.responded', [$this, $action, $collection]);

                if ($this->runningInConsole() && !$this->option('json')) {
                    $this->collectionMonitor($collection);
                } else {
                    $this->writeCollection($collection);
                }

                $this->stop();
            },
            [$this, 'writeException']
        );
    }
}
