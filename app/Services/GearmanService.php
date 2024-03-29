<?php

namespace App\Services;
use App\Http\Controllers\ChatController;
use Log;
use DateTime;

class GearmanService
{
    public static function addUpdateProductNotification($data)
    {
        $client = new \GearmanClient();
        $client->addServers(config('app.gearmman_server'));
        $client->doBackground('updateProduct', json_encode($data));
    }

    public static function addOrderNotification($data)
    {
        $client = new \GearmanClient();
        $client->addServers(config('app.gearmman_server'));
        $client->doHighBackground('processOrderNotification', json_encode($data));
    }

    public static function addProcessProductToOzonNotification($data)
    {
        $client = new \GearmanClient();
        $client->addServers(config('app.gearmman_server'));
        $client->doBackground('processProductToOzon', json_encode($data));
    }

    public static function addSetOzonProductIdNotification($data)
    {
        $client = new \GearmanClient();
        $client->addServers(config('app.gearmman_server'));
        $client->doBackground('setOzonProductId', json_encode($data));
    }
    
    public static function addForRetry($data)
    {
        $client = new \GearmanClient();
        $client->addServers(config('app.gearmman_server'));
        $client->doHighBackground('processForRetry', json_encode($data));
    }

    public static function chatEddySync($data = [])
    {
        $client = new \GearmanClient();
        $client->addServers(config('app.gearmman_server'));

        $client->doHighBackground('chatEddySync', json_encode($data));
    }

    public static function eddyChatSync($data = [])
    {
        $client = new \GearmanClient();
        $client->addServers(config('app.gearmman_server'));
        $client->doHighBackground('eddyChatSync', json_encode($data));
    }


    public static function updateProduct(\GearmanJob $job)
    {
        $data = $job->workload();
        $json_data = json_decode($data, true);
        Log::info('stocks_price gearmanWork:' . json_encode($json_data));

        $ozonService = new OzonService();
        $sendStockResult = $ozonService->sendStockAndPriceAndEnabledForProduct($json_data);

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

    public static function processRetry($json_data, $resultedData, $job)
    {
        $function_name = $job->functionName();

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
                    'function_name' => $function_name,
                    'data' => json_encode($resultedData['data']),
                    'processing' => 0,
                    'ignored' => (isset($resultedData['ignored']) && $try > 1) ? intval($resultedData['ignored']) : 0,
                    'reason' => isset($resultedData['reason']) ? $resultedData['reason'] : null,
                    'sent_date' => date('Y-m-d\TH:i:s.u')
                ]
            );
        }
    }

    public static function syncChatsWithEddy(\GearmanJob $job)
    {
        $data = $job->workload();
        $cc = new ChatController();
        $os = new OzonService();
        $es = new EddyService();
        $cc->SyncChat($os,$es);
    }
    public static function syncFromEddyToChats(\GearmanJob $job)
    {
        $data = $job->workload();
        $cc = new ChatController();
        $os = new OzonService();
        $es = new EddyService();
        $cc->SyncChatsFromHelpdesk($os,$es);
    }

   public static function processForRetry(\GearmanJob $job)
   {
        $success = false;
        try {
            app('db')->connection('mysql')->table('gearman_retry_queue')
                ->where('ignored', 0)
                ->orWhereNull('ignored')
                ->update(['processing' => 1]);

            $res = app('db')->connection('mysql')->table('gearman_retry_queue')
                ->where('processing', 1)
                ->skip(0)
                ->take(30000)
                ->get();
            if ($res) {
                foreach ($res as $key => $row) {
                    $unique_key = $row->unique_key;
                    $function_name = $row->function_name;
                    $data = $row->data;
                    if ($function_name == 'updateProduct' || $function_name == 'processStockAndPrice') {
                        GearmanService::addUpdateProductNotification(json_decode($row->data));
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
            ->where('ignored', 0)
            ->delete();

        app('db')->connection('mysql')->table('gearman_retry_queue')
            ->where('data', 'LIKE', '%"product_id":' . $key . '%')
            ->where('function_name', $function_name)
            ->where('processing', 0)
            ->whereNull('ignored')
            ->delete();
   }
}