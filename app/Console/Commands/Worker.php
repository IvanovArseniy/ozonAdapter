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

        $worker->addFunction('processOrderNotification', '\App\Services\GearmanService::processOrderNotification');
        $worker->addFunction('processForRetry', '\App\Services\GearmanService::processForRetry');
        $worker->addFunction('processProductToOzon', '\App\Services\GearmanService::processProductToOzon');
        $worker->addFunction('setOzonProductId', '\App\Services\GearmanService::setOzonProductId');
        $worker->addFunction('updateProduct', '\App\Services\GearmanService::updateProduct');
        $worker->addFunction('processStockAndPrice', '\App\Services\GearmanService::updateProduct');

        while (1) {
            $worker->work();
            if ($worker->returnCode() != GEARMAN_SUCCESS) {
                break;
            }
        }
    }
}