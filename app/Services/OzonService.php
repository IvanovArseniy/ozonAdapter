<?php

namespace App\Services;
use Log;

class OzonService
{
    protected $baseUrl = config('app.ozon_base_url');

    protected $productListUrl = config('app.ozon_productlist_url');
    protected $createProductsUrl = config('app.ozon_createrpoduct_url');

    protected $orderListUrl = config('app.ozon_orderlist_url');
    protected $orderInfoUrl = config('app.ozon_orderinfo_url');
    protected $setOrderStatusUrl = '';

    protected function addHeaders($ch)
    {
        curl_setopt($ch,
            CURLOPT_HTTPHEADER,
            array('Client-Id: ' . config('app.ozon_api_client_id'), 'Api-Key: ' . config('app.ozon_api_key')));
    }

    public function getProductList($productId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->productListUrl);
        $this->addHeaders($ch);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data_string = json_encode(['product_id' => $productId]);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        Log::info('Ozon products: ' . $response);
        return $result;
    }

    public function createProduct($product)
    {
        $items = array();
        if (count($product->variants) == 0) {
            array_push($items, [
                'barcode' => strval($product->sku),
                'description' => $product->description,
                'category_id' => 17030819,
                'name' => $product->name,
                'offer_id' => $product->sku,
                'price' => strval($product->price),
                'vat'=> '0',
                'weight' => $product->weight,
                'weight_unit' => 'g',
                'images' => array([
                    'file_name' => 'https://ozon-st.cdn.ngenix.net/multimedia/c1200/1022555115.jpg',
                    'default' => true
                ])
            ]);
        }
        else {
            foreach ($product->variants as $key => $variant) {
                array_push($items, [
                    'barcode' => strval($variant->mallVariantId),
                    'description' => $product->description,
                    'category_id' => 17030819,
                    'name' => $product->name,
                    'offer_id' => $product->sku,
                    'price' => strval($variant->price),
                    'vat'=> '0',
                    'weight' => $product->weight,
                    'weight_unit' => 'g',
                    'images' => array([
                        'file_name' => 'https://ozon-st.cdn.ngenix.net/multimedia/c1200/1022555115.jpg',
                        'default' => true
                    ])
                ]);
                //app('db')->connection('mysql')->insert('insert into product (Id, ozonProductId, ozonProductChangeDate) values(?, ?, ?)', [$variant->mallVariantId, 1, date('Y-m-d H:i:s')]);
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->createProductsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data_string = json_encode(['items' => $items]);
        Log::info('Create product request to ozon:', ['items' => $items]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);  
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Client-Id: 466',
            'Api-Key: 9753260e-2324-fde7-97f1-7848ed7ed097',                                                                   
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($data_string))                                                                
        );  
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        Log::info('Create ozon product: ' . $response);
        return $result;
    }

    protected function sendProductToOzon($product)
    {

    }

    public function getOrderList()
    {
        $to = new DateTime('now');
        $since = new DateTime('now');
        $since->modify('-1 day');
        $data = [
            // 'since' => $since->format('Y-m-d') . 'T' . $since->date('H:i:s') .'.000Z',
            // 'to' => $to->format('Y-m-d') . 'T' . $to->date('H:i:s') .'.999Z',
            'since' => $since->format('Y-m-d\TH:i:s.u'),
            'to' => $to->format('Y-m-d\TH:i:s.u'),
            'delivery_schema' => 'fbs'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->orderListUrl);
        $this->addHeaders($ch);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data_string = json_encode($data);
        Log::info('Get orders from ozon:', $data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);  
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Client-Id: 466',
            'Api-Key: 9753260e-2324-fde7-97f1-7848ed7ed097',                                                                   
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($data_string))
        );  
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        Log::info('Ozon order info: ' . $response);

        $notifyOrders = ['newOrders' => array(), 'existedOrders' => array(), 'deletedOrders' => array()]];
        $ozonOrders = $result->orders;
        $existedOrders = app('db')->connection('mysql')->select('select * from order where orderId IN (' . implode(',', $result->order_ids) . ')');
        foreach ($ozonOrders as $key => $ozonOrder) {
            $existedFound = false;

            foreach ($existedOrders as $k => $existedOrder) {
                if ($ozonOrder->order_id == $existedOrder->id && $ozonOrder->status != $existedOrder->status)) {
                    $existedFound = true;
                    app('db')->connection->('mysql')->table('users')
                        ->where('id', $existedOrder->id)
                        ->update(['status' => $ozonOrder->status]);
                    array_push($notifyOrders->existedOrders, [
                        'id' => $existedOrder->id,
                        'oldStatus' => $existedOrder->status,
                        'newStatus' => $ozonOrder->status
                    ]);
                }
            }

            if (!$existedFound) {
                app('db')->connection('mysql')->table('order')->insert([
                    'id' => $ozonOrder->order_id,
                    'createdon' => date('Y-m-d\TH:i:s.u'),
                    'status' => $ozonOrder->status
                ]);
                array_push($notifyOrders->newOrders, $ozonOrder);
            }
        }

        foreach ($existedOrders as $key => $existedOrder) {
            if (!in_array($existedOrder->id, $result->order_ids)) {
                app('db')->connection('mysql')->table('order')
                    ->where('id', '=', $existedOrder->id)
                    ->delete();
                    array_push($notifyOrders->deletedOrders, $existedOrder);
            }
        }

        return $notifyOrders;
    }

    public function getOrderInfo($orderId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, str_replace('{orderId}', $orderId, $this->baseUrl . $this->orderInfoUrl));
        $this->addHeaders($ch);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        Log::info('Ozon order info: ' . $response);

        $fullItems = array();
        foreach ($result->items as $key => $item) {
            $product = app('db')->connection('mysql')->table('product')
                ->where('ozonId', $item->order_id)
                ->first();

            array_push($fullItems, [
                'product_id': $item->product_id,
                'item_id': $item->item_id,
                'quantity': $item->quantity,
                'offer_id': $item->offer_id,
                'price': $item->price,
                'tracking_number': $item->tracking_number,
                'status': $item->status,
                'cancel_reason_id': $item->cancel_reason_id,
                'auto_cancel_date': $item->auto_cancel_date,
                'shipping_provider_id': $item->shipping_provider_id,
                'name' => $product->name,
                'imageUrl' => $product->imageUrl,
                //'smallThumbnailUrl' => $product->smallThumbnailUrl ?? 
            ]);
        }

        $result->items = $fullItems;

        return $result;
    }

    public function setOrderStatus($orderId)
    {
        // $result = $this->getOrderInfo($orderId);
        // return $result->status;
    }
}