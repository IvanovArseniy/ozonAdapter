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

    public function notifyOrders($notifyOrders)
    {
        $existedResult = $this->notifyExistedOrders($notifyOrders['existedOrders']);
        $newResult = $this->notifyNewOrders($notifyOrders['newOrders']);
        $deletedResult = $this->notifyDeletedOrders($notifyOrders['deletedOrders']);
        return ['existedOrders' => $existedResult, 'newOrders' => $newResult];
    }
    
    protected function notifyExistedOrders($orders)
    {
        foreach ($orders as $key => $order) {
            $data_string = json_encode([
                'oldFulfillmentStatus' => $order->oldStatus,
                'newFulfillmentStatus' => $order->newStatus
            ]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{store_num}', $order['order_id'], $this->orderUrl)));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',                                                                                
                'Content-Length: ' . strlen($data_string))                                                                
            );  
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $order['order_id'], $this->orderUrl)));
            $response = curl_exec($ch);
            curl_close($ch);
            Log::info('Update order: ' . $response);
        }
    }

    protected function notifyNewOrders($orders)
    {
        Log::info('New orders:' . json_encode($orders));
        foreach ($orders as $key => $order) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{store_num}', $order['id'], $this->orderUrl)));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $order['id'], $this->orderUrl)));
            $response = curl_exec($ch);
            curl_close($ch);
            Log::info('Notify new order: ' . $response);
        }
    }

    protected function notifyDeletedOrders($orders)
    {
        Log::info('Deleted orders:' . json_encode($orders));
        foreach ($orders as $key => $order) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken(str_replace('{store_num}', $order->id, $this->orderUrl)));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            Log::info('Url: ' . $this->baseUrl . $this->addToken(str_replace('{store_num}', $order->id, $this->orderUrl)));
            $response = curl_exec($ch);
            curl_close($ch);
            Log::info('Notify deleted order: ' . $response);
        }
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