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

        $worker->addFunction('processStockAndPrice', '\App\Services\GearmanService::processStockAndPrice');
        $worker->addFunction('processOrderNotification', '\App\Services\GearmanService::processOrderNotification');
        $worker->addFunction('setOzonIds', '\App\Services\GearmanService::setOzonIds');

        while (1) {
            $worker->work();
            if ($worker->returnCode() != GEARMAN_SUCCESS) {
                break;
            }
        }
    }
}