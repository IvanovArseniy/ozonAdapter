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

    public static function processStock(\GearmanJob $job)
    {
        $data = $job->workload();
        $json_data = json_decode($data, true);
        Log::info('gearmanWork:' . json_encode($json_data));

        $ozonService = new OzonService();
        $sendStockResult = $ozonService->sendStockAndPriceForProduct($json_data);

        if (!$sendStockResult) {
            app('db')->connection('mysql')->table('gearman_retry_queue')
                ->insert(
                    [
                        'unique_key' => $job->unique(),
                        'function_name' => $job->functionName(),
                        'data' => $job->workload(),
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