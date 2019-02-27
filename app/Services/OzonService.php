<?php

namespace App\Services;
use Log;

class OzonService
{
    protected $baseUrl = config('app.ozon_base_url');

    protected $productInfoUrl = config('app.ozon_productinfo_url');
    protected $createProductsUrl = config('app.ozon_createrpoduct_url');
    protected $updateStocksUrl = config('app.ozon_update_stocks_url');
    protected $updateProductUrl = config('app.ozon_updateproduct_url');
    protected $activateProductUrl = config('app.activate_product_url');
    protected $deactivateProductUrl = config('app.deactivate_product_url');
    protected $updatePricesUrl = config('app.update_productprices_url');

    protected $orderListUrl = config('app.ozon_orderlist_url');
    protected $orderInfoUrl = config('app.ozon_orderinfo_url');
    protected $approveOrderUrl = config('app.ozon_approveorder_url');
    protected $cancelOrderUrl = config('app.ozon_cancelorder_url');

    protected $categoryListUrl = config('app.ozon_categorylist_url');
    
    public function getProductFullInfo($productId)
    {
        //Get mall items here and make response
        return $this->getProductInfo($productId, 0);
    }

    public function getProductInfo($productId, $mallVariantId)
    {
        if(is_null($mallVariantId)) {
            $mallVariantId = 0;
        }
        $ozonProductResult = $this->getOzonProductId($productId, $mallVariantId);
        if ($ozonProductResult->Success) {

            $response = $this->getProductFromOzon($ozonProductResult->ozonProductId);
            $result = json_decode($response, true);
            $result->->result->productVariant = [
                'id' => $ozonProductResult->id,
                'mallVariantId' => $ozonProductResult->mallVariantId,
                'productId' => $ozonProductResult->productId,
                'ozonProductId' => $ozonProductResult->ozonProductId
            ];
            return $result;
        }
        else return $ozonProductResult;
    }

    protected function getProductFromOzon($ozonProductId)
    {
        $interactionId = com_create_guid();
        Log::info($interactionId . ' => Get product info from ozon:' . $ozonProductId);
        $response = $this->sendData($this->productInfoUrl, ['product_id' => $ozonProductId]);
        Log::info($interactionId . ' => Ozon products: ' . $response);
        return $response;
    }

    public function createNewProduct($product)
    {
        $productId = 0;
        $pdo = app('db')->connection('mysql')->getPdo();
        $result = app('db')-connection('mysql')->table('product')
            ->insert([
                'name' => $product->name,
                'sku' => $product->sku,
                'enabled' => $product->enabled
            ]);
        }
        if ($result)  {
            $productId = $pdo->lastInsertId();
        }
        else
        {
            Log::error('Insert product to database failed' . $result);
            return [
                'Error' => 'Failed to insert product to database'
            ];
        }

        return $this->createProduct($product, $productId);
    }

    protected function createProduct($product, $productId)
    {
        $result = app('db')-connection('mysql')->table('product')
        ->where([
            'id' => $productId,
        ])->first();
        if (!$result)  {
            Log::error('Product with Id ' . $productId . 'doesn\'t exists!');
            return [
                'Error' => 'Product with Id ' . $productId . 'doesn\'t exists!'
            ];
        }  

        $items = array();
        $this->addProductToRequest(
            $items,
            0,
            $product->sku,
            $product->description,
            $product->name, $product->price,
            $product->weight,
            $product->quantity,
            $product->unlimited,
            $product->enabled,
            $product->categoryId,
            null,
            null,
            $productId
        );
        if (count($product->variants) > 0) {
            foreach ($product->variants as $key => $variant) {
                $this->addProductToRequest(
                    $items,
                    $variant->mallVariantId,
                    $product->sku,
                    $product->description,
                    $product->name,
                    $variant->price,
                    $variant->inventory,
                    $product->weight,
                    $product->unlimited,
                    $product->enabled,
                    $product->categoryId,
                    $variant->color,
                    $varoant->size,
                    $productId
                );
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
                    'mallVariantId' => $items[i]->dropshippVariantId
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

    protected function addProductToRequest($items, $dropshippVariantId, $sku, $description, $name, $price, $weight, $quantity, $unlimited, $enabled, $categoryId, $color, $size, $productId);
    {
        $ozonCategoryId = $this->getOzonCategory($categoryId);
        $attributes = array([
            'id' => config('app.ozon_product_group_attribute'),
            'value' => $name . '(' . $sku . ')'
        ]);
        if (!is_null($color)) {
            $colorAttributeId = $this->getAttribute($ozonCategoryId, $color);
            if (!is_null($colorAttributeId)) {
                appay_push($attributes, [
                    'id' => $colorAttributeId,
                    'value' => $color
                ]);
            }
        }
        if (!is_null($size)) {
            $sizeAttributeId = $this->getAttribute($ozonCategoryId, $size);
            if (!is_null($sizeAttributeId)) {
                appay_push($attributes, [
                    'id' => $sizeAttributeId,
                    'value' => $size
                ]);
            }
        }

        array_push($items, [
            'dropshippProductId' => $productId,
            'dropshippVariantId' => $dropshippVariantId,
            'barcode' => strval($sku),
            'description' => $description,
            'category_id' => config('app.active_category_id'),
            'name' => $name,
            'offer_id' => $productId,
            'price' => strval($price),
            'vat'=> '0',
            'weight' => $weight,
            'weight_unit' => 'g',
            'quantity' => $quantity,
            'images' => array([
                'file_name' => 'https://ozon-st.cdn.ngenix.net/multimedia/c1200/1022555115.jpg',
                'default' => true
            ]),
            'attributes' => $attributes,
            "visibility_details": [
                'has_price': true,
                'has_stock': $unlimited,
                'active_product': $enabled
            ]
        ]);

        return $items;
    }

    public function getOzonCategory($categoryId)
    {
        $ozonCategoryId = $this->classifyCategory($categoryId);
        if (is_null($ozonCategoryId)) {
            $result = app('db')->connection('mysql')->select('select top 1 oc.id as ozonCategoryId from ozonCategory oc
                left join category c on c.ozonCategoryId = oc.id where c.id = \'' . $categoryId . '\'');
            if (!is_null($result)) {
                return $result->ozonCategoryId;
            }
            else return config('app.active_category_id');
        }
        else return $ozonCategoryId;
    }

    protected function classifyCategory($categoryId)
    {
        return null;
    }

    protected function getAttribute($ozonCategoryId, $attributeValue)
    {
        $result = app('db')->connection('mysql')->select('
            select top 1 a.id as attributeId from ozonCategory oc
                inner join category_attribute ca on oc.id = ca.categoryid
                inner join attribute a on ca.attributeid = a.id
                inner join attribute_value av on a.id = av.attributeid
                where av.value =\'' . $value . '\'
                    and oc.id = \'' . $ozonCategoryId .'\'
        ');
        if (!$result)  {
            Log::error('Attribute with value ' . $attributeValue . 'doesn\'t exists!');
            return null;
        }  
        return $result->attributeId;
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
        $ozonProductResult = $this->getProductInfo($productId);
        if (!is_null($ozonProductResult->result)) {
            $result = $this->createProduct([
                'dropshippProductId' => $productId,
                'dropshippVariantId' => $combination->mallVariantId,
                'barcode' => $ozonProductResult->result->barcode,
                'categiry_id' => $ozonProductResult->result->category_id,
                'name' => $ozonProductResult->result->name,
                'offer_id' => $ozonProductResult->result->offer_id,
                'price' => $combination->price,
                'vat' => 0,
                'quantity' => $combination->inventory,
                'images' => $ozonProductResult->result->images,
                'attributes' => array([
                    'id' => config('app.ozon_product_group_attribute'),
                    'value' => $ozonProductResult->result->name . '(' . $ozonProductResult->result->barcode . ')'
                ]),
                "visibility_details": $ozonProductResult->result->visibility_details
            ], $productId);
            return $result;
        }
        else return $ozonProductResult;
    }

    protected function getOzonProductId($productId, $dropshippVariantId)
    {
        $productVariant = app('db')->connection('mysql')->table('product_variants')
            ->where('deleted', 0)
            ->where('dropshippId', $productId)
            ->where('mallVariantId', $drodshippVariantId)
            ->first();
        if ($productVariant) {
            return [
                'Success' => true,
                'id' => $productVariant->id,
                'ozonProductId' => $productVariant->ozonProductId,
                'mallVariantId' => $productVariant->mallVariantId,
                'productId' => $productVariant->productId
            ];
        }
        else {
            return [
                'Success' => false,
                'Error' => 'Product with ID ' . $productId . ' doesn\'t exists!'
            ];
        }
    }

    public function updateProduct($product, $productId)
    {
        $result = $this->updateOzonProduct($product, $productId, 0);

        if (!is_null($product->variants)) {
            foreach ($product->variants as $key => $variant) {
                if (!is_null($variant->price) && !is_null($variant->inventory)) {
                    $item = array();
                    if (!is_null($product->name)) {
                        array_push($item, ['name' => $product->name]);
                    }
                    if (!is_null($product->description)) {
                        array_push($item, ['description' => $product->description]);
                    }
                    if (!is_null($product->enabled)) {
                        array_push($item, ['enabled' => $product->enabled]);
                    }
                    if (!is_null($variant->price)) {
                        array_push($item, ['price' => $variant->price]);
                    }
                    if (!is_null($variant->inventory)) {
                        array_push($item, ['inventory' => $variant->inventory]);
                    }
                    $this->updateOzonProduct($item, $productId, $variant->mallVariantId);
                }
            }
        }
        
        return $result;
    }

    protected function updateOzonProduct($product, $productId, $mallVariantId)
    {
        if (is_null($mallVariantId)) {
            $mallVariantId = 0;
        }
        $ozonProductResult = $this->getProductInfo($productId, $mallVariantId);
        if (!is_null($ozonProductResult->result)) {
            $interactionId = com_create_guid();

            $request = ['product_id' => $ozonProductResult->result->Id];
            if (!is_null($product->name)) {
                array_push($request, ['name' => $product->name]);
            }
            if (!is_null($product->description)) {
                array_push($request, ['description' => $product->description]);
            }
            if(!is_null($product->images)) {
                $imagesResult = $this->compareImages(
                    $ozonProductResult->result->images,
                    $product->images,
                    $ozonProductResult->result->productVariant->id
                );
                if (count($imagesResult->images) > 0) {
                    array_push($request, ['images' => $imagesResult->images]);
                }
            }

            if (count($request) > 1) {
                Log::info($interactionId . ' => Update product request to ozon:', json_encode($request));
                $response = $this->sendData($this->updateProductUrl, $request);
                Log::info($interactionId . ' => Update product response: ' . $response);
                $result = json_decode($response, true);
            }

            $this->setQuantity([
                'product_id' => $ozonProductResult->result->Id,
                'stock' => $product->quantity
            ]);

            if (!is_null($product->enabled) && $product->enabled) {
                $response = $this->activateProduct($ozonProductResult->result->Id);
            }
            else if (!is_null($product->enabled) && !$product->enabled){
                $response = $this->deactivateProduct($ozonProductResult->result->Id);
            }

            $this->setPrices([
                'product_id' => $ozonProductResult->result->Id,
                'price' => $product->price,
                'old_price' => $ozonProductResult->result->old_price,
                'vat' => $ozonProductResult->result->vat
            ]);

            return ['productId' => $productId, 'imageIds' => $imagesResult->imageIds];
        }
        else return $ozonProductResult;
    }

    protected function setPrices($items)
    {
        $interactionId = com_create_guid();
        Log::info($interactionId . ' => Update prices request to ozon:', json_encode(['prices' => $items]));
        $response = $this->sendData($this->updatePricesUrl, ['prices' => $items]);
        Log::info($interactionId . ' => Update prices response: ' . $response);
        $result = json_decode($response, true);
    }

    public function deleteProduct($productId)
    {
        $products = app('db')->connection('mysql')->select('select * from product_variants where deleted = 0 and productId = ' . $productId);
        foreach ($products as $key => $product) {
            $this->deactivateProduct($product->getOzonProductId);
        }
        app('db')->connection('mysql')->table('product_variants')->where('productid', $productId)->update(['deleted' => 1]);        
        return $ozonProductResult;
    }

    protected function activateProduct($ozonProductId)
    {
        Log::info($interactionId . ' => Activate ozon product: ' . $ozonProductId);
        $response = $this->sendData($this->activateProductUrl, ['product_id' => $ozonProductId]);
        Log::info($interactionId . ' => Activate ozon product result: ' . $response);
        return $response;
    }

    protected function deactivateProduct($ozonProductId)
    {
        Log::info($interactionId . ' => Deactivate ozon product: ' . $ozonProductId);
        $response = $this->sendData($this->deactivateProductUrl, ['product_id' => $ozonProductId]);
        Log::info($interactionId . ' => Deactivate ozon product result: ' . $response);
        return $response;
    }

    public function addMainImage($productId, $image)
    {
        $result = $this->updateOzonProduct(['image' => array([
            'file_name' => $image->externalUrl,
            'default' => true
        ])], $productId);
        return $result->imageIds;
    }

    public function addGalleryImage($image, $productId)
    {
        $result = $this->updateOzonProduct(['image' => array([
            'file_name' => $image->externalUrl,
            'default' => false
        ])], $productId);
        return $result->imageIds;
    }

    public function addGalleryImageForCombination($image, $productId, $mallVariantId)
    {
        $result = $this->updateOzonProduct(['image' => array([
            'file_name' => $image->externalUrl,
            'default' => false
        ])], $productId, $mallVariantId);
        return $result->imageIds;
    }

    public function deleteGalleryImage($imageId, $productId)
    {
        $ozonProductInfo = $this->getProductInfo($productId, 0);
        $imageResult = $this->getImages($ozonProductInfo->result->images);
        $images = array();
        $result;
        foreach ($imageResult as $key => $image) {
            if ($image->id == $imageId && count($imageResult) == 1) {
                $result = [
                    'Error' => 'Can\'t delete last image';
                ];
            }
            else if($image->id == $imageId && count($imageResult) > 1) {
                if ($image->default) {
                    $result = [
                        'Error' => 'Can\'t delete main image'
                    ];
                }
                else {
                    $result = app('db')->connection('mysql')->table('image')
                        ->where('id', $imageId)->update(['deleted' => 1]);
                }
            }
            else {
                array_push($images, [
                    'file_name' => $image->file_name,
                    'default' => $image->default
                ]);
            }
        }
        $this->updateOzonProduct(['images' => $images], $productId);

        return $result;
    }

    protected function compareImages($existedImages, $newImages, $productVariantId)
    {
        $resultImages = $newImages;
        $insertedImages = array();

        $imageResult = $this->getImages($existedImages);
        if ($imageResult) {
            $newMainImageExists = false;
            foreach ($newImages as $key => $newImage) {
                if ($newImage->default) {
                    $newMainImageExists = true;
                }

                $imageExists = false;
                foreach ($imageResult as $key => $image) {
                    if($mage->file_name == newImage->file_name) {
                        $imageExists = true;
                    }
                }
                if (!$image_exists) {
                    $newImageId = $this->saveImage($productVariantId, $newImage);
                    array_push($insertedImages, $newImageId);
                }
            }

            foreach ($imageResult as $key => $image) {
                $imageInArray = false;
                foreach ($newImages as $key => $newImage) {
                    if ($newImage->file_name == $image->file_name) {
                        $imageInArray = true;
                        break;
                    }
                }
                if(!$imageInArray) {
                    if($newMainImageExists) {
                        array_push($resultImages, [
                            'file_name' => $image->file_name,
                            'default' => false
                        ]);
                    }
                    else {
                        array_push($resultImages, [
                            'file_name' => $image->file_name,
                            'default' => $image->default
                        ]);
                    }

                }
    
            }

            return ['images' => $resultImages, 'imageIds' => $insertedImages];
        }


        $imageResult = $this->saveImage($ozonProductResult->productVariant->id, $product->images[0]);
    }

    protected function getImages($imageFilenames) {
        $imageResult = app('db')->connection('mysql')
            ->select('select * from image where deleted = 0 and file_name IN (' . implode('\',\'', $imageFilenames) . ')');
        if ($imageResult) {
            return $iamgeResult;
        }
        else return array();
    }

    protected function saveImage($productVariantId, $image)
    {
        $pdo = app('db')->connection('mysql')->getPdo();
        $result = app('db')->connection('mysql')->table('image')
            ->insert([
                'file_name' => $image->file_name,
                'default' => $image->default,
                'product_variant_id' => $productVariantId,
                'deleted' => 0
            ]);

        if ($result)  {
            $imageId = $pdo->lastInsertId();
        }
        else
        {
            Log::error('Insert image to database failed' . $result);
            return $result;
        }
        return $imageId;
    }




    ////Orders
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
            'delivery_schema' => 'CB'
        ];

        $interactionId = com_create_guid();
        Log::info($interactionId . ' => Get orders from ozon:', $data);
        $response = $this->sendData($this->orderListUrl, $data);
        Log::info($interactionId . ' => Ozon order info: ' . $response);
        $result = json_decode($response, true);

        $notifyOrders = ['newOrders' => array(), 'existedOrders' => array(), 'deletedOrders' => array()]];
        $ozonOrders = $result->orders;
        $existedOrders = app('db')->connection('mysql')->select('select * from order where deleted = 0 and ozonOrderId IN (' . implode(',', $result->order_ids) . ')');
        foreach ($ozonOrders as $key => $ozonOrder) {
            $orderExists = false;

            foreach ($existedOrders as $k => $existedOrder) {
                if ($ozonOrder->order_id == $existedOrder->id && $ozonOrder->status != $existedOrder->status)) {
                    $orderExists = true;
                    array_push($notifyOrders->existedOrders, [
                        'id' => $existedOrder->id,
                        'oldStatus' => $this->mapOrderStatus($existedOrder->status),
                        'newStatus' => $this->mapOrderStatus($ozonOrder->status)
                    ]);
                    app('db')->connection->('mysql')->table('users')
                        ->where('id', $existedOrder->id)
                        ->update(['status' => $ozonOrder->status]);
                }
            }

            if (!$orderExists) {
                array_push($notifyOrders->newOrders, $ozonOrder);
                app('db')->connection('mysql')->table('order')->insert([
                    'ozonOrderId' => $ozonOrder->order_id,
                    'createDate' => date('Y-m-d\TH:i:s.u'),
                    'status' => $ozonOrder->status,
                    'deleted' = 0
                ]);
            }
        }

        foreach ($existedOrders as $key => $existedOrder) {
            if (!in_array($existedOrder->id, $result->order_ids)) {
                app('db')->connection('mysql')->table('order')
                    ->where('id', $existedOrder->id)
                    ->update(['deleted' => 1]);
                    array_push($notifyOrders->deletedOrders, $existedOrder);
            }
        }

        return $notifyOrders;
    }

    public function getOrderInfo($orderId)
    {
        $interactionId = com_create_guid();

        $orderResult = app('db')->connection('mysql')->table('order')
            ->where('id', $orderId)->first();
        if (!$orderResult)  {
            Log::error('Order with Id ' . $orderId . 'doesn\'t exists!');
            return [
                'Error' => 'Product with Id ' . $orderId . 'doesn\'t exists!'
            ];
        } 

        Log::info($interactionId . ' => Get order info from ozon: ' . $orderResult->ozonOrderId);
        $response = $this->sendData(str_replace('{orderId}', $orderResult->ozonOrderId, $this->orderInfoUrl));
        Log::info($interactionId . ' => Ozon order info: ' . $response);
        $order = json_decode($response, true);

        if (!is_null($order->result)) {
            $order->result->createDate = $orderResult->createDate;
            $fullItems = array();
            foreach ($order->result->items as $key => $item) {
                $product = app('db')->connection('mysql')->table('product_variant')
                    ->where('ozonProductId', $item->product_id)->first();
                $productResponse = $this->getProductFromOzon($item->product_id);
                $product = json_decode($productResponse, true);
                $productName = '';
                $productImage = '';
                if (!is_null($product->result)) {
                    $productName = $product->result->name;
                    $productImage = $product->result->images[0];
                }

                array_push($fullItems, [
                    'product_id': $product->productid,
                    'item_id': $item->item_id,
                    'quantity': $item->quantity,
                    'offer_id': $item->offer_id,
                    'price': $item->price,
                    'tracking_number': $item->tracking_number,
                    'status': $item->status,
                    'cancel_reason_id': $item->cancel_reason_id,
                    'auto_cancel_date': $item->auto_cancel_date,
                    'shipping_provider_id': $item->shipping_provider_id,
                    'name' => $productName,
                    'imageUrl' => $productImage,
                    'smallThumbnailUrl' => $productImage
                ]);
            }

            $order->result->items = $fullItems;
            return $this->mapOrder($order->result);
        }
        return $order;
    }

    protected function mapOrder($order)
    {
        $status = $this->mapOrderStatus($order->status);
        $response = [
            'paymentStatus': 'PAID',
            'fulfillmentStatus' => $status,
            'email' => $order->address->email,
            //'ipAddress': {ipAddress}, ??
            'createDate': $order->createDate,
            'refererUrl' => 'http://ozon.ru/',
            'shippingPerson' => [
                'name' => $order->adress->addressee,
                'phone' => $order->adress->phone,
                'postalCode' => $order->adress->zip_code,
                'city' => $order->adress->city,
                'street' => $order->adress->address_tail,
                //'countryCode': {countryCode},
                //'stateOrProvinceName': {stateOrProvinceName}, // область, край округ и т.п.
            ],
            'items' => array()
        ];

        foreach ($order->items as $key => $item) {
            array_push($response->items, [
                'price' => $item->price,
                'quantity' => $item=>quantity,
                'name': $item->name,
                'imageUrl': $item->imageUrl,
                'smallThumbnailUrl': $item->smallThumbnailUrl,
                //'shipping': {shipping}, // Стоимость доставки рассчитанная для этой позиции
                //'description': {description}
            ]);
        }
        return $response;
    }

    protected function mapOrderStatus($status)
    {
        return config('app.order_status.' . $status);
    }

    public function setOrderStatus($orderId, $status)
    {

        $status->fulfillmentStatus;
        $order = $this->getOrderInfo($orderId);
        if (!is_null($order->result)) {
            $items = array();
            foreach ($order->result->items as $key => $item) {
                array_push($items, $item->item_id;)
            }

            if($status == config('app.order_approve_status'))
            {
                $interactionId = com_create_guid();
                Log::info($interactionId . ' => Approve ozon order:' . $ozonProductId);
                $response = $this->sendData($this->approveOrderUrl, [
                    'order_id' => $order->result->order_id,
                    'item_ids' => $items
                ]);
                Log::info($interactionId . ' => Approve ozon order result: ' . $response);
            }
            if($status == config('app.order_cancel_status'))
            {
                $interactionId = com_create_guid();
                Log::info($interactionId . ' =>Cancel ozon order:' . $ozonProductId);
                $response = $this->sendData($this->cancelOrderUrl, [
                    'order_id' => $order->result->order_id,
                    'reason_code' => config('app.order_cancel_reason'),
                    'item_ids' => $items
                ]);
                Log::info($interactionId . ' => Cancel ozon order result: ' . $response);
            }

            return [
                'order_id' => $orderId,
                'fulfillmentStatus' => $status
            ];
        }

        return $order;
    }



    
    ////Common
    protected sendData($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            'Client-Id: ' . config('app.ozon_api_client_id'),
            'Api-Key: ' . config('app.ozon_api_key')
        );
        if (!is_null($data)) {
            array_push($headers, ['Content-Type: application/json', 'Content-Length: ' . strlen($data_string)]);
            $data_string = json_encode(['items' => $items]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}