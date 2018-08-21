<?php

namespace Enniel\Ami\Commands;

use Exception;
use Clue\React\Ami\Client;
use Clue\React\Ami\Protocol\Response;

class AmiCli extends AmiAbstract
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ami:client
                                {--host= : Asterisk ami host}
                                {--port= : Asterisk ami port}
                                {--username= : Asterisk ami username}
                                {--secret= : Asterisk ami secret key}
                                {client? : Command}
                                {--autoclose : Close after call command}
                                {--json : Output as json}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send command from asterisk ami client';

    protected $exit = [
        'quit', 'exit',
    ];

    public function client(Client $client)
    {
        parent::client($client);
        // client connected and authenticated
        $this->info('starting ami client interface');
        $command = $this->argument('client');
        if (!empty($command)) {
            $this->sendCommand($command);
        } else {
            $this->writeInterface();
        }
        $client->on('close', function () {
            // the connection to the AMI just closed
            $this->info('closed ami client');
        });
    }

    public function sendCommand($command)
    {
        $this->request('Command', [
            'Command' => $command,
        ])->then([$this, 'writeResponse'], [$this, 'writeException']);
    }

    public function writeInterface()
    {
        $command = $this->ask('Write command');
        if (in_array(mb_strtolower($command), $this->exit)) {
            $this->stop();
        }
        $this->sendCommand($command);
    }

    public function writeResponse(Response $response)
    {
        $json = $this->option('json');
        if ($json) {
            $this->line(json_encode($response->getFields()));
        } else {
            $this->line($response->getFieldValue('Output'));
        }

        $autoclose = $this->option('autoclose');
        if ($autoclose) {
            $this->stop();
        } else {
            $this->writeInterface();
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
}
