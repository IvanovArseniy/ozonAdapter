<?php

namespace App\Services;
use Log;

class OzonService
{
    protected $baseUrl = config('app.ozon_base_url');

    protected $productListUrl = config('app.ozon_productlist_url');
    protected $createProductsUrl = config('app.ozon_createrpoduct_url');
    protected $updateStocksUrl = config('app.ozon_update_stocks_url');

    protected $orderListUrl = config('app.ozon_orderlist_url');
    protected $orderInfoUrl = config('app.ozon_orderinfo_url');
    protected $setOrderStatusUrl = '';

    public function getProductList($productId)
    {
        $ozonProductResult = $this->getOzonProductId($productId);
        if ($ozonProductResult->Success) {
            $interactionId = com_create_guid();
            Log::info($interactionId . ' => Get product info from ozon:' . $productId);
            $response = $this->sendData($this->productListUrl, ['product_id' => $productId]);
            Log::info($interactionId . ' => Ozon products: ' . $response);
            $result = json_decode($response, true);
            return $result;
        }
        else return $ozonProductResult;
    }

    public function createProduct($product)
    {
        $items = array();
        $this->addProductToRequest($items, 0, $product->sku, $product->description, $product->name, $product->price, $product->weight, $product->quantity, $product->unlimited, $product->enabled);
        if (count($product->variants) > 0) {
            foreach ($product->variants as $key => $variant) {
                $this->addProductToRequest($items, $variant->mallVariantId, $product->sku, $product->description, $product->name, $variant->price, $variant->inventory, $product->weight, $product->unlimited, $product->enabled);
            }
        }

        $interactionId = com_create_guid();
        Log::info($interactionId . ' => Create product request to ozon:' , json_encode(['items' => $items]);
        $response = $this->sendData($this->createProductsUrl, ['items' => $items]);
        Log::info($interactionId . ' => Create ozon products: ' . $response);
        $result = json_decode($response, true);

        $productId = 0;
        $quantityItems = array();
        for ($i=0; $i < count($result->result); $i++) { 
            app('db')->connection('mysql')->table('product_variants')
                ->insert([
                    'ozonProductId' => $result->result[i],
                    'productId' => $items[i]->dropshippProductId,
                    'variantId' => $items[i]->dropshippVariantId
                ]);

            array_push($quantityItems, [
                'product_id' => $result->result[i],
                'stock' => $items[i]->quantity
            ]);

            $productId = $items[i]->productId;
        }

        $this->setQuantity($quantityItems);

        return $productId;
    }

    protected function addProductToRequest($items, $dropshippVariantId, $sku, $description, $name, $price, $weight, $quantity, $unlimited, $enabled);
    {
        $pdo = app('db')->connection('mysql')->getPdo();
        $result = app('db')-connection('mysql')->table('product')
            ->insert([
                'name' => $name,
                'sku' => $sku,
                'enabled' => $enabled
            ]);
        if ($result)  {
            $productId = $pdo->lastInsertId();

            array_push($items, [
                'productId' => $prductId,
                'dropshippVariantId' => $dropshippVariantId,
                'barcode' => strval($sku),
                'description' => $description,
                'category_id' => config('app.active_category_id'),
                'name' => $name,
                'offer_id' => $sku,
                'price' => strval($price),
                'vat'=> '0',
                'weight' => $weight,
                'weight_unit' => 'g',
                'quantity' => $quantity,
                'images' => array([
                    'file_name' => 'https://ozon-st.cdn.ngenix.net/multimedia/c1200/1022555115.jpg',
                    'default' => true
                ]),
                'attributes' => array([
                    'id' => config('app.ozon_product_group_attribute'),
                    'value' => $name . '(' . $sku . ')'
                ]),
                "visibility_details": [
                    'has_price': true,
                    'has_stock': $unlimited,
                    'active_product': $enabled
                ]
            ]);
        }
        else
        {
            Log::error('Insert product to database failed' . $result);
        }

        return $items;
    }

    protected function setQuantity($items)
    {
        $interactionId = com_create_guid();
        Log::info($interactionId . ' => Update stocks request to ozon:', json_encode(['stocks' => $items]));
        $response = $this->sendData($this->updateStocksUrl, ['stocks' => $items]);
        Log::info($interactionId . ' => Update stocks response: ' . $response);
        $result = json_decode($response, true);
    }

    public function createProductCombination($productId, $combination)
    {
        $ozonProduct = $this->getProductList($ozonProductId);
    }

    protected function getOzonProductId($productId)
    {
        $product = app('db')->connection('mysql')->table->('product')->where('dropshippId', $productId)->first();
        if ($product) {
            return [
                'Success' => true,
                'Id' => $product->ozonProductId
            ];
        }
        else {
            return [
                'Success' => false,
                'Error' => 'Product with ID ' . $productId . ' doesn\'t exists!'
            ];
        }
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

        $interactionId = com_create_guid();
        Log::info($interactionId . ' => Get orders from ozon:', $data);
        $response = $this->sendData($this->orderListUrl, $data);
        Log::info($interactionId . ' => Ozon order info: ' . $response);
        $result = json_decode($response, true);

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
        $interactionId = com_create_guid();
        Log::info($interactionId . ' => Get order info from ozon: ' . $orderId);
        $response = $this->sendData(str_replace('{orderId}', $orderId, $this->orderInfoUrl));
        Log::info($interactionId . ' => Ozon order info: ' . $response);
        $result = json_decode($response, true);

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
        //TODO:set status in ozon
        // $result = $this->getOrderInfo($orderId);
        // return $result->status;
    }

    protected sendData($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            'Client-Id: ' . config('app.ozon_api_client_id'),
            'Api-Key: ' . config('app.ozon_api_key')
        );
        if ($data) {
            array_push($headers, ['Content-Type: application/json', 'Content-Length: ' . strlen($data_string)]);
            $data_string = json_encode(['items' => $items]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}