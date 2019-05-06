<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Worker extends Command
{
    protected $name        = 'worker';
    protected $description = 'Gearman worker';

    public function handle()
    {
        $worker = new \GearmanWorker();
        $worker->addServer('localhost');

        $worker->addFunction('main_test', '\App\Services\GearmanService::processStock');

        while (1) {
            $worker->work();
            if ($worker->returnCode() != GEARMAN_SUCCESS) {
                break;
            }
        }
    }
}