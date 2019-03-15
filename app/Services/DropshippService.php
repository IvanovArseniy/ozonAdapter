<?php
namespace App\Services;

use Log;

class DropshippService
{
    protected $baseUrl;
    protected $tokenUrl;

    protected $orderUrl;
    protected $setOrderStatusUrl;

    public function __construct() {
        $this->baseUrl = config('app.dropshipp_base_url');
        $this->tokenUrl = config('app.dropshipp_token_url');
        $this->orderUrl = config('app.dropshipp_order_url');
        $this->setOrderStatusUrl = '';
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

    public function CreateOrder(Type $var = null)
    {
        # code...
    }

    public function UpdateOrder(Type $var = null)
    {
        # code...
    }
}