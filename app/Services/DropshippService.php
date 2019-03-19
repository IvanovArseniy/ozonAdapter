<?php
namespace App\Services;

use Log;

class DropshippService
{
    protected $baseUrl;
    protected $tokenUrl;

    protected $orderUrl;
    protected $setOrderStatusUrl;

    protected $updateProductUrl;
    protected $deleteProductUrl;

    public function __construct() {
        $this->baseUrl = config('app.dropshipp_base_url');
        $this->tokenUrl = config('app.dropshipp_token_url');
        $this->orderUrl = config('app.dropshipp_order_url');
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

    public function notifyOrders($orderIds)
    {
        $notifications = app('db')->connection('mysql')->table('order_notification')
            ->whereIn('order_id', $orderIds)
            ->where('notified', 0)
            ->get();
        $successNotifications = [];
        $notificationResult = [];
        foreach ($notifications as $key => $notification) {
            if ($notification->type == 'create') {
                $result = $this->notifyNewOrder($notification->order_id);
                if (!isset($result['error'])) {
                    array_push($successNotifications, $notification->id);
                    array_push($notificationResult, $result);
                }
            }
            if ($notification->type == 'update') {
                $result = $this->notifyExistedOrder($notification->order_id, $notification->data);
                if (!isset($result['error'])) {
                    array_push($successNotifications, $notification->id);
                    array_push($notificationResult, $result);
                }
            }
            elseif ($notification->type == 'delete') {
                $result = $this->notifyDeletedOrder($notification->order_id);
                if (!isset($result['error'])) {
                    array_push($successNotifications, $notification->id);
                    array_push($notificationResult, $result);
                }
            }
            else {
                array_push($successNotifications, $notification->id);
            }
        }

        app('db')->connection('mysql')->table('order_notification')
            ->whereIn('id', $successNotifications)
            ->update(['notified' => 1]);        

        return $notificationResult;
    }
    
    protected function notifyExistedOrder($orderId, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderId, $this->orderUrl)));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($data))                                                                
        );  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderId, $this->orderUrl)));
        $response = curl_exec($ch);
        curl_close($ch);
        Log::info('Update order: ' . $response);
        return json_decode($response, true);
    }

    protected function notifyNewOrder($orderId)
    {
        Log::info('New order:' . json_encode($orderId));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderId, $this->orderUrl)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderId, $this->orderUrl)));
        $response = curl_exec($ch);
        curl_close($ch);
        Log::info('Notify new order: ' . $response);
        return json_decode($response, true);
    }

    protected function notifyDeletedOrder($orderId)
    {
        Log::info('Deleted order:' . json_encode($orderId));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderId, $this->orderUrl)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $orderId, $this->orderUrl)));
        $response = curl_exec($ch);
        curl_close($ch);
        Log::info('Notify deleted order: ' . $response);
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
}