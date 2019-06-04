<?php
namespace App\Services;

use Log;

class DropshippService
{
    protected $baseUrl;
    protected $tokenUrl;

    protected $orderUrl;
    protected $orderUrlAction;
    protected $setOrderStatusUrl;

    protected $updateProductUrl;
    protected $deleteProductUrl;

    public function __construct() {
        $this->baseUrl = config('app.dropshipp_base_url');
        $this->tokenUrl = config('app.dropshipp_token_url');
        $this->orderUrl = config('app.dropshipp_order_url');
        $this->orderUrlAction = config('app.dropshipp_order_url_action');
        $this->setOrderStatusUrl = '';
        $this->updateProductUrl = config('app.dropshipp_updateproduct_url');
    }

    protected function addToken($url)
    {
        $tokenUrl = str_replace('{api_key}', config('app.dropshipp_key'), $this->tokenUrl);
        $tokenUrl = str_replace('{owner_token}', config('app.dropshipp_owner_token'), $tokenUrl);
        $url = $url . $tokenUrl;
        return $url;
    }

    public function notifyOrders()
    {
        $notifications = app('db')->connection('mysql')
            ->select('select no.id as id, no.type as type, no.data as data, o.ozon_order_id as ozonOrderId, o.ozon_order_nr as ozonOrderNr from order_notification no
                left join orders o on o.ozon_order_id = no.order_id
                where notified = 0 order by no.id asc limit 1');

        $notificationResult = [];
        $created = 0;
        $approved = 0;
        $declined = 0;
        foreach ($notifications as $key => $notification) {
            $success = false;
            if ($notification->type == 'create') {
                $result = $this->notifyNewOrder($notification->ozonOrderNr);
                if (!isset($result['error'])) {
                    $created++;
                    $notificationResult['created'] = $created;
                    $success = true;
                }
            }
            if ($notification->type == 'update') {
                $result = $this->notifyExistedOrder($notification->ozonOrderNr, $notification->data);
                if (!isset($result['error'])) {
                    array_push($notificationResult, $result);
                    $success = true;
                }
            }
            elseif ($notification->type == 'delete') {
                $result = $this->notifyDeletedOrder($notification->ozonOrderNr);
                if (!isset($result['error'])) {
                    array_push($notificationResult, $result);
                    $success = true;
                }
            }

            if ($notification->type == 'approve') {
                $result = $this->ApproveOrder($notification->ozonOrderNr);
                if (!isset($result['error'])) {
                    $approved++;
                    $notificationResult['approved'] = $approved;
                    $success = true;
                }
            }

            if ($notification->type == 'decline') {
                $result = $this->DeclineOrder($notification->ozonOrderNr);
                if (!isset($result['error'])) {
                    $declined++;
                    $notificationResult['declined'] = $declined;
                    $success = true;
                }
            }

            app('db')->connection('mysql')->table('order_notification')
                ->where('id', $notification->id)
                ->update(['notified' => 1]);     

            if (!$success) {
                $orderNotificationResult = app('db')->connection('mysql')->table('order_notification')
                    ->where('order_id', $notification->ozonOrderId)
                    ->where('type', $notification->type)
                    ->where('notified', 0)
                    ->first();
                if (!$orderNotificationResult) {
                    app('db')->connection('mysql')->table('order_notification')
                    ->insert([
                        'data' => null,
                        'type' => $notification->type,
                        'notified' => 0,
                        'order_id' => $notification->ozonOrderId
                    ]);   
                }
            }

        }

        return $notificationResult;
    }

    public function notifyOrder($notification)
    {
        if (!is_array($notification)) {
            $notification = json_decode($notification,true);
        }
        if (!is_array($notification)) {
            return false;
        }

        $success = false;
        if ($notification['type'] == 'create') {
            $result = $this->notifyNewOrder($notification['order_nr']);
            if (!isset($result['error'])) {
                $success = true;
            }
        }
        elseif ($notification['type'] == 'update') {
            $result = $this->notifyExistedOrder($notification['order_nr'], $notification['data']);
            if (!isset($result['error'])) {
                $success = true;
            }
        }
        elseif ($notification['type'] == 'delete') {
            $result = $this->notifyDeletedOrder($notification['order_nr']);
            if (!isset($result['error'])) {
                $success = true;
            }
        }
        elseif ($notification['type'] == 'approve') {
            $result = $this->ApproveOrder($notification['order_nr']);
            if (!isset($result['error'])) {
                $success = true;
            }
        }
        elseif ($notification['type'] == 'decline') {
            $result = $this->DeclineOrder($notification['order_nr']);
            if (!isset($result['error'])) {
                $success = true;
            }
        }

        return [
            'result' => $success,
            'data' => $notification
        ];
    }
    
    protected function notifyExistedOrder($orderNr, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderNr, $this->orderUrl)));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($data))                                                                
        );  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderNr, $this->orderUrl)));
        $response = curl_exec($ch);
        curl_close($ch);
        Log::info('Update order: ' . $response);
        return json_decode($response, true);
    }

    protected function notifyNewOrder($orderNr)
    {
        Log::info('New order:' . json_encode($orderNr));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderNr, $this->orderUrl)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderNr, $this->orderUrl)));
        $response = curl_exec($ch);
        curl_close($ch);
        Log::info('Notify new order: ' . $response);
        return json_decode($response, true);
    }

    protected function notifyDeletedOrder($orderNr)
    {
        Log::info('Deleted order:' . json_encode($orderNr));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderNr, $this->orderUrl)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderNr, $this->orderUrl)));
        $response = curl_exec($ch);
        curl_close($ch);
        Log::info('Notify deleted order: ' . $response);
        return json_decode($response, true);
    }

    public function ApproveOrder($orderNr)
    {
        Log::info('Approve order:' . json_encode($orderNr));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace(['{store_num}','{action}'], [$orderNr, 'approve'], $this->orderUrlAction)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderNr, $this->orderUrl) . '/approve'));
        $response = curl_exec($ch);
        curl_close($ch);
        Log::info('Approve new order: ' . $response);
        return json_decode($response, true);
    }

    public function DeclineOrder($orderNr)
    {
        Log::info('Decline order:' . json_encode($orderNr));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace(['{store_num}','{action}'], [$orderNr, 'decline'], $this->orderUrlAction)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderNr, $this->orderUrl) . '/decline'));
        $response = curl_exec($ch);
        curl_close($ch);
        Log::info('Decline new order: ' . $response);
        return json_decode($response, true);
    }

    public function getOrderList(Type $var = null)
    {
        # code...
    }

    public function getOrderInfo(Type $var = null)
    {
        # code...
    }

    public function notifyProducts($productIds)
    {
        $notifications = app('db')->connection('mysql')->table('product_notification')
            ->whereIn('product_id', $productIds)
            ->where('notified', 0)
            ->get();
        $successNotifications = [];
        foreach ($notifications as $key => $notification) {
            if ($notification->type == 'update') {
                $result = $this->updateProduct($notification->product_id, $notification->data);
                if (!isset($result['error'])) {
                    array_push($successNotifications, $notification->id);
                }
            }
            elseif ($notification->type == 'delete') {
                $result = $this->deleteProduct($notification->product_id);
                if (!isset($result['error'])) {
                    array_push($successNotifications, $notification->id);
                }
            }
            else {
                array_push($successNotifications, $notification->id);
            }
        }

        app('db')->connection('mysql')->table('product_notification')
            ->whereIn('id', $successNotifications)
            ->update(['notified' => 1]);
    }

    protected function updateProduct($productId, $data)
    {
        Log::info('Update product:' . $productId . '=>' . $data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{product_id}', $productId, $this->updateProductUrl)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        $headers = ['Content-Type: application/json', 'Content-Length: ' . strlen($data)];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{product_id}', $productId, $this->updateProductUrl)));
        $response = curl_exec($ch);
        curl_close($ch);
        Log::info('Update product result: ' . $response);
        return json_decode($response, true);
    }

    protected function deleteProduct($productId)
    {
        Log::info('Deleted product:' . $productId);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{product_id}', $productId, $this->updateProductUrl)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{product_id}', $productId, $this->updateProductUrl)));
        $response = curl_exec($ch);
        curl_close($ch);
        Log::info('Delete product result: ' . $response);
        return json_decode($response, true);
    }

    public static function gearmanWork(\GearmanJob $job)
    {
        $json_data = $job->workload();
        $json_data = json_decode($json_data, true);

        Log::info('gearmanWork:' . json_encode($json_data));

        file_put_contents(storage_path() . '/app/gearmant_test', json_encode($json_data));
    }
}