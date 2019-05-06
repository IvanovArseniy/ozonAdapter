<?php

namespace App\Services;
use Log;
use DateTime;

class GearmanService
{
    public static function add($data) {
        $client = new \GearmanClient();
        $client->addServers('localhost');

        $client->doBackground('main_test', json_encode($data));
    }

    public static function processStockMessage(\GearmanJob $job)
    {
        $json_data = $job->workload();
        $json_data = json_decode($json_data, true);
        Log::info('gearmanWork:' . json_encode($json_data));

        $ozonService = new OzonService();
        $ozonService->sendStockForProduct($json_data);
    }
}