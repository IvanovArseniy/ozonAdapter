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
        $worker->addServer(config('app.gearmman_server'));

        $worker->addFunction('processOrderNotification', '\App\Services\GearmanService::processOrderNotification');
        $worker->addFunction('processForRetry', '\App\Services\GearmanService::processForRetry');
        $worker->addFunction('checkApprovedOrders', '\App\Services\GearmanService::checkApprovedOrders');
        $worker->addFunction('processProductToOzon', '\App\Services\GearmanService::processProductToOzon');
        $worker->addFunction('setOzonProductId', '\App\Services\GearmanService::setOzonProductId');
        $worker->addFunction('chatEddySync', '\App\Services\GearmanService::syncChatsWithEddy');
        $worker->addFunction('eddyChatSync', '\App\Services\GearmanService::syncFromEddyToChats');
        $worker->addFunction('updateProduct', '\App\Services\GearmanService::updateProduct');

        while (1) {
            $worker->work();
            if ($worker->returnCode() != GEARMAN_SUCCESS) {
                break;
            }
        }
    }
}