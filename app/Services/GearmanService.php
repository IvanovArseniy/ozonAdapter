<?php

namespace App\Services;
use Log;
use DateTime;

class GearmanService
{
    public static function addPriceAndStock($data)
    {
        $client = new \GearmanClient();
        $client->addServers('localhost');
        $client->doBackground('processStockAndPrice', json_encode($data));
    }

    public static function addOrderNotification($data)
    {
        $client = new \GearmanClient();
        $client->addServers('localhost');
        $client->doBackground('processOrderNotification', json_encode($data));
    }

    public static function addProcessProductToOzonNotification($data)
    {
        $client = new \GearmanClient();
        $client->addServers('localhost');
        $client->doBackground('processProductToOzon', json_encode($data));
    }

    public static function addSetOzonIds($data) {
        $client = new \GearmanClient();
        $client->addServers('localhost');
        $client->doBackground('setOzonIds', json_encode($data));
    }

    public static function addSetOzonProductIdNotification($data)
    {
        $client = new \GearmanClient();
        $client->addServers('localhost');
        $client->doBackground('setOzonProductId', json_encode($data));
    }
    
    public static function addForRetry($data)
    {
        $client = new \GearmanClient();
        $client->addServers('localhost');
        $client->doBackground('processForRetry', json_encode($data));
    }

    public static function processStockAndPrice(\GearmanJob $job)
    {
        $data = $job->workload();
        $json_data = json_decode($data, true);
        Log::info('stocks_price gearmanWork:' . json_encode($json_data));

        $ozonService = new OzonService();
        $sendStockResult = $ozonService->sendStockAndPriceForProduct($json_data);

        if (isset($sendStockResult['result']) && !$sendStockResult['result']) {
            GearmanService::processRetry($json_data, $sendStockResult, $job);
        }
    }

    public static function processOrderNotification(\GearmanJob $job)
    {
        $data = $job->workload();
        $json_data = json_decode($data, true);
        Log::info('order gearmanWork:' . json_encode($json_data));

        $dropshippService = new DropshippService();
        $orderNotifyResult = $dropshippService->notifyOrder($json_data);

        if (isset($orderNotifyResult['result']) && !$orderNotifyResult['result']) {
            GearmanService::processRetry($json_data, $orderNotifyResult, $job);
        }
    }

    public static function processProductToOzon(\GearmanJob $job)
    {
        $data = $job->workload();
        $json_data = json_decode($data, true);
        Log::info('processProductToOzon gearmanWork:' . json_encode($json_data));

        $ozonService = new OzonService();
        $processProductResult = $ozonService->processProductToOzon($json_data);

        if (isset($processProductResult['result']) && !$processProductResult['result']) {
            GearmanService::processRetry($json_data, $processProductResult, $job);
        }
    }

    public static function setOzonProductId(\GearmanJob $job)
    {
        $data = $job->workload();
        $json_data = json_decode($data, true);
        Log::info('setOzonProductId gearmanWork:' . json_encode($json_data));

        $ozonService = new OzonService();
        $setOzonProductIdResult = $ozonService->setOzonProductId($json_data);

        if (isset($setOzonProductIdResult['result']) && !$setOzonProductIdResult['result']) {
            GearmanService::processRetry($json_data, $setOzonProductIdResult, $job);
        }
    }

    public static function setOzonIds(\GearmanJob $job)
    {
        $data = $job->workload();
        $json_data = json_decode($data, true);
        $ozonService = new OzonService();
        $ozonService->setOzonProductIdOld();
    }

    public static function processRetry($json_data, $resultedData, $job)
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
                    'processing' => 0
                ]
            );
        }
    }

   public static function processForRetry(\GearmanJob $job)
   {
        $success = false;
        try {
            app('db')->connection('mysql')->table('gearman_retry_queue')
                ->update(['processing' => 1]);

            $res = app('db')->connection('mysql')->table('gearman_retry_queue')
                ->where('processing', 1)
                ->get();
            if ($res) {
                foreach ($res as $key => $row) {
                    $unique_key = $row->unique_key;
                    $function_name = $row->function_name;
                    $data = $row->data;
                    if ($function_name == 'processStockAndPrice') {
                        GearmanService::addPriceAndStock(json_decode($row->data));
                    }
                    elseif ($function_name == 'processOrderNotification') {
                        GearmanService::addOrderNotification(json_decode($row->data));
                    }
                    elseif ($function_name == 'processProductToOzon') {
                        GearmanService::addProcessProductToOzonNotification(json_decode($row->data));
                    }
                    elseif ($function_name == 'setOzonProductId') {
                        GearmanService::addSetOzonProductIdNotification(json_decode($row->data));
                    }

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

   public static function deleteRetryByQuery($key, $function_name)
   {
        app('db')->connection('mysql')->table('gearman_retry_queue')
            ->where('data', 'LIKE', '%"product_id":' . $key . '%')
            ->where('function_name', $function_name)
            ->where('processing', 0)
            ->delete();
   }
}