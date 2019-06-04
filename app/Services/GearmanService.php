<?php

namespace App\Services;
use Log;
use DateTime;

class GearmanService
{
    public static function addPriceAndStock($data) {
        $client = new \GearmanClient();
        $client->addServers('localhost');
        $client->doBackground('processStockAndPrice', json_encode($data));
    }

    public static function addOrderNotification($data) {
        $client = new \GearmanClient();
        $client->addServers('localhost');
        $client->doBackground('processOrderNotification', json_encode($data));
    }

    public static function processStockAndPrice(\GearmanJob $job)
    {
        $data = $job->workload();
        $json_data = json_decode($data, true);
        Log::info('stocks_price gearmanWork:' . json_encode($json_data));

        $ozonService = new OzonService();
        $sendStockResult = $ozonService->sendStockAndPriceForProduct($json_data);

        if (isset($sendStockResult['result']) && !$sendStockResult['result']) {
            GearmanService::processRetry($json_data, $sendStockResult);
        }
    }

    public static function processOrderNotification(\GearmanJob $job)
    {
        $data = $job->workload();
        $json_data = json_decode($data, true);
        Log::info('order gearmanWork:' . json_encode($json_data));

        $dropshippService = new DropshippService();
        $orderNotifyResult = $dropshippService->notifyOrder($json_data);

        if (!$orderNotifyResult) {
            GearmanService::processRetry($json_data, $orderNotifyResult);
        }
    }

    public static function processRetry($json_data, $resultedData)
    {
        $try = 1;
        if (isset($json_data['try'])) {
            $try = $try + $json_data['try'];
        }
        $resultedData['data']['try'] = $try;
        if ($try >= 0) {
            app('db')->connection('mysql')->table('gearman_retry_queue')
            ->insert(
                [
                    'unique_key' => $job->unique(),
                    'function_name' => $job->functionName(),
                    'data' => json_encode($resultedData['data']),
                ]
            );
        }
    }

    public static function addForRetry()
    {
        $success = false;
        try {
            $res = app('db')->connection('mysql')->table('gearman_retry_queue')
                ->get();
            if ($res) {
                foreach ($res as $key => $row) {
                    $unique_key = $row->unique_key;
                    $function_name = $row->function_name;
                    $data = $row->data;
                    GearmanService::add(json_decode($row->data));

                    app('db')->connection('mysql')->table('gearman_retry_queue')
                        ->where('unique_key', $unique_key)
                        ->where('function_name', $function_name)
                        ->delete();
                }
                $success = true;
            }
        }
        catch (\Exception $e) {
            $success = false;
        }
        return $success;
   }
}