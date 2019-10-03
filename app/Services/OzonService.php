<?php

namespace App\Services;
use Log;
use App\Services\DropshippService;
use App\Services\NotificationService;
use DateTime;

class OzonService
{
    protected $baseUrl;

    protected $productInfoUrl;
    protected $createProductsUrl;
    protected $importProductsUrl;
    protected $updateStocksUrl;
    protected $updateProductUrl;
    protected $activateProductUrl;
    protected $deactivateProductUrl;
    protected $updatePricesUrl;
    protected $productListUrl;

    protected $ordersUrl;
    protected $orderListUrl;
    protected $orderInfoUrl;
    protected $approveOrderUrl;
    protected $cancelOrderUrl;
    protected $shipOrderUrl;

    protected $categoryListUrl;
    protected $categoryAttributeListUrl;

    protected $attributes;

    protected $interactionId;

    public function __construct() {
        $this->baseUrl = config('app.ozon_base_url');

        $this->productInfoUrl = config('app.ozon_productinfo_url');
        $this->createProductsUrl = config('app.ozon_createproduct_url');
        $this->importProductsUrl = config('app.ozon_importproduct_url');
        $this->updateStocksUrl = config('app.ozon_update_stocks_url');
        $this->updateProductUrl = config('app.ozon_updateproduct_url');
        $this->activateProductUrl = config('app.activate_product_url');
        $this->deactivateProductUrl = config('app.deactivate_product_url');
        $this->updatePricesUrl = config('app.update_productprices_url');
        $this->productListUrl = config('app.ozon_productlist_url');
    
        $this->ordersUrl = config('app.ozon_orders_url');
        $this->orderListUrl = config('app.ozon_orderlist_url');
        $this->orderInfoUrl = config('app.ozon_orderinfo_url');
        $this->approveOrderUrl = config('app.ozon_approveorder_url');
        $this->cancelOrderUrl = config('app.ozon_cancelorder_url');
        $this->shipOrderUrl = config('app.ozon_shiporder_url');
    
        $this->categoryListUrl = config('app.ozon_categorylist_url');
        $this->categoryAttributeListUrl = config('app.ozon_categoryattributelist_url');

        $this->attributes = [];

        $this->interactionId = uniqid();
    }

    public function getInteractionId() {
        return $this->interactionId;
    }
    
    public function getProductFullInfo($productId)
    {
        $products = app('db')->connection('mysql')->
            select('
                select pv.id as id, pv.ozon_product_id as ozonProductId, pv.mall_variant_id as mallVariantId, p.name, p.description, i.image_url as imageUrl, pv.color, pv.size
                    from product_variant pv
                    inner join product p on p.id = pv.product_id
                    left join product_variant_image pvi on pv.id = pvi.product_variant_id
                    left join image i on i.id = pvi.image_id
                    where pv.deleted = 0 and i.deleted = 0 and i.is_default = 1 and pv.product_id = ' . $productId . '
            ');
        $galleryImages = array();
        $variants = array();
        $name = null;
        $mainImage = null;
        $description = null;
        if(count($products) > 0)
        {
            foreach ($products as $key => $product) {
                $response = $this->getProductInfo($product->ozonProductId);
                if (isset($response['result'])) {
                    $name = $response['result']['name'];
                    $imageResult = $this->getImages($response['result']['images'], $product->id);
                    foreach ($imageResult as $key => $image) {
                        if ($image->is_default) {
                            $mainImage = $image->imageUrl;
                        }
                        else {
                            array_push($galleryImages, [
                                'id' => $image->id,
                                'imageUrl' => $image->imageUrl
                            ]);
                        }
                    }

                    $description = $product->description;
                    array_push($variants, [
                        'mallVariantId' => $product->mallVariantId,
                        'inventory' => $response['result']['stock'],
                        'color' => $product->color,
                        'size' => $product->size,
                        'price' => $response['result']['price'],
                    ]);
                }

                if (count($variants) > 0) {
                    return [
                        'name' => $name,
                        'imageUrl' => $mainImage,
                        'description' => $description,
                        "galleryImages" => $galleryImages,
                        'variants' => $variants
                    ];
                }
                else {
                    return [
                        'Error' => 'Product with id=' . $productId . ' doesn\'t found in ozon!'
                    ];
                }
            }
        }
        else return [
            'Error' => 'Product with id=' . $productId . ' doesn\'t exists!'
        ];
    }

    public function getProductInfo($ozonProductId)
    {
        $response = $this->getProductFromOzon($ozonProductId);
        $result = json_decode($response, true);
        if (isset($result['result'])) {
            return $result;
        }
        else {
            $variant = app('db')->connection('mysql')->table('product_variant')
                ->where('ozon_product_id', $ozonProductId)
                ->first();
            if($variant) {
                $byofferResponse = $this->getProductFromOzonByOfferId($variant->mall_variant_id);
                $byofferResult = json_decode($byofferResponse, true);
                if (isset($byofferResult['result'])) {
                    app('db')->connection('mysql')->table('product_variant')
                        ->where('id', $variant->id)
                        ->update(['ozon_product_id' => $byofferResult['result']['id']]);
                    return $byofferResult;
                }
                else return $byofferResult;
            }
            else return $result;
        }
    }

    protected function getProductFromOzon($ozonProductId)
    {

        Log::info($this->interactionId . ' => Get product info from ozon:' . json_encode(['product_id' => $ozonProductId], JSON_UNESCAPED_UNICODE));
        $response = $this->sendData($this->productInfoUrl, ['product_id' => $ozonProductId]);
        $response = $response['response'];
        Log::info($this->interactionId . ' => Ozon products: ' . $response);
        return $response;
    }

    protected function getProductFromOzonByOfferId($offerId)
    {
        Log::info($this->interactionId . ' => Get product info from ozon by sku:' . json_encode(['offer_id' => $offerId], JSON_UNESCAPED_UNICODE));
        $response = $this->sendData($this->productInfoUrl, ['offer_id' => $offerId]);
        $response = $response['response'];
        Log::info($this->interactionId . ' => Ozon products: ' . $response);
        return $response;
    }

    public function createNewProduct($product)
    {
        $productId = 0;
        $result = null;
        
        if (!isset($product['name']) || is_null($product['name'])) {
            return [
                "errorCode" => "EMPTY_NAME",
                'errorMessage' => 'Name is empty!'
            ];
        }

        if (boolval(config('app.enable_product_creation'))) {
            if (!isset($product['description']) || is_null($product['description'])) {
                return [
                    "errorCode" => "EMPTY_DESCRIPTION",
                    'errorMessage' => 'Description is empty!'
                ];
            }
        }

        if (boolval(config('app.enable_product_creation'))) {
            $categoryResult = $this->getOzonCategory($product['mallCategoryId'], $product['mallCategoryName']);
            if (isset($categoryResult['error'])) {
                return [
                    "errorCode" => "CATEGORY_NOT_MAPPED",
                    'errorMessage' => $categoryResult['error']
                ];
            }
        }

        $checkShippingResult = $this->checkShipping($product);
        if (!isset($checkShippingResult['success'])) {
            return $checkShippingResult;
        }

        try {
            $pdo = app('db')->connection('mysql')->getPdo();
            $result = app('db')->connection('mysql')->table('product')
                ->insert([
                    'name' => $product['name'],
                    'sku' => $product['sku'],
                    'enabled' => $product['enabled'],
                    'update_date' => date('Y-m-d\TH:i:s.u'),
                    'description' => $product['description'],
                    'unlimited' => $product['unlimited'],
                    'quantity' => $product['quantity'],
                    'price' => $product['price'],
                    'weight' => $product['weight'],
                    'default_category' => isset($product['categoryIds']) ? $product['categoryIds'][0] : null,
                    'mall_category_id' => $product['mallCategoryId'],
                    'mall_category_name' => $product['mallCategoryName'],
                    'brand' => isset($product['brand']) ? $product['brand'] : null,
                ]); 
            if ($result) {
                $productId = $pdo->lastInsertId();
            }
            else
            {
                Log::error('Insert product to database failed' . $result);
                return [
                    'errorCode' => 'PRODUCT_EXISTS',
                    'errorMessage' => 'Failed to insert product to database.'
                ];
            }
        } catch (\Exception $e) {
            $result = app('db')->connection('mysql')->table('product')
                ->where('sku', $product['sku'])
                ->first();
            if($result) {
                $productId = $result->id;
            }
        }


        $variantIds = $this->createVariants($product['variants'], $productId, isset($product['enabled']) ? $product['enabled'] : null);

        return ['id' => $productId];
    }

    protected function createVariants($variants, $productId, $enabled)
    {
        $insertedVariants = [];
        foreach ($variants as $key => $variant) {
            $shippingPrice = 0;
            foreach ($variant['shippingRaw'] as $key => $shipping) {
                if (strtolower(strval($shipping['geo_id'])) == strtolower(config('app.shipping_rf_code'))) {
                    $shippingPrice = floatval($shipping['price']);
                    break;
                }
            }
            $price = round((floatval($variant['priceRaw']) / 1.0815 * 1.035) + ($shippingPrice * 1.035), 2);

            $quantity = 0;
            if (isset($variant['isPromo']) && boolval($variant['isPromo'])) {
                $quantity = $variant['inventory'];
            }
            else {
                $quantity = $variant['inventory'] - 5 > 0 ? $variant['inventory'] - 5 : 0;
            }

            try {
                $existedVariant = app('db')->connection('mysql')->table('product_variant')
                    ->where('mall_variant_id', $variant['mallVariantId'])
                    ->first();
                if ($existedVariant) {
                    $updatedFields = [
                        'color' => $variant['color'],
                        'size' => $variant['size'],
                        'price' => $price,
                        'inventory' => $quantity,
                        'ozon_product_id' => null,
                        'sent' => 0
                    ];
                    if (!is_null($enabled)) {
                        $updatedFields['deleted'] = intval(!boolval($enabled));
                    }

                    app('db')->connection('mysql')->table('product_variant')
                        ->where('mall_variant_id', $variant['mallVariantId'])
                        ->update($updatedFields);

                        array_push($insertedVariants, [
                            'product_variant_id' => $existedVariant->id,
                            'mall_variant_id' => $variant['mallVariantId']
                            ]);
                }
                else {
                    $pdo = app('db')->connection('mysql')->getPdo();
                    $insertedFields = [
                        'product_id' => $productId,
                        'mall_variant_id' => $variant['mallVariantId'],
                        'color' => $variant['color'],
                        'size' => $variant['size'],
                        'price' => $price,
                        'inventory' => $variant['inventory'],
                        'deleted' => 0,
                        'sent' => 0
                    ];
                    if (!is_null($enabled)) {
                        $insertedFields['deleted'] = intval(!boolval($enabled));
                    }
                    $result = app('db')->connection('mysql')->table('product_variant')
                        ->insert($insertedFields);
        
                    if ($result) {
                        $variantId = $pdo->lastInsertId();
                        array_push($insertedVariants, [
                            'product_variant_id' => $variantId,
                            'mall_variant_id' => $variant['mallVariantId']
                            ]);
                    }
                }


            } catch (\Exception $e) {
                
            }

            try {
                GearmanService::addProcessProductToOzonNotification(['mall_variant_id' => $variant['mallVariantId']]);
            }
            catch (\Exception $e) {
                Log::error('Adding order notification message to gearman queue failed!');
            }
        }

        return $insertedVariants;
    }

    public function processProductToOzon($product)
    {
        $success = false;
        if (boolval(config('app.enable_product_creation'))) {
            $variants = app('db')->connection('mysql')
            ->select('
                select
                    pv.id as id,
                    pv.mall_variant_id as mallVariantId,
                    pv.price as price,
                    pv.inventory as inventory,
                    pv.color as color,
                    pv.size as size,
                    p.id as productId,
                    p.sku as sku,
                    p.description as description,
                    p.name as name,
                    p.weight as weight,
                    p.unlimited as unlimited,
                    p.enabled as enabled,
                    p.default_category as defaultCategory,
                    p.mall_category_id as mallCategoryId,
                    p.mall_category_name as mallCategoryName, 
                    p.brand as brand,
                    i.image_url as imageUrl
                from product_variant pv
                left join product p on pv.product_id = p.id
                left join product_variant_image pvi on pv.id = pvi.product_variant_id
                left join image i on i.id = pvi.image_id
                where pv.deleted = 0 and i.deleted = 0 and i.is_default = 1 and pv.ozon_product_id is null and pv.sent = 0 and pv.mall_variant_id = "' . $product['mall_variant_id'] . '"
                order by i.id desc
            ');

            if (!is_null($variants) && count($variants) > 0) {
                $variant = $variants[0];
                if (!is_null($variant->imageUrl)) {
                    Log::debug('Main image for variant:' . json_encode($variant->imageUrl));
                    $categoryResult = $this->getOzonCategory($variant->mallCategoryId, $variant->mallCategoryName);
                    Log::debug('Category search result:' . json_encode($categoryResult));
                    if (!isset($categoryResult['error']) && !is_null($categoryResult['categoryId'])) {
                        $item = $this->addProductToRequest(
                            $variant->mallVariantId,
                            $variant->sku,
                            $variant->description,
                            $variant->name,
                            $variant->price,
                            $variant->weight,
                            $variant->inventory,
                            !$variant->unlimited,
                            $variant->enabled,
                            $categoryResult['categoryId'],
                            $variant->color,
                            $variant->size,
                            $variant->brand,
                            $variant->imageUrl,
                            $variant->productId
                        );

                        $result = $this->sendProductsToOzon([$item]);
                        if (!is_null($result) && isset($result['result']) && isset($result['result']['task_id'])) {
                            $success = true;
                        }
                    }
                }
                else {
                    Log::debug('No main image for variant:' . json_encode($variant->mallVariantId));
                }
            }
        }
        else {
            $success = true;
        }

        if ($success) {
            try {
                GearmanService::addSetOzonProductIdNotification(['mall_variant_id' => $product['mall_variant_id']]);
            }
            catch (\Exception $e) {
                Log::error('Adding order notification message to gearman queue failed!');
                return [
                    'result' => false,
                    'data' => $product
                ];
            }

            app('db')->connection('mysql')->table('product_variant')
                ->where('mall_variant_id', $product['mall_variant_id'])
                ->update([
                    'sent_date' => date('Y-m-d\TH:i:s.u'),
                    'sent' => 1
                ]);

            return [
                'result' => true,
                'data' => $product
            ];
        }

        $variant = app('db')->connection('mysql')->table('product_variant')
            ->where('mall_variant_id', $product['mall_variant_id'])
            ->first();
        if ($variant && $variant->sent == 1 && $variant->deleted == 0) {
            return [
                'result' => true,
                'data' => $product
            ];            
        }

        return [
            'result' => false,
            'data' => $product
        ];
    }

    public function setOzonProductId($product)
    {
        $response = $this->getProductFromOzonByOfferId($product['mall_variant_id']);
        $ozonProduct = json_decode($response, true);

        if(isset($ozonProduct['error']) && isset($ozonProduct['error']['code']) && $ozonProduct['error']['code'] == 'NOT_FOUND_ERROR') {
            return [
                'result' => true,
                'data' => $product
            ];
        }

        $updateFields = ['sent_date' => date('Y-m-d\TH:i:s.u')];

        $variant = app('db')->connection('mysql')->table('product_variant')
            ->where('mall_variant_id', $product['mall_variant_id'])
            ->first();
        if(isset($ozonProduct['result'])) {
            $updateFields = ['ozon_product_id' => $ozonProduct['result']['id']];
            app('db')->connection('mysql')->table('product_variant')
                ->where('id', $variant->id)
                ->update($updateFields);            

            $message = ['product_id' => $ozonProduct['result']['id']];
            
            try {
                GearmanService::addUpdateProductNotification($message);
            }
            catch (\Exception $e) {
                Log::error('Adding order notification message to gearman queue failed!');
                return [
                    'result' => false,
                    'data' => $product
                ];
            }

            return [
                'result' => true,
                'data' => $product
            ];
        }

        app('db')->connection('mysql')->table('product_variant')
            ->where('id', $variant->id)
            ->update($updateFields);

        return [
            'result' => false,
            'data' => $product
        ];
    }

    public function scheduleProductCreation($productId = null)
    {
        $variants = null;
        if (is_null($productId)) {
            $variants = app('db')->connection('mysql')->select('
                select
                    pv.id as id,
                    pv.mall_variant_id as mallVariantId,
                    pv.price as price,
                    pv.inventory as inventory,
                    pv.color as color,
                    pv.size as size,
                    p.sku as sku,
                    p.description as description,
                    p.name as name,
                    p.weight as weight,
                    p.unlimited as unlimited,
                    p.enabled as enabled,
                    p.default_category as defaultCategory,
                    p.mall_category_id as mallCategoryId,
                    p.mall_category_name as mallCategoryName,
                    p.brand as brand,
                    i.image_url as imageUrl
                from product_variant pv
                left join product p on pv.product_id = p.id
                left join product_variant_image pvi on pv.id = pvi.product_variant_id
                left join image i on i.id = pvi.image_id
                    where pv.deleted = 0 and i.deleted = 0 and i.is_default = 1 and pv.ozon_product_id is null and pv.sent = 0
                order by sent_date asc
                limit 40
            ');

        }
        else {
            $variants = app('db')->connection('mysql')->select('
                select
                    pv.id as id,
                    pv.mall_variant_id as mallVariantId,
                    pv.price as price,
                    pv.inventory as inventory,
                    pv.color as color,
                    pv.size as size,
                    p.sku as sku,
                    p.description as description,
                    p.name as name,
                    p.weight as weight,
                    p.unlimited as unlimited,
                    p.enabled as enabled,
                    p.default_category as defaultCategory,
                    p.mall_category_id as mallCategoryId,
                    p.mall_category_name as mallCategoryName, 
                    p.brand as brand,
                    i.image_url as imageUrl
                    from product_variant pv
                    left join product p on pv.product_id = p.id
                    left join product_variant_image pvi on pv.id = pvi.product_variant_id
                    left join image i on i.id = pvi.image_id
                    where pv.deleted = 0 and i.deleted = 0 and i.is_default = 1 and pv.ozon_product_id is null and pv.sent = 0 and pv.product_id = ' . $productId . '
                    order by sent_date asc
                    limit 40
            ');
        }

        $errorsCategories = [];
        $errorAttributes = [];
        $errorGeneral = [];
        $setSentDateIds = [];
        if ($variants) {
            $items = [];
            $variantIds = [];
            foreach ($variants as $key => $variant) {
                array_push($setSentDateIds, $variant->id);
                if (empty($variant->name) || empty($variant->description)) {
                    array_push($errorGeneral, 'Variant ' . $variant->mallVariantId . '. Name or description is empty');
                    continue;
                }

                if (!is_null($variant->imageUrl)) {
                    $categoryResult = $this->getOzonCategory($variant->mallCategoryId, $variant->mallCategoryName);
                    if (!isset($categoryResult['error'])) {
                        $item = $this->addProductToRequest(
                            $variant->mallVariantId,
                            $variant->sku,
                            $variant->description,
                            $variant->name,
                            $variant->price,
                            $variant->weight,
                            $variant->inventory,
                            !$variant->unlimited,
                            $variant->enabled,
                            $categoryResult['categoryId'],
                            $variant->color,
                            $variant->size,
                            $variant->brand,
                            $variant->imageUrl,
                            $productId
                        );
                        if (!isset($item['error'])) {
                            array_push($items, $item);
                            array_push($variantIds, $variant->id);
                        }
                        else {
                            array_push($errorAttributes, $item['error']);
                        }
                    }
                    else {
                        $errorsCategories[$variant->mallCategoryId] = $categoryResult['error'];
                    }
                }
                else {
                    array_push($errorGeneral, 'Variant ' . $variant->mallVariantId . '. No main image');
                }
            }

            //$result = $this->sendProductsToOzon($items);
            // if (isset($result['result']) && isset($result['result']['task_id'])) {
            //     app('db')->connection('mysql')->table('product_variant')
            //         ->whereIn('id', $variantIds)
            //         ->update(['sent' => 1, 'sent_date' => date('Y-m-d\TH:i:s.u')]);
            // }
            // app('db')->connection('mysql')->table('product_variant')
            //     ->whereIn('id', $setSentDateIds)
            //     ->update(['sent_date' => date('Y-m-d\TH:i:s.u')]);
        }

        
        return [
            'general' => $errorGeneral,
            'categories' => array_values($errorsCategories),
            'attributes' => $errorAttributes
        ];
    }

    public function sendStockAndPriceAndEnabledForProduct($product)
    {
        $timerTotalStart = $milliseconds = round(microtime(true) * 1000);	
        $timerStart = $milliseconds = round(microtime(true) * 1000);
        $ignored = false;
        $reason = null;
        if(!is_array($product)) $product = json_decode($product,true);
        if (!is_array($product)) {
            return false;
        }
        $ozonProductResult = $this->getProductInfo($product['product_id']);
        $priceSuccess = false;
        $quantitySuccess = false;
        $getProductResult = true;
        $timerEnd = $milliseconds = round(microtime(true) * 1000);
        Log::debug($this->interactionId . ' => sendStockAndPriceAndEnabledForProduct: getProductInfo operation:' . ($timerEnd - $timerStart));

        $productVariant = app('db')->connection('mysql')->table('product_variant')
            ->where('ozon_product_id', $product['product_id'])
            ->first();

        if (isset($ozonProductResult['result']) && !is_null($productVariant)) {
            $timerStart = $milliseconds = round(microtime(true) * 1000);
            $updateFields = ['sent_date' => date('Y-m-d\TH:i:s.u')];

            $product['price'] = $productVariant->price;
            $product['quantity'] = $productVariant->inventory;
            $product['enabled'] = !$productVariant->deleted;

            $enabled = boolval(!$productVariant->deleted);

            $ozonEnabled = boolval($ozonProductResult['result']['visibility_details']['active_product']);

            if (!$ozonEnabled) {
                $this->enableProduct(true, $product['product_id']);
            }
            $timerEnd = $milliseconds = round(microtime(true) * 1000);
            Log::debug($this->interactionId . ' => sendStockAndPriceAndEnabledForProduct: enable before apdate operation:' . ($timerEnd - $timerStart));

            $timerStart = $milliseconds = round(microtime(true) * 1000);
            if (isset($product['price'])) {
                $oldPrice = 0;
                if (isset($ozonProductResult['result']['old_price'])) {
                    $oldPrice = floatval($ozonProductResult['result']['old_price']);
                }
                $price = floatval($product['price']);
                $priceData = [
                    'product_id' => $ozonProductResult['result']['id'],
                    'price' => strval($product['price']),
                    'vat' => "0"
                ];
                if ($price < $oldPrice) {
                    $priceData['old_price'] = strval($ozonProductResult['result']['old_price']);
                }

                $priceResult = $this->setPrices($priceData);
                if (isset($priceResult['result']) && boolval($priceResult['result'][0]['updated'])) {
                    $priceSuccess = true;
                }
            }
            $timerEnd = $milliseconds = round(microtime(true) * 1000);
            Log::debug($this->interactionId . ' => sendStockAndPriceAndEnabledForProduct: set price operation:' . ($timerEnd - $timerStart));

            $timerStart = $milliseconds = round(microtime(true) * 1000);
            if (isset($product['quantity'])) {
                $quantityResults = $this->setQuantity([
                    [
                        'product_id' => $product['product_id'],
                        'stock' => $product['quantity']
                    ]
                ]);
                
                if (isset($quantityResults['result']) && isset($quantityResults['result'][0]['updated']) && boolval($quantityResults['result'][0]['updated'])) {
                    $quantitySuccess = true;
                }

                if (isset($quantityResults['result']) && isset($quantityResults['result'][0]['updated']) && !boolval($quantityResults['result'][0]['updated'])
                && isset($quantityResults['result'][0]['errors']) && count($quantityResults['result'][0]['errors']) > 0) {
                    $ignored = true;
                    $reason = json_encode($quantityResults['result'][0]);
                }
            }
            $timerEnd = $milliseconds = round(microtime(true) * 1000);
            Log::debug($this->interactionId . ' => sendStockAndPriceAndEnabledForProduct: set stock operation:' . ($timerEnd - $timerStart));

            $timerStart = $milliseconds = round(microtime(true) * 1000);
            if (!is_null($enabled)) {
                if (!$enabled) {
                    $this->enableProduct($enabled, $product['product_id']);
                }
            }
            else {
                if (!$ozonEnabled) {
                    $this->enableProduct($ozonEnabled, $product['product_id']);
                }
            }
            $timerEnd = $milliseconds = round(microtime(true) * 1000);
            Log::debug($this->interactionId . ' => sendStockAndPriceAndEnabledForProduct: enable after apdate operation:' . ($timerEnd - $timerStart));

            $timerStart = $milliseconds = round(microtime(true) * 1000);
            app('db')->connection('mysql')->table('product_variant')
                ->where('deleted', 0)
                ->where('ozon_product_id', $ozonProductResult['result']['id'])
                ->update($updateFields);
            $timerEnd = $milliseconds = round(microtime(true) * 1000);
            Log::debug($this->interactionId . ' => sendStockAndPriceAndEnabledForProduct: update db operation:' . ($timerEnd - $timerStart));
        }
        else {
            if (isset($ozonProductResult['error'])
            && isset($ozonProductResult['error']['code'])
            && $ozonProductResult['error']['code'] == 'TOO_MANY_REQUESTS') {
                $getProductResult = false;
                Log::info($this->interactionId . ' => LOG_TOO_MANY_REQUESTS');
            }
        }

        $timerStart = $milliseconds = round(microtime(true) * 1000);
        $data = [
            'product_id' => $product['product_id']
        ];

        $timerEnd = $milliseconds = round(microtime(true) * 1000);
        Log::debug($this->interactionId . ' => sendStockAndPriceAndEnabledForProduct: generate response operation:' . ($timerEnd - $timerStart));   

        $timerTotalEnd = $milliseconds = round(microtime(true) * 1000);
        Log::debug($this->interactionId . ' => sendStockAndPriceAndEnabledForProduct: Total:' . ($timerTotalEnd - $timerTotalStart )); 
        return [
            'result' => $getProductResult && $priceSuccess && $quantitySuccess,
            'data' => $data,
            'ignored' => $ignored,
            'reason' => $reason
        ];
    }


    protected function sendProductsToOzon($items) {
        Log::info($this->interactionId . ' => Import product request to ozon:' . json_encode(['items' => $items], JSON_UNESCAPED_UNICODE));
        $response = $this->sendData($this->importProductsUrl, ['items' => $items]);
        $response = $response['response'];
        Log::info($this->interactionId . ' => Import ozon products: ' . $response);
        $result = json_decode($response, true);
        return $result;
    }

    protected function addProductToRequest($mallVariantId, $sku, $description, $name, $price, $weight, $quantity, $unlimited, $enabled, $ozonCategoryId, $color, $size, $brand, $mainImageUrl, $productId)
    {
        if(!is_null($ozonCategoryId)) {
            $attributes = array([
                'id' => config('app.ozon_product_group_attribute'),
                'value' => $sku
            ]);
            $colorAttribute = null;
            if (!is_null($color)) {
                $colorAttribute = $this->getAttribute($ozonCategoryId, $color);
                if (isset($colorAttribute['attributeId'])) {
                    $attributes = $this->AddAttributeToRequest($attributes, $colorAttribute);
                }
            }
            $sizeAttribute = null;
            if (!is_null($size)) {
                $sizeAttribute = $this->getAttribute($ozonCategoryId, $size);
                if (isset($sizeAttribute['attributeId'])) {
                    $attributes = $this->AddAttributeToRequest($attributes, $sizeAttribute);
                }
            }

            $result =  [
                'barcode' => strval($sku),
                'description' => $description,
                'category_id' => $ozonCategoryId,
                'name' => $name,
                'offer_id' => $mallVariantId,
                'price' => strval($price),
                'vat'=> '0',
                'weight' => intval($weight) > 0 ? intval($weight) : 1,
                'weight_unit' => 'g',
                'quantity' => $quantity,
                'images' => array([
                    'file_name' => $mainImageUrl,
                    'default' => true
                ]),
                'attributes' => $attributes,
                "visibility_details" => [
                    'has_price' => true,
                    'has_stock' => $unlimited,
                    'active_product' => $enabled
                ]
            ];

            if (!is_null($brand)) {
                $result['vendor'] = $brand;
            }

            return $result;
        }
        else return [
            'error' => true
        ];
    }

    protected function AddAttributeToRequest($attributes, $attribute)
    {
        if ($attribute['isCollection']) {
            array_push($attributes, [
                'id' => $attribute['attributeId'],
                'collection' => array(strval($attribute['attributeValueId']))
            ]);
        }
        else {
            array_push($attributes, [
                'id' => $attribute['attributeId'],
                'value' => strval($attribute['attributeValueId'])
            ]);
        }

        return $attributes;
    }

    public function getOzonCategory($mallCategoryId, $mallCategoryName)
    {
        $error = null;
        $ozonCategoryId = $this->classifyCategory($mallCategoryId);
        if (is_null($ozonCategoryId)) {
            $result = app('db')->connection('mysql')->select('select oc.id as ozonCategoryId from ozon_category oc
                left join category c on c.ozon_category_id = oc.id where c.mall_category_id = \'' . $mallCategoryId . '\' limit 1');
            if (!is_null($result) && isset($result[0])) {
                $ozonCategoryId = $result[0]->ozonCategoryId;
            }
            else {
                try {
                    app('db')->connection('mysql')->table('category')
                        ->insert([
                            'mall_category_id' => $mallCategoryId,
                            'mall_category_name' => $mallCategoryName,
                            'create_date' => date('Y-m-d\TH:i:s.u')
                        ]);
                    $error = 'Category with mallCategoryId=' . $mallCategoryId . ' does not mapped!';
                    Log::debug($error);
                }
                catch (\Exception $e) {
                    $error = 'Category with mallCategoryId=' . $mallCategoryId . ' does not mapped!';
                    Log::error('Category ' . $mallCategoryId . ' already exists');
                }
            }
        }
        if (is_null($ozonCategoryId)) {
            return [
                'error' => $error
            ];
        }
        else return [
            'categoryId' => $ozonCategoryId
        ];
    }

    protected function classifyCategory($categoryId)
    {
        return null;
    }

    protected function getAttribute($ozonCategoryId, $value)
    {
        $result = app('db')->connection('mysql')->select('
            select a.id as attributeId, av.ozon_id as attributeValueId, a.is_collection as isCollection from ozon_category oc
                inner join ozon_category_attribute ca on oc.id = ca.ozon_category_id
                inner join attribute a on ca.attribute_id = a.id
                inner join attribute_value av on a.id = av.attribute_id
                inner join attribute_value_map avm on av.id = avm.attribute_value_id
                where avm.value =\'' . addslashes($value) . '\'
                    and oc.id = \'' . $ozonCategoryId .'\'
                limit 1
        ');
        if (!$result) {
            // $result = app('db')->connection('mysql')->select('
            //     select a.id as attributeId, av.ozon_id as attributeValueId, a.is_collection as isCollection from ozon_category oc
            //         inner join ozon_category_attribute ca on oc.id = ca.ozon_category_id
            //         inner join attribute a on ca.attribute_id = a.id
            //         inner join attribute_value av on a.id = av.attribute_id
            //         where av.value = \'' . $value . '\'
            //             and oc.id = ' . $ozonCategoryId . '
            //         limit 1
            // ');
            //if (!$result)  {
                // $pdo = app('db')->connection('mysql')->getPdo();
                // $result = app('db')->connection('mysql')->table('attribute_value_map')
                //     ->insert([
                //         'value' => $value
                //     ]);
       
                // if ($result)  {
                //     $attributeMapId = $pdo->lastInsertId();
                // }
                $attributeMapId = null;
                Log::error('Attribute with value ' . $value . ' doesn\'t exists! Attribute map with id=' . $attributeMapId . ' was created.');
                return [
                    'attributeMapId' => $attributeMapId
                ];
            //}
        }  
        return [
            'attributeId' => $result[0]->attributeId,
            'attributeValueId' => $result[0]->attributeValueId,
            'isCollection' => $result[0]->isCollection
        ];
    }

    protected function setQuantity($items)
    {
        Log::info($this->interactionId . ' => Update stocks request to ozon:' . json_encode(['stocks' => $items], JSON_UNESCAPED_UNICODE));
        $response = $this->sendData($this->updateStocksUrl, ['stocks' => $items]);
        $response = $response['response'];
        Log::info($this->interactionId . ' => Update stocks response: ' . $response);
        $result = json_decode($response, true);
        return $result;
    }

    public function createProductCombination($productId, $combinations)
    {
        $product = app('db')->connection('mysql')->table('product')
            ->where('id', $productId)->first();
        if (!is_null($product)) {
            $variantIds = $this->createVariants($combinations, $productId, null);
            $result = [];
            foreach ($variantIds as $key => $variant) {
                $result[$variant['product_variant_id']] = $variant['mall_variant_id'];
            }
            return $result;
        }
        return [
            'Error' => 'Product not found'
        ];
    }

    protected function getOzonProductId($productId, $mallVariantId)
    {
        $productVariant = app('db')->connection('mysql')->table('product_variant')
            ->where('product_id', $productId)
            ->where('mall_variant_id', $mallVariantId)
            ->first();
            
        if ($productVariant) {
            return [
                'Success' => true,
                'id' => $productVariant->id,
                'ozonProductId' => $productVariant->ozon_product_id,
                'mallVariantId' => $productVariant->mall_variant_id,
                'productId' => $productVariant->product_id
            ];
        }
        else {
            return [
                'Success' => false,
                'Error' => 'Product with ID=' . $productId . ' and mallVariantId=' . $mallVariantId . ' doesn\'t exists!'
            ];
        }
    }

    public function updateProduct($product, $productId)
    {
        $result = [];
        $shippingError = false;
        $newVariants = [];
        $success = true;

        $productVariants = $this->getAllProductVariants($productId);
        if (isset($product['variants']) && count($product['variants']) > 0) {
            $mallVariantIds = [];
            $mallVariantIdsFromDb = [];
            foreach ($product['variants'] as $key => $variant) {
                array_push($mallVariantIds, $variant['mallVariantId']);
            }

            foreach ($productVariants as $key => $productVariant) {
                array_push($mallVariantIdsFromDb, $productVariant->mallVariantId);

                if (!in_array($productVariant->mallVariantId, $mallVariantIds)) {
                    if ($productVariant->ozonProductId != null) {
                        $deactivatedItem = [
                            'enabled' => false,
                            'quantity' => 0
                        ];
                        $this->saveProductVariant($deactivatedItem, $productId, $productVariant->mallVariantId, $productVariant->ozonProductId);    
                    }
                }
            }

            $variantsToUpdate = [];
            foreach ($product['variants'] as $key => $variant) {
                if (!in_array($variant['mallVariantId'], $mallVariantIdsFromDb)) {
                    array_push($newVariants, $variant);
                    continue;
                }

                $item = [];

                if (isset($variant['priceRaw'])) {
                    $shippingError = false;
                    if (isset($variant['shippingRaw'])) {
                        foreach ($variant['shippingRaw'] as $key => $shipping) {
                            if (isset($shipping['geo_id']) && strtolower(strval($shipping['geo_id'])) == strtolower(config('app.shipping_rf_code'))) {
                                $shippingError = false;
                                break;
                            }
                            else {
                                $shippingError = true;
                            }
                        }
                    }
                    else {
                        continue;
                    }

                    if ($shippingError) {
                        continue;
                    }

                    $shippingPrice = 0;
                    foreach ($variant['shippingRaw'] as $key => $shipping) {
                        if (strtolower(strval($shipping['geo_id'])) == strtolower(config('app.shipping_rf_code'))) {
                            $shippingPrice = floatval($shipping['price']);
                            break;
                        }
                    }
                    $price = round((floatval($variant['priceRaw']) / 1.0815 * 1.035) + ($shippingPrice * 1.035), 2);
                    $item['price'] = $price;
                }

                if (isset($variant['inventory']) && !is_null($variant['inventory'])) {
                    if (isset($variant['isPromo']) && boolval($variant['isPromo'])) {
                        $item['quantity'] = $variant['inventory'];
                    }
                    else {
                        $item['quantity'] = $variant['inventory'] - 5 > 0 ? $variant['inventory'] - 5 : 0;
                    }
                }

                if (isset($product['enabled'])) {
                    $item['enabled'] = $product['enabled'];
                }

                $variantToUpdate = $this->checkOzonProducts($productId, $variant['mallVariantId']);
                if (isset($variantToUpdate['Success']) && $variantToUpdate['Success']) {
                    $item['quantity'] = intval($item['quantity']) + $variantToUpdate['reservedStock'];
                    if (!boolval($product['enabled'])) {
                        $item['quantity'] = 0;
                    }
                    $variantToUpdate['item'] = $item;
                    array_push($variantsToUpdate, $variantToUpdate);
                }
                else {
                    $success = false;
                    array_push($result, ['error' => $variantToUpdate['result']]);
                    break;
                }
            }

            if ($success) {
                foreach ($variantsToUpdate as $key => $toUpdate) {
                    $this->saveProductVariant($toUpdate['item'], $productId, $toUpdate['mallVariantId'], $toUpdate['ozonProductId']);
    
                    array_push($result, ['productId' => $productId]);
                }
            }            
        }
        else if (isset($product['enabled'])) {
            $variantsToUpdate = [];
            foreach ($productVariants as $key => $productVariant) {
                $variantToUpdate = $this->checkOzonProducts($productId, $productVariant->mallVariantId);
                if (isset($variantToUpdate['Success']) && $variantToUpdate['Success']) {
                    array_push($variantsToUpdate, $variantToUpdate);
                }
                else {
                    $success = false;
                    array_push($result, ['error' => $variantToUpdate['result']]);
                    break;
                }
            }

            if ($success) {
                foreach ($variantsToUpdate as $key => $toUpdate) {
                    $item = [
                        'enabled' => $product['enabled']
                    ];

                    if (!boolval($product['enabled'])) {
                        $item['quantity'] = 0;
                    }

                    $this->saveProductVariant($item, $productId, $toUpdate['mallVariantId'], $toUpdate['ozonProductId']);
    
                    array_push($result, ['productId' => $productId]);
                }
            }
        }

        if (count($newVariants) > 0) {
            $variantIds = $this->createVariants($newVariants, $productId, isset($product['enabled']) ? $product['enabled'] : null);
        }
        
        return [
            'result' => $result,
            'success' => $success
        ];
    }

    protected function checkOzonProducts($productId, $mallVariantId)
    {
        $variantToUpdate = [];
        $productVariant = $this->getOzonProductId($productId, $mallVariantId);

        if (is_null($productVariant['ozonProductId'])) {
            $byofferResponse = $this->getProductFromOzonByOfferId($mallVariantId);
            $byofferResult = json_decode($byofferResponse, true);
            if (isset($byofferResult['result'])) {
                app('db')->connection('mysql')->table('product_variant')
                    ->where('mall_variant_id', $mallVariantId)
                    ->update(['ozon_product_id' => $byofferResult['result']['id']]);
            }
        }

        if ($productVariant['Success'] && !is_null($productVariant['ozonProductId'])) {
            $ozonProductResult = $this->getProductInfo($productVariant['ozonProductId']);
            if (isset($ozonProductResult['result'])) {
                $variantToUpdate = [
                    'mallVariantId' => $mallVariantId,
                    'ozonProductId' => $productVariant['ozonProductId'],
                    'Success' => true,
                    'reservedStock' => (isset($ozonProductResult['result']['stocks']) && isset($ozonProductResult['result']['stocks']['reserved'])) ? intval($ozonProductResult['result']['stocks']['reserved']) : 0
                ];
            }
            else {
                $variantToUpdate = [
                    'Success' => false,
                    'result' => $ozonProductResult
                ];
            }
        }
        else {
            $variantToUpdate = [
                'Success' => false,
                'result' => isset($productVariant['Error']) ? $productVariant['Error'] : "Unexpected error"
            ];
        }

        return $variantToUpdate;
    }

    public function updateProductLight($product, $productId)
    {
        $result = [];
        $shippingError = false;
        $newVariants = [];
        $success = true;

        $productVariants = $this->getAllProductVariants($productId);
        if (isset($product['variants']) && count($product['variants']) > 0) {
            $mallVariantIds = [];
            $mallVariantIdsFromDb = [];
            foreach ($product['variants'] as $key => $variant) {
                array_push($mallVariantIds, $variant['mallVariantId']);
            }

            $deactivatedProductVariants = [];
            $activatedProductVariants = [];
            foreach ($productVariants as $key => $productVariant) {
                array_push($mallVariantIdsFromDb, $productVariant->mallVariantId);

                if (!in_array($productVariant->mallVariantId, $mallVariantIds)) {
                    if ($productVariant->ozonProductId != null) {
                        $this->deactivateProduct($productVariant->ozonProductId);
                    }
                    array_push($deactivatedProductVariants, $productVariant->id);
                }
                else if($productVariant->deleted == 1) {
                    array_push($activatedProductVariants, $productVariant->id);
                }
            }

            if (count($deactivatedProductVariants) > 0) {
                app('db')->connection('mysql')->table('product_variant')
                    ->whereIn('id', $deactivatedProductVariants)
                    ->update(['deleted' => 1]);
            }

            if (count($activatedProductVariants) > 0) {
                app('db')->connection('mysql')->table('product_variant')
                    ->whereIn('id', $activatedProductVariants)
                    ->update(['deleted' => 0]);
            }

            foreach ($product['variants'] as $key => $variant) {
                if (!in_array($variant['mallVariantId'], $mallVariantIdsFromDb)) {
                    array_push($newVariants, $variant);
                    continue;
                }

                $item = [];

                if (isset($variant['priceRaw'])) {
                    $shippingError = false;
                    if (isset($variant['shippingRaw'])) {
                        foreach ($variant['shippingRaw'] as $key => $shipping) {
                            if (isset($shipping['geo_id']) && strtolower(strval($shipping['geo_id'])) == strtolower(config('app.shipping_rf_code'))) {
                                $shippingError = false;
                                break;
                            }
                            else {
                                $shippingError = true;
                            }
                        }
                    }
                    else {
                        continue;
                    }

                    if ($shippingError) {
                        continue;
                    }

                    $shippingPrice = 0;
                    foreach ($variant['shippingRaw'] as $key => $shipping) {
                        if (strtolower(strval($shipping['geo_id'])) == strtolower(config('app.shipping_rf_code'))) {
                            $shippingPrice = floatval($shipping['price']);
                            break;
                        }
                    }
                    $price = round((floatval($variant['priceRaw']) / 1.0815 * 1.035) + ($shippingPrice * 1.035), 2);
                    $item['price'] = $price;
                }

                if (isset($variant['inventory']) && !is_null($variant['inventory'])) {
                    if (isset($variant['isPromo']) && boolval($variant['isPromo'])) {
                        $item['quantity'] = $variant['inventory'];
                    }
                    else {
                        $item['quantity'] = $variant['inventory'] - 5 > 0 ? $variant['inventory'] - 5 : 0;
                    }
                }

                if (isset($product['enabled'])) {
                    $item['enabled'] = $product['enabled'];
                }

                $updateResult = $this->updateOzonProduct($item, $productId, $variant['mallVariantId']);
                if(isset($updateResult['error'])) {
                    $success = false;
                }
                array_push($result, $updateResult);
            }
        }
        else if (isset($product['enabled'])) {
            foreach ($productVariants as $key => $productVariant) {
                $item = [
                    'enabled' => $product['enabled']
                ];
                $updateResult = $this->updateOzonProduct($item, $productId, $productVariant->mallVariantId);
                if(isset($updateResult['error'])) {
                    $success = false;
                }
                array_push($result, $updateResult);
            }
        }

        if (count($newVariants) > 0) {
            $variantIds = $this->createVariants($newVariants, $productId, isset($product['enabled']) ? $product['enabled'] : null);
            $imageUrl = app('db')->connection('mysql')->select('
                select i.image_url as imageUrl from product_variant pv
                    left join product_variant_image pvi on pv.id = pvi.product_variant_id
                    left join image i on i.id = pvi.image_id where pv.product_id = ' . $productId . '
                    and i.is_default = 1 and i.deleted = 0 limit 1');

            // Log::info(json_encode($imageUrl, JSON_UNESCAPED_UNICODE));
            // if ($imageUrl) {
            //     Log::info(json_encode($imageUrl[0]->imageUrl, JSON_UNESCAPED_UNICODE));
            //     $this->addMainImage($productId, $imageUrl[0]->imageUrl);
            // }

        }
        
        return [
            'result' => $result,
            'success' => $success
        ];

    }

    protected function checkShipping($product)
    {
        $shippingError = false;
        foreach ($product['variants'] as $key => $variant) {
            if (isset($variant['shippingRaw'])) {
                foreach ($variant['shippingRaw'] as $key => $shipping) {
                    if (isset($shipping['geo_id']) && strtolower(strval($shipping['geo_id'])) == strtolower(config('app.shipping_rf_code'))) {
                        $shippingError = false;
                        break;
                    }
                    else {
                        $shippingError = true;
                    }
                }
            }
            else {
                $shippingError = true;
            }
        }
        if ($shippingError) {
            return [
                "errorCode" => "SHIPPING_NOT_FOUND",
                'errorMessage' => 'Shipping not found.'
            ];
        }
        else {
            return ['success' => true];
        }
    }

    protected function updateOzonProduct($product, $productId, $mallVariantId)
    {
        $productVariant = $this->getOzonProductId($productId, $mallVariantId);

        if (is_null($productVariant['ozonProductId'])) {
            $byofferResponse = $this->getProductFromOzonByOfferId($mallVariantId);
            $byofferResult = json_decode($byofferResponse, true);
            if (isset($byofferResult['result'])) {
                app('db')->connection('mysql')->table('product_variant')
                    ->where('mall_variant_id', $mallVariantId)
                    ->update(['ozon_product_id' => $byofferResult['result']['id']]);
            }
        }

        if ($productVariant['Success'] && !is_null($productVariant['ozonProductId'])) {
            $ozonProductResult = $this->getProductInfo($productVariant['ozonProductId']);
            if (isset($ozonProductResult['result'])) {
                $this->saveProductVariant($product, $productId, $mallVariantId, $productVariant['ozonProductId']);
                $ozonProductResult['result']['productVariant'] = [
                    'id' => $productVariant['id'],
                    'mallVariantId' => $productVariant['mallVariantId'],
                    'productId' => $productVariant['productId'],
                    'ozonProductId' => $productVariant['ozonProductId']
                ];

                $updateFields = array();
                if (isset($product['color'])) {
                    $updateFields['color'] = $product['color'];
                }
                if (isset($product['size'])) {
                    $updateFields['size'] = $product['size'];
                }
                $request = ['product_id' => $ozonProductResult['result']['id']];
                $updateNeeded = false;
                if (isset($product['name'])) {
                    $request['name'] = $product['name'];
                    $updateFields['name'] = $product['name'];
                    $updateNeeded = true;
                }
                if (isset($product['description'])) {
                    $request['description'] = $product['description'];
                    $updateFields['description'] = $product['description'];
                    $updateNeeded = true;
                }
                if(isset($product['images']) && count($product['images']) > 0) {
                    $request['images'] = $product['images'];
                    $updateNeeded = true;
                }
    
                $attributes = array();
                if (isset($product['color'])) {
                    $colorAttribute = null;
                    if (!is_null($color)) {
                        $colorAttribute = $this->getAttribute($ozonCategoryId, $color);
                        if (!is_null($colorAttribute->attributeId)) {
                            $attributes = $this->AddAttributeToRequest($attributes, $colorAttribute);
                        }
                    }
                }
                if (isset($product['size'])) {
                    $sizeAttribute = null;
                    if (!is_null($size)) {
                        $sizeAttribute = $this->getAttribute($ozonCategoryId, $size);
                        if (!is_null($sizeAttribute->attributeId)) {
                            $attributes = $this->AddAttributeToRequest($attributes, $sizeAttribute);
                        }
                    }
                }
                if (count($attributes) > 0) {
                    $request['attributes'] = $attributes;
                    $updateNeeded = true;
                }
    
                if ($updateNeeded) {
                    Log::debug($this->interactionId . ' => Update product request to ozon:' . json_encode($request, JSON_UNESCAPED_UNICODE));
                    $response = $this->sendData($this->updateProductUrl, $request);
                    $response = $response['response'];
                    Log::debug($this->interactionId . ' => Update product response: ' . $response);
                    $result = json_decode($response, true);
                }
    
                if ($updateNeeded && count($updateFields) > 0) {
                    app('db')->connection('mysql')->table('product_variant')
                        ->where('deleted', 0)
                        ->where('product_id', $productId)
                        ->where('mall_variant_id', $mallVariantId)
                        ->update($updateFields);
                }
    
                return ['productId' => $productId];
            }
            else return $ozonProductResult;
        }
        else return $productVariant;
    }

    public function saveProductVariant($product, $productId, $mallVariantId, $ozonProductId)
    {
        if (!is_null($ozonProductId)) {
            $message = ['product_id' => $ozonProductId];
            $updatedFields = [];
            if (isset($product['quantity'])) {
                $updatedFields['inventory'] = $product['quantity'];
            }
            if (isset($product['price'])) {
                $updatedFields['price'] = $product['price'];
            }
            if (isset($product['enabled'])) {
                $updatedFields['deleted'] = !boolval($product['enabled']);
            }
    
            if (isset($product['quantity']) || isset($product['price']) || isset($product['enabled'])) {
                app('db')->connection('mysql')->table('product_variant')
                    ->where('ozon_product_id', $ozonProductId)
                    ->update($updatedFields);

                try {
                    GearmanService::addUpdateProductNotification($message);
                }
                catch (\Exception $e) {
                    Log::error('Adding message to gearman queue failed!');
                }
            }
        }
    }

    protected function enableProduct($enabled, $id)
    {
        if ($enabled) {
            $activateResponse = $this->activateProduct($id);
        }
        else if (!$enabled){
            $activateResponse = $this->deactivateProduct($id);
        }
        $activateResponse = json_decode($activateResponse, true);
        return $activateResponse;
    }

    protected function setPrices($items)
    {
        Log::info($this->interactionId . ' => Update prices request to ozon:' . json_encode(['prices' => [$items]], JSON_UNESCAPED_UNICODE));
        $response = $this->sendData($this->updatePricesUrl, ['prices' => [$items]]);
        $response = $response['response'];
        Log::info($this->interactionId . ' => Update prices response: ' . $response);
        $result = json_decode($response, true);
        return $result;
    }

    public function deleteProduct($productId)
    {
        $result = [];
        $products = $this->getProductVariants($productId);
        foreach ($products as $key => $product) {
            array_push($result, $this->deactivateProduct($product->ozonProductId));
        }
        app('db')->connection('mysql')->table('product_variant')
            ->where('product_id', $productId)
            ->update(['deleted' => 1]);
        return $result;
    }

    protected function activateProduct($ozonProductId)
    {
        Log::info($this->interactionId . ' => Activate ozon product: ' . $ozonProductId);
        $response = $this->sendData($this->activateProductUrl, ['product_id' => $ozonProductId]);
        $response = $response['response'];
        Log::info($this->interactionId . ' => Activate ozon product result: ' . $response);
        return $response;
    }

    protected function deactivateProduct($ozonProductId)
    {
        Log::info($this->interactionId . ' => Deactivate ozon product: ' . $ozonProductId);
        $response = $this->sendData($this->deactivateProductUrl, ['product_id' => $ozonProductId]);
        $response = $response['response'];
        Log::info($this->interactionId . ' => Deactivate ozon product result: ' . $response);
        return $response;
    }

    public function addMainImage($productId, $imageUrl)
    {
        $timerStart = $milliseconds = round(microtime(true) * 1000);
        $productVariants = $this->getProductVariants($productId);
        $timerEnd = $milliseconds = round(microtime(true) * 1000);
        Log::debug($this->interactionId . ' => addMainImage: get product variants operation:' . ($timerEnd - $timerStart));
        if (!is_null($productVariants) && count($productVariants) > 0) {
            $newImage = [
                'is_default' => true,
                'imageUrl' => $imageUrl,
                'deleted' => 0
            ];

            $timerStart = $milliseconds = round(microtime(true) * 1000);
            $firstProductVariantId = $productVariants[0]->id;
            $imagesResult = $this->compareImages(
                [$newImage],
                $firstProductVariantId
            );
            $timerEnd = $milliseconds = round(microtime(true) * 1000);
            Log::debug($this->interactionId . ' => addMainImage: compare images operation: ' . ($timerEnd - $timerStart));

            $relations = [];
            foreach ($productVariants as $key => $variant) {
                if ($variant->id != $firstProductVariantId) {
                    array_push($relations, [
                        'image_id' => $imagesResult['imageIds'][0],
                        'product_variant_id' => $variant->id
                    ]);
                }
                if (!is_null($variant->ozonProductId)) {
                    $timerStart = $milliseconds = round(microtime(true) * 1000);
                    $updateResult = $this->updateOzonProduct(['images' => $imagesResult['images']], $productId, $variant->mallVariantId);
                    $timerEnd = $milliseconds = round(microtime(true) * 1000);
                    Log::debug($this->interactionId . ' => addMainImage: update product operation: variant: ' . $variant->mallVariantId . '---:' . ($timerEnd - $timerStart));
                }
            }

            app('db')->connection('mysql')->table('product_variant_image')
                ->insert($relations);

            return $imagesResult['imageIds'][0];
        }
        else {
            Log::error('Product with Id=' . $productId . ' does not exists!');
            return [
                'Error' => 'Product with Id=' . $productId . ' does not exists!'
            ];
        }
        return $imageId;
    }

    public function addGalleryImage($productId, $image)
    {
        $timerStart = $milliseconds = round(microtime(true) * 1000);
        $imagesResult = [];
        $productVariants = $this->getProductVariants($productId);
        $timerEnd = $milliseconds = round(microtime(true) * 1000);
        Log::debug($this->interactionId . ' => addGalleryImage: get product variants operation:' . ($timerEnd - $timerStart));
        if (!is_null($productVariants) && count($productVariants) > 0) {
            foreach ($productVariants as $key => $variant) {
                $timerStart = $milliseconds = round(microtime(true) * 1000);
                $imagesResult = $this->compareImages([
                    [
                        'is_default' => false,
                        'imageUrl' => $image,
                        'deleted' => 0
                    ]],
                    $variant->id
                );
                $timerEnd = $milliseconds = round(microtime(true) * 1000);
                Log::debug($this->interactionId . ' => addGalleryImage: compare images operation: variant: ' . $variant->mallVariantId . '---:' . ($timerEnd - $timerStart));
                $timerEnd = $milliseconds = round(microtime(true) * 1000);
                $result = $this->updateOzonProduct(['images' => $imagesResult['images']],$productId, $variant->mallVariantId);
                $timerEnd = $milliseconds = round(microtime(true) * 1000);
                Log::debug($this->interactionId . ' => addGalleryImage: update product operation: variant: ' . $variant->mallVariantId . '---:' . ($timerEnd - $timerStart));
            }
            return $imagesResult['imageIds'];
        }
        return $imagesResult;
    }

    protected function getProductVariants($productId)
    {
        return app('db')->connection('mysql')->table('product_variant')
            ->where('product_id', $productId)
            ->where('deleted', 0)
            ->select('ozon_product_id as ozonProductId', 'mall_variant_id as mallVariantId', 'id')
            ->get();
    }

    protected function getAllProductVariants($productId)
    {
        return app('db')->connection('mysql')->table('product_variant')
            ->where('product_id', $productId)
            ->select('ozon_product_id as ozonProductId', 'mall_variant_id as mallVariantId', 'id', 'deleted')
            ->get();
    }

    public function addGalleryImageForCombination($productId, $mallVariantId, $image)
    {
        $timerStart = $milliseconds = round(microtime(true) * 1000);
        $result = [];
        $productVariants = app('db')->connection('mysql')->table('product_variant')
            ->where('product_id', $productId)
            ->where('mall_variant_id', $mallVariantId)
            ->where('deleted', 0)
            ->select('ozon_product_id as ozonProductId', 'mall_variant_id as mallVariantId', 'id')
            ->get();
            $timerEnd = $milliseconds = round(microtime(true) * 1000);
            Log::debug($this->interactionId . ' => addGalleryImageForCombination: get product variants operation:' . ($timerEnd - $timerStart));
        if (!is_null($productVariants) && count($productVariants) > 0) {
            $timerStart = $milliseconds = round(microtime(true) * 1000);
            $imagesResult = $this->compareImages([
                [
                    'is_default' => true,
                    'imageUrl' => $image,
                    'deleted' => 0
                ]],
                $variant[0]->id
            );
            $timerEnd = $milliseconds = round(microtime(true) * 1000);
            Log::debug($this->interactionId . ' => addGalleryImageForCombination: compare images operation:' . ($timerEnd - $timerStart));
            $timerStart = $milliseconds = round(microtime(true) * 1000);
            $updateResult = $this->updateOzonProduct(['images' => $imagesResult['images']], $productId, $mallVariantId);
            array_push($result, $imagesResult['imageIds']);
            $timerEnd = $milliseconds = round(microtime(true) * 1000);
            Log::debug($this->interactionId . ' => addGalleryImageForCombination: update product operation:' . ($timerEnd - $timerStart));
        }
        return $result;
    }

    public function deleteGalleryImage($imageId, $productId)
    {
        $result = [];
        $productVariants = $this->getProductVariants($productId);
        if (!is_null($productVariants)) {
            foreach ($productVariants as $key => $variant) {
                $ozonProductInfo = $this->getProductInfo($variant->ozonProductId);
                if (isset($ozonProductInfo['result'])) {
                    $imageResult = $this->getImages($ozonProductInfo['result']['images'], $variant->id);
                    $images = array();
                    $result = array();
                    foreach ($imageResult as $key => $image) {
                        if ($image->id == $imageId && count($imageResult) == 1) {
                            array_push($result, [
                                'Error' => 'Can\'t delete last image'
                            ]);
                        }
                        else if($image->id == $imageId && count($imageResult) > 1) {
                            if ($image->is_default) {
                                array_push($result, [
                                    'Error' => 'Can\'t delete main image'
                                ]);
                            }
                            else {
                                $insertResult = app('db')->connection('mysql')->table('image')
                                    ->where('id', $imageId)->update(['deleted' => 1]);
                            }
                        }
                        else {
                            array_push($images, [
                                'imageUrl' => $image->imageUrl,
                                'is_default' => $image->is_default
                            ]);
                        }
                    }
                    $imagesResult = $this->compareImages(
                        $images,
                        $variant->id
                    );
                    $updateResult = $this->updateOzonProduct(['images' => $imagesResult['images']], $productId, $variant->mallVariantId);
                    array_push($result, $imagesResult['imageIds']);
                }
                else return $ozonProductInfo;
            }
        }

        return $result;
    }

    protected function compareImages($newImages, $productVariantId)
    {
        $resultImages = [];
        foreach ($newImages as $key => $newImage) {
            array_push($resultImages, [
                'file_name' => $newImage['imageUrl'],
                'default' => $newImage['is_default']
            ]);
        }
        $insertedImages = array();

        $imageResult = $this->getProductVariantImages($productVariantId);
        if (count($newImages) > 0) {
            $newMainImageExists = false;
            foreach ($newImages as $key => $newImage) {
                if ($newImage['is_default']) {
                    $newMainImageExists = true;
                }

                $imageExists = false;
                $existingImageId = null;
                foreach ($imageResult as $key => $image) {
                    if($image->imageUrl == $newImage['imageUrl']) {
                        $imageExists = true;
                        $existingImageId = $image->id;
                        break;
                    }
                }

                if (!$imageExists) {
                    $newImageId = $this->saveImageWithRelation($productVariantId, $newImage);
                    array_push($insertedImages, $newImageId);
                }
                else {
                    array_push($insertedImages, $existingImageId);
                }
            }

            foreach ($imageResult as $key => $image) {
                $imageInArray = false;
                foreach ($newImages as $key => $newImage) {
                    if ($newImage['imageUrl'] == $image->imageUrl) {
                        $imageInArray = true;
                        break;
                    }
                }
                if(!$imageInArray) {
                    if($newMainImageExists) {
                        array_push($resultImages, [
                            'file_name' => $image->imageUrl,
                            'default' => false
                        ]);
                    }
                    else {
                        array_push($resultImages, [
                            'file_name' => $image->imageUrl,
                            'default' => $image->is_default
                        ]);
                    }
                }
            }
            return ['images' => $resultImages, 'imageIds' => $insertedImages];
        }
        else return ['images' => [], 'imageIds' => []];
    }

    protected function getImages($imageFilenames, $productVariantId) {
        $imageResult = app('db')->connection('mysql')
            ->select('
                select i.image_url as imageUrl, i.is_default as is_default, i.id as id
                from image i
                inner join product_variant_image pvi on i.id = pvi.image_id
                where 
                    i.deleted = 0
                    and pvi.product_variant_id = ' . $productVariantId . '
                    and image_url IN (\'' . implode('\',\'', $imageFilenames) . '\')
            ');
        if ($imageResult) {
            return $imageResult;
        }
        else return [];
    }


    protected function getProductVariantImages($productVariantId) {
        $imageResult = app('db')->connection('mysql')
            ->select('
                select i.image_url as imageUrl, i.is_default as is_default, i.id as id
                from image i
                inner join product_variant_image pvi on i.id = pvi.image_id
                where 
                    i.deleted = 0
                    and pvi.product_variant_id = ' . $productVariantId . '
            ');
        if ($imageResult) {
            return $imageResult;
        }
        else return [];
    }

    protected function saveImage($image)
    {
        $pdo = app('db')->connection('mysql')->getPdo();
        $result = app('db')->connection('mysql')->table('image')
            ->insert([
                'image_url' => $image['imageUrl'],
                'is_default' => $image['is_default'] ? 1 : 0,
                'deleted' => 0
            ]);

        if ($result)  {
            $imageId = $pdo->lastInsertId();
        }
        else
        {
            Log::error('Insert image to database failed' . $result);
            return null;
        }
        return $imageId;
    }

    protected function saveImageWithRelation($productVariantId, $image)
    {
        $imageId = $this->saveImage($image);
        if (!is_null($imageId)) {
            app('db')->connection('mysql')->table('product_variant_image')
                ->insert([
                    'image_id' => $imageId,
                    'product_variant_id' => $productVariantId
                ]);
        }
        return $imageId;
    }



    ////ScheduledProductUpdate

    public function getProductInfos()
    {
        $i = 0;
        while($i < 1000) {
            $res = $this->getProductListFromOzon($i);

            foreach ($res['result']['items'] as $key => $product) {
                $response = $this->getProductFromOzon($product['product_id']);
                $result = json_decode($response, true);
                if (isset($result['result'])) {
                    app('db')->connection('mysql')->table('product_temp')
                    ->insert([
                        'ozon_product_id' => $result['result']['id'],
                        'mall_variant_id' => $result['result']['offer_id'],
                        'state' => $result['result']['state'],
                        'visible' => boolval($result['result']['visible']) ? "true" : "false",
                        'active' => boolval($result['result']['visibility_details']['active_product']) ? "true" : "false",
                        'is_enabled' => count($result['result']['sources']) > 0 ? boolval($result['result']['sources'][0]['is_enabled']) ? "true" : "false" : "",
                        'has_price' => boolval($result['result']['visibility_details']['has_price']) ? "true" : "false",
                        'has_stock' => boolval($result['result']['visibility_details']['has_stock']) ? "true" : "false"
                    ]);
                }
            }
            $i++;
        }
    }

    public function getProductListFromOzon($p)
    {
        $data = [
            'filter' => ['visibility' => 'ALL'],
            'page' => $p,
            'page_size' => 1000
        ];
        Log::info($this->interactionId . ' => Get products from ozon:' . json_encode($data));
        $response = $this->sendData($this->productListUrl, $data);
        $response = $response['response'];
        Log::info($this->interactionId . ' => Ozon products info: ' . $response);
        $result = json_decode($response, true);
        return $result;
    }

    public function syncProducts()
    {
        $updatedProducts = array();
        $productResult = app('db')->connection('mysql')->select('select id from product order by update_date asc limit ' . config('app.sync_portion') . '');
        $productResult = array_map('current', json_decode(json_encode($productResult), true));

        if(count($productResult) > 0) {
            $result = app('db')->connection('mysql')->select('
                select 
                    pv.id as id,
                    pv.product_id as productId,
                    pv.mall_variant_id as mallVariantId,
                    pv.ozon_product_id as ozonProductId,
                    p.name as name,
                    p.description as description,
                    pv.price as price,
                    i.image_url as imageUrl,
                    p.sku as sku
                from product p
                inner join product_variant pv on p.id = pv.product_id
                left join product_variant_image pvi on pv.id = pvi.product_variant_id
                left join image i on i.id = pvi.image_id
                where pv.deleted = 0 and i.deleted = 0 and i.is_default = 1 and product_id in (' . implode(',', $productResult) . ')
            ');
        }
        else {
            $result = [];
        }

        $notifyingProducts = [];
        $notifyingProductIds = [];
        if (count($result) > 0) {
            foreach ($result as $key => $productVariant) {
                if (!in_array($productVariant->productId, $updatedProducts)) {
                    array_push($updatedProducts, $productVariant->productId);
                    $productInfo = $this->getProductFullInfo($productVariant->productId);

                    if($productInfo) {
                        $updateFields = [];
                        $updateProductFields = ['update_date' => date('Y-m-d\TH:i:s.u')];
                        foreach ($productInfo['variants'] as $key => $variant) {
                            if($productVariant->mallVariantId == $variant['mallVariantId']) {
                                if (
                                    ($variant['price'] != $productVariant->price)
                                    || $productInfo['name'] != $productVariant->name
                                    || $productInfo['imageUrl'] != $productVariant->imageUrl)
                                {
                                    $updateFields['price'] = $variant['price'];
                                    $updateProductFields['name'] = $productInfo['name'];
                                    if ($productInfo['imageUrl'] != $productVariant->imageUrl) {
                                        app('db')->connection('mysql')->table('image')
                                        ->where('deleted', 0)
                                        ->where('product_variantId', $productVariant->id)
                                        ->update(['image_url' => $productInfo['imageUrl']]);
                                    }
                                }
    
                                if (count($updateFields) > 0 || count($updateProductFields) > 1) {
                                    if (count($updateFields) > 0) {
                                        app('db')->connection('mysql')->table('product_variant')
                                        ->where('id', $productVariant->id)
                                        ->update($updateFields);
                                    }
    
                                    app('db')->connection('mysql')->table('product')
                                        ->where('id', $productVariant->productId)
                                        ->update($updateProductFields);
    
                                    $product = [
                                        'name' => $productInfo['name'],
                                        'description' => $productInfo['description'],
                                        'imageUrl' => $productInfo['imageUrl'],
                                        'galleryImages' => $productInfo['galleryImages'],
                                        'combinations' => $productInfo['variants']
                                    ];
        
                                    array_push($notifyingProductIds, $productVariant->productId);
                                    array_push($notifyingProducts, [
                                        'type' => 'update',
                                        'notified' => 0,
                                        'data' => json_encode($product),
                                        'product_id' => $productVariant->productId,
                                        'mall_variant_id' => $productVariant->sku
                                    ]);
                                }
                            }
                        }
                    }
                    else {
                        app('db')->connection('mysql')->table('image')
                            ->where('product_variantId', $productVariant->id)       
                            ->update(['deleted' => 1]);

                        $updatedFields['deleted'] = 1;
                        app('db')->connection('mysql')->table('product_variant')
                            ->where('id', $productVariant->id)
                            ->update($updateFields);

                        array_push($notifyingProductIds, $productVariant->productId);
                        array_push($notifyingProducts, [
                            'type' => 'delete',
                            'notified' => 0,
                            'product_id' => $productVariant->productId,
                            'mall_variant_id' => $productVariant->sku
                        ]);
                    }
                }
            }

            app('db')->connection('mysql')->table('product_notification')
                ->insert($notifyingProducts);

            $ozonProductList = $this->getProductListFromOzon();

            foreach ($ozonProductList['result']['items'] as $key => $ozonProductId) {
                $productExists = false;
                foreach ($result as $key => $productVariant) {
                    if ($ozonProductId['product_id'] == $productVariant->ozonProductId) {
                        $productExists = true;
                    }
                }

                if (!$productExists) {
                    $ozonProductInfo = $this->getProductInfo($ozonProductId['product_id']);

                    if (isset($ozonProductInfo['result'])) {
                        $productVariant = app('db')->connection('mysql')->table('product_variant')
                            ->where('mall_variant_id', $ozonProductInfo['result']['offer_id'])
                            ->first();
                        if (!$productVariant) {
                            $productId = null;
                            $product = app('db')->connection('mysql')->table('product')
                                ->where('sku', $ozonProductInfo['result']['barcode'])
                                ->first();

                            if(!$product) {
                                $pdo = app('db')->connection('mysql')->getPdo();
                                $productResult = app('db')->connection('mysql')->table('product')
                                    ->insert([
                                        'name' => $ozonProductInfo['result']['name'],
                                        'sku' => $mallItemId,
                                        'enabled' => $ozonProductInfo['result']['visibility_details']['active_product'],
                                        'update_date' => date('Y-m-d\TH:i:s.u'),
                                        'unlimited' => !$ozonProductInfo['result']['visibility_details']['has_stock'],
                                        'quantity' => $ozonProductInfo['result']['stock'],
                                        'price' => $ozonProductInfo['result']['price'],
                                        'default_category' => $ozonProductInfo['result']['category_id']
                                    ]); 

                                if ($productResult) {
                                    $productId = $pdo->lastInsertId();
                                }
                            }
                            else {
                                $productId = $product->id;
                            }

                            if (!is_null($productId)) {
                                $pdo = app('db')->connection('mysql')->getPdo();
                                $productVariantResult = app('db')->connection('mysql')->table('product_variant')
                                    ->insert([
                                        'product_id' => $productId,
                                        'mall_variant_id' => $ozonProductInfo['result']['offer_id'],
                                        'price' => $ozonProductInfo['result']['price'],
                                        'inventory' => $ozonProductInfo['result']['stock'],
                                        'ozon_product_id' => $ozonProductInfo['result']['id'],
                                        'deleted' => 0,
                                        'sent' => 1
                                    ]);
                                if ($productVariantResult) {
                                    $productVariantId = $pdo->lastInsertId();

                                    $mainImage = true;
                                    foreach ($ozonProductInfo['result']['images'] as $key => $imageUrl) {
                                        $this->saveImageWithRelation($productVariantId, [
                                            'imageUrl' => $imageUrl,
                                            'is_default' => $mainImage
                                        ]);
                                        if ($mainImage) {
                                            $mainImage = false;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $notifyingProductIds;
    }

    protected function getMallItemId($mallVariantId)
    {
        $mallItemId = null;
        $mallItemResult = app('db')->connection('mysql')->table('mallItem_mallVariant')
            ->where('mall_variant_id', $mallVariantId)
            ->first();
        if ($mallItemResult) {
            $mallItemId = $mallItemResult->mall_item_id;
        }
        return $mallItemId;
    }



    ////Orders
    public function getLastOrder()
    {
        $halfHourAgo = new \DateTime();
        $halfHourAgo->sub(new \DateInterval('PT30M'));
        $halfHourAgo->sub(new \DateInterval('PT3H'));
        $since = new \DateTime();
        $since->sub(new \DateInterval('PT5H'));
        $data = [
            'since' => $since->format('Y-m-d') . 'T' . $since->format('H:i:s') .'.000Z',
            'to' => $halfHourAgo->format('Y-m-d') . 'T' . $halfHourAgo->format('H:i:s') .'.999Z',
            //'since' => $since->format('Y-m-d\TH:i:s.u'),
            //'to' => $to->format('Y-m-d\TH:i:s.u'),
            'statuses' => [config('app.ozon_order_status.AWAITING_APPROVE')],
            'delivery_schema' => 'crossborder',
            'page' => 1,
            'page_size' => 100
        ];
        $response = $this->sendData($this->ordersUrl, $data);
        $response = $response['response'];
        $result = json_decode($response, true);

        if (isset($result['result']) && isset($result['result']['orders']) && isset($result['result']['orders'][0])) {
            $orders = $result['result']['orders'];
            usort($orders, function($o1, $o2) {
                return intval($o2['order_id']) - intval($o1['order_id']);
            });

            $order = $this->getOzonOrderInfo($orders[0]['order_id']);

            if (!isset($order['error']) && !isset($order['errorCode'])) {
                return $this->mapOrder($order['result']);
            }

            return ['error' => 'Error while getting last order!'];
        }
        else return ['error' => 'Error while getting last order!'];
    }

    public function checkApprovedOrders()
    {
        $orders = app('db')->connection('mysql')
            ->select('select ozon_order_id as ozonOrderId, ozon_order_nr as ozonOrderNr from orders where update_date IS NOT NULL AND update_date < (NOW() - INTERVAL 7 DAY) and status IN ("' . config('app.ozon_order_status.AWAITING_APPROVE') . '", "' . config('app.ozon_order_status.AWAITING_PACKAGING') . '")');

        if ($orders) {
            $notificationService = new NotificationService();
            foreach ($orders as $key => $order) {
                $notificationResult = $notificationService->sendWeekAgoOrderEmail($order->ozonOrderNr);
                if (isset($notificationResult['sendCount'])) {
                    $date = new \DateTime();
                    app('db')->connection('mysql')->table('orders')
                        ->where('ozon_order_id', $order->ozonOrderId)
                        ->update([
                            'status' => config('app.ozon_order_status.AWAITING_PACKAGING_TOO_LONG'),
                            'update_date' => $date->format('Y-m-d H:i:s')
                            ]);
                }
            }
        }
    }

    public function getOrderList()
    {
        $data = [
            'delivery_schema' => 'crossborder'
        ];

        Log::debug($this->interactionId . ' => Get orders from ozon:' . json_encode($data, JSON_UNESCAPED_UNICODE));
        $response = $this->sendData($this->orderListUrl, $data);
        $response = $response['response'];
        Log::info($this->interactionId . ' => Ozon order info: ' . $response);
        $result = json_decode($response, true);

        $notifyingOrders = [];
        $notifyingOrderIds = [];
        $ozonOrders = $result['result'];

        $toApprove = [];//список пришедших заказов от ozon'а
        $i = 0;
        foreach ($ozonOrders as $key => $ozonOrder) {
            $toApprove[$ozonOrder['order_id']] = true;
            $existedOrder = app('db')->connection('mysql')->table('orders')
                ->where('ozon_order_id', $ozonOrder['order_id'])
                ->first();

            if (!$existedOrder) {
                if ($i > 30) {
                    continue;
                }
                
                $orderInfo = null;
                try {
                    $orderInfo = $this->getOzonOrderInfo($ozonOrder['order_id']);
                }
                catch (\Exception $e) {
                    continue;
                }

                if (!isset($orderInfo['result'])) {
                    continue;
                }
                
                $orderInfo = $orderInfo['result'];
                $orderResult = app('db')->connection('mysql')->table('orders')
                    ->insert([
                        'ozon_order_id' => $orderInfo['order_id'],
                        'create_date' => $orderInfo['order_time'],
                        'status' => $orderInfo['status'],
                        'deleted' => 0,
                        'ozon_order_nr' => $orderInfo['order_nr']
                    ]);
                if ($orderResult) {
                    array_push($notifyingOrderIds, $orderInfo['order_nr']);
                    $notifyingOrders = $this->processOrderNotification($notifyingOrders, [
                        'data' => null,
                        'type' => 'create',
                        'notified' => 0,
                        'order_id' => $orderInfo['order_id'],
                        'order_nr' => $orderInfo['order_nr']
                    ]);
                }
                $i++;
            } else {
                //Заказ уже есть, проверяем не вышло ли 4 часа для отмены
                $dt = new \DateTime($existedOrder->create_date);
                $dt->add(new \DateInterval('PT3H'));
                $dt->add(new \DateInterval('PT4H'));
                $dtnow = new \DateTime();
                if($dtnow > $dt){
                    //4 часа вышло - пора аппрувить на ozon'е
                    $statusResult = $this->setOrderStatus($ozonOrder['order_id'], config('app.order_approve_status'), null, null);
                    if (isset($statusResult['response']) && !is_null($statusResult['response']) && !isset($statusResult['response']['error'])) {
                        //апрув в ozon'е прошёл. Надо заапрувить в DS
                        array_push($notifyingOrderIds, $existedOrder->ozon_order_nr);
                        $notifyingOrders = $this->processOrderNotification($notifyingOrders, [
                            'data' => null,
                            'type' => 'approve',
                            'notified' => 0,
                            'order_id' => $existedOrder->ozon_order_id,
                            'order_nr' => $existedOrder->ozon_order_nr
                        ]);
                    }
                }
            }
        }

        //Проверка на отмену
        $orders = app('db')->connection('mysql')->table('orders')
            ->where('status', config('app.ozon_order_status.AWAITING_APPROVE'))
            ->where('deleted', 0)
            ->get();
        if ($orders) {
            foreach ($orders as $key => $order) {
                if(!isset($toApprove[$order->ozon_order_id])){
                    //от ozon'а не пришёл - отменён
                    $orderInfo = $this->getOzonOrder($order->ozon_order_id);
                    if (isset($orderInfo['result']) && strtoupper($orderInfo['result']['status']) == config('app.ozon_order_status.CANCELLED')) {
                        array_push($notifyingOrderIds, $order->ozon_order_nr);
                        $notifyingOrders = $this->processOrderNotification($notifyingOrders, [
                            'data' => null,
                            'type' => 'decline',
                            'notified' => 0,
                            'order_id' => $order->ozon_order_id,
                            'order_nr' => $order->ozon_order_nr
                        ]);
    
                        $date = new \DateTime();
                        app('db')->connection('mysql')->table('orders')
                            ->where('ozon_order_id', $order->ozon_order_id)
                            ->update([
                                'status' => config('app.ozon_order_status.CANCELLED'),
                                'update_date' => $date->format('Y-m-d H:i:s')
                                ]);
                    }
                }
            }
        }

        app('db')->connection('mysql')->table('order_notification')
            ->insert($notifyingOrders);

        return $notifyingOrderIds;
    }

    public function processOrderNotification($notifyingOrders, $notification)
    {
        try {
            GearmanService::addOrderNotification($notification);
        }
        catch (\Exception $e) {
            Log::error('Adding order notification message to gearman queue failed!');
        }

        $orderNotificationResult = app('db')->connection('mysql')->table('order_notification')
            ->where('order_id', $notification['order_id'])
            ->where('type', $notification['type'])
            ->where('notified', $notification['notified'])
            ->first();

        if(!$orderNotificationResult) {
            array_push($notifyingOrders, [
                'data' => $notification['data'],
                'type' => $notification['type'],
                'notified' => $notification['notified'],
                'order_id' => $notification['order_id'],
            ]);
        }
        return $notifyingOrders;
    }

    public function getOrderInfoCommon($orderNr)
    {
        if (strpos(strval($orderNr), '-')  !== false) {
            return $this->getOrderInfoByNr($orderNr);
        }
        else {
            return $this->getOrderInfoById($orderNr);
        }
    }

    public function getOrderInfoByNr($orderNr)
    {
        $orderResult = app('db')->connection('mysql')
            ->table('orders')
            ->where('deleted', 0)
            ->where('ozon_order_nr', $orderNr)
            ->first();
        return $this->getOrderInfo($orderResult);
    }

    public function getOrderInfoById($orderId)
    {
        $orderResult = app('db')->connection('mysql')
            ->table('orders')
            ->where('deleted', 0)
            ->where('ozon_order_id', $orderId)
            ->first();
        return $this->getOrderInfo($orderResult);
    }

    protected function getOrderInfo($orderResult)
    {
        if (!$orderResult)  {
            Log::error('Order doesn\'t exists!');
            return [
                'errorCode' => 'ORDER_NOT_EXISTS',
                'errorMessage' => 'Order with Id doesn\'t exists!'
            ];
        }
        $order =  $this->getOzonOrderInfo($orderResult->ozon_order_id);

        if (!isset($order['error']) && !isset($order['errorCode'])) {
            return $this->mapOrder($order['result']);
        }
        return $order;
    }

    public function getOzonOrderInfo($ozonOrderId)
    {
        $order = $this->getOzonOrder($ozonOrderId);

        if (isset($order['result'])) {
            $fullItems = array();
            $productSuccess = true;
            foreach ($order['result']['items'] as $key => $item) {
                $product = app('db')->connection('mysql')
                    ->select('select pv.product_id as productId, p.description as description, pv.mall_variant_id as mallVariantId from product_variant pv
                        left join product p on p.id = pv.product_id
                        where ozon_product_id = ' . $item['product_id']);
                $ozonProduct = $this->getProductInfo($item['product_id']);

                if (!isset($ozonProduct['result'])) {
                    $productSuccess = false;
                    continue;
                }

                $productName = '';
                $productImage = '';
                if (isset($ozonProduct['result']) && !is_null($ozonProduct['result'])) {
                    $productName = $ozonProduct['result']['name'];
                    if (isset($ozonProduct['result']['images'][0])) {
                        $productImage = $ozonProduct['result']['images'][0];                        
                    }
                }

                $productId = null;
                $mallVariantId = null;
                $description = null;
                if (count($product) > 0) {
                    $productId = $product[0]->productId;
                    $mallVariantId = $product[0]->mallVariantId;
                    $description = $product[0]->description;
                }
                if (is_null($mallVariantId)) {
                    $mallVariantId = $item['offer_id'];
                }

                array_push($fullItems, [
                    'product_id' => $productId,
                    'mallVariantId' => $mallVariantId,
                    'item_id' => $item['item_id'],
                    'ozon_product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'offer_id' => $item['offer_id'],
                    'price' => $item['price'],
                    'tracking_number' => $item['tracking_number'],
                    'status' => $item['status'],
                    'cancel_reason_id' => $item['cancel_reason_id'],
                    'auto_cancel_date' => $item['auto_cancel_date'],
                    'shipping_provider_id' => $item['shipping_provider_id'],
                    'name' => $productName,
                    'imageUrl' => $productImage,
                    'smallThumbnailUrl' => $productImage,
                    'description' => $description,
                    'shipping' => 0
                ]);
            }

            if (!$productSuccess) {
                return ['error' => true, 'errorCode' => 'NO_ITEM_INFO'];
            }

            $order['result']['items'] = $fullItems;
            return $order;
        }
        else {
            return $order;
        }
    }

    protected function getOzonOrder($ozonOrderId)
    {
        Log::info($this->interactionId . ' => Get order info from ozon: ' . $ozonOrderId);
        $response = $this->sendData(str_replace('{orderId}', $ozonOrderId, $this->orderInfoUrl), null);
        $response = $response['response'];
        Log::info($this->interactionId . ' => Ozon order info: ' . $response);
        $order = json_decode($response, true);
        return $order;
    }

    protected function mapOrder($order)
    {
        $date = new \DateTime($order['order_time']);
        $date->modify('+3 hour');
        $date_approve_at = new \DateTime($order['order_time']);
        $date_approve_at->modify('+3 hour');
        $date_approve_at->add(new \DateInterval('P3D'));
        $status = $this->mapOrderStatus($order['status']);
        $response = [
            'order_id' => $order['order_nr'],
            'number' => $order['order_nr'],
            'ozon_order_id' => $order['order_id'],
            'paymentStatus' => 'PAID',
            'fulfillmentStatus' => $status,
            'email' => $order['address']['email'],
            //'ipAddress': {ipAddress}, ??
            'refererUrl' => 'http://ozon.ru/',
            'createDate' => $date->format('Y-m-d H:i:s'),
            'approveAt' => $date_approve_at->format('Y-m-d H:i:s'),
            'customerId' => $order['customer_id'],
            'shippingPerson' => [
                'name' => $order['address']['addressee'],
                'phone' => $order['address']['phone'],
                'postalCode' => $order['address']['zip_code'],
                'city' => $order['address']['city'],
                'street' => $order['address']['address_tail'],
                'countryCode' => $this->mapCountryCode($order['address']['country']),
                'stateOrProvinceName' => $order['address']['region'],
                'stateOrProvinceCode' => '-'
            ],
            'items' => array()
        ];

        foreach ($order['items'] as $key => $item) {
            array_push($response['items'], [
                'item_id' => $item['item_id'],
                'product_id' => $item['product_id'],
                'ozon_product_id' => $item['ozon_product_id'],
                'mallVariantId' => $item['mallVariantId'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'name' => $item['name'],
                'imageUrl' => $item['imageUrl'],
                'smallThumbnailUrl' => $item['smallThumbnailUrl'],
                'shipping' => $item['shipping'],
                'description' => is_null($item['description']) ? '   ' : $item['description'],
                'shippingProviderId' => $item['shipping_provider_id']
            ]);
        }
        return $response;
    }

    protected function mapOrderStatus($status)
    {
        return config('app.order_status.' . strtoupper($status));
    }

    protected function mapCountryCode($countryName)
    {
        $result = app('db')->connection('mysql')->table('country')
            ->where('name', $countryName)
            ->first();
        if ($result) {
            return $result->code;
        }
        else {
            app('db')->connection('mysql')->table('country')
                ->insert([
                    'name' => $countryName
                ]);
            return $countryName;
        }
    }

    public function setOrderStatus($orderNr, $status, $trackingNumber, $orderItems)
    {
        $response = null;
        $errorResponse = null;
        $toApprove = [];
        $toCancel = [];
        $toShipped = [];
        $httpCode = null;

        $order = $this->getOrderInfoCommon($orderNr);
        if (!is_null($order) && !isset($order['error']) && !isset($order['errorCode'])) {
            if(strtoupper($status) == strtoupper(config('app.order_cancel_status'))) {
                foreach ($order['items'] as $key => $ozonOrderItem) {
                    array_push($toCancel, $ozonOrderItem['item_id']);
                }
            }
            elseif(is_null($orderItems) && strtoupper($status) == strtoupper(config('app.order_approve_status'))) {
                foreach ($order['items'] as $key => $ozonOrderItem) {
                    array_push($toApprove, $ozonOrderItem['item_id']);
                }
            }
            else {
                foreach ($orderItems as $key => $orderItem) {
                    $item = null;
                    foreach ($order['items'] as $key => $ozonOrderItem) {
                        if ($orderItem['mallVariantId'] == $ozonOrderItem['mallVariantId']) {
                            $item = $ozonOrderItem;
                            break;
                        }
                    }

                    if (is_null($item)) {
                        Log::error($this->interactionId . ' => Item not found: ' . json_encode($orderItem['mallVariantId'], JSON_UNESCAPED_UNICODE));
                        continue;
                    }

                    if ($orderItem['status'] == config('app.order_item_status.ST_PAYMENT_CANCELLED')
                    || $orderItem['status'] == config('app.order_item_status.ST_DECLINED')
                    || $orderItem['status'] == config('app.order_item_status.ST_REFUNDED')) {
                        array_push($toCancel, $item['item_id']);
                    }
                    elseif ($orderItem['status'] == config('app.order_item_status.ST_SHIPPED') || $orderItem['status'] == config('app.order_item_status.ST_DELIVERED')) {
                        if (isset($orderItem['quantity']) && !is_null($orderItem['quantity'])
                        && isset($orderItem['trackingNumber']) && !is_null($orderItem['trackingNumber'])) {
                            array_push($toShipped, [
                                'item_id' => $item['item_id'],
                                'quantity' => $orderItem['quantity'],
                                'trackingNumber' => $orderItem['trackingNumber']
                            ]);
                        }
                    }
                    elseif ($orderItem['status'] == config('app.order_item_status.ST_PAYMENT_APPROVED')
                    || $orderItem['status'] == config('app.order_item_status.ST_FULFILLING')) {
                        array_push($toApprove, $item['item_id']);
                    }
                }
            }

            if(strtoupper($status) == strtoupper(config('app.order_approve_status')))
            {
                Log::info($this->interactionId . ' => Approve ozon order:' . strval($order['ozon_order_id']));
                $response = $this->sendData($this->approveOrderUrl, [
                    'order_id' => $order['ozon_order_id'],
                    'item_ids' => $toApprove
                ]);
                    $httpCode = $response['http_code'];
                    $response = $response['response'];
                $response = json_decode($response, true);
                Log::info($this->interactionId . ' => Approve ozon order result: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
                
                if (!isset($response['error'])) {
                    $date = new \DateTime();
                    app('db')->connection('mysql')->table('orders')
                        ->where('ozon_order_id', $order['ozon_order_id'])
                        ->update([
                            'status' => config('app.ozon_order_status.AWAITING_PACKAGING'),
                            'update_date' => $date->format('Y-m-d H:i:s')
                            ]);
                }
            }
            if(strtoupper($status) == strtoupper(config('app.order_cancel_status')) || count($toCancel) > 0)
            {
                if (strtoupper($status) == strtoupper(config('app.order_cancel_status'))) {
                    Log::info($this->interactionId . ' =>Cancel ozon order:' . strval($order['ozon_order_id']));
                }
                Log::info($this->interactionId . ' =>Cancel ozon order items:' . json_encode($toCancel));
                $response = $this->sendData($this->cancelOrderUrl, [
                    'order_id' => $order['ozon_order_id'],
                    'reason_code' => config('app.order_cancel_reason'),
                    'item_ids' => $toCancel
                ]);
                $httpCode = $response['http_code'];
                $response = $response['response'];

                if (strtoupper($status) == strtoupper(config('app.order_cancel_status'))) {
                    $date = new \DateTime();
                    app('db')->connection('mysql')->table('orders')
                        ->where('ozon_order_id', $order['ozon_order_id'])
                        ->update([
                            'status' => config('app.ozon_order_status.CANCELLED'),
                            'update_date' => $date->format('Y-m-d H:i:s')
                            ]);
                }

                $response = json_decode($response, true);
                Log::info($this->interactionId . ' => Cancel ozon order result: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
            }
            if (strtoupper($status) == strtoupper(config('app.order_ship_status'))) {
                foreach ($toShipped as $key => $orderItemShipped) {
                    $shippingProviderCode = config('app.russianpost_shipping_provider');
                    if (boolval(preg_match('/^JNTCU(\d){10}YQ$/i', $orderItemShipped['trackingNumber']))) {
                        $shippingProviderCode = config('app.jnet_shipping_provider');
                    }
                    if (boolval(preg_match('/^PE(.){7}CRU$/i', $orderItemShipped['trackingNumber']))) {
                        $shippingProviderCode = config('app.ponyexpress_shipping_provider');
                    }

                    Log::info($this->interactionId . ' => Ship ozon order:' . strval($order['ozon_order_id']));
                    $response = $this->sendData($this->shipOrderUrl, [
                        'order_id' => $order['ozon_order_id'],
                        "shipping_provider_id" => $shippingProviderCode,
                        "tracking_number" => $orderItemShipped['trackingNumber'],
                        'items' => [[
                            'item_id' => $orderItemShipped['item_id'],
                            'quantity' => $orderItemShipped['quantity']
                        ]]
                    ]);
                    $httpCode = $response['http_code'];
                    $response = $response['response'];
                    $response = json_decode($response, true);
                    Log::info($this->interactionId . ' => Ship ozon order result: ' . json_encode($response, JSON_UNESCAPED_UNICODE));

                    if (isset($response['error'])) {
                        $errorResponse = $response;
                    }
                    elseif (is_null($response)) {
                        $errorResponse = ['error' => 'Empty response'];
                    }

                    $nonshippedItems =  false;
                    $orderInfo = $this->getOzonOrder($order['ozon_order_id']);
                    if (isset($orderInfo['result']) && isset($orderInfo['result']['items'])) {
                        foreach ($orderInfo['result']['items'] as $key => $orderItem) {
                            if ($orderItem['status'] == config('app.ozon_order_item_status.awaiting_packaging') || $orderItem['status'] == config('app.ozon_order_item_status.awaiting_approve')) {
                                $nonshippedItems = true;
                                break;
                            }
                        }
                    }
                    else {
                        $nonshippedItems =  true;
                    }

                    if (!$nonshippedItems) {
                        $date = new \DateTime();
                        app('db')->connection('mysql')->table('orders')
                            ->where('ozon_order_id', $order['ozon_order_id'])
                            ->update([
                                'status' => config('app.ozon_order_status.DELIVERING'),
                                'update_date' => $date->format('Y-m-d H:i:s')
                                ]);
                    }
                }
            }
            if(strtoupper($status) == strtoupper(config('app.order_delivered_status')))
            {
                $response = ['success' => true];
                $httpCode = 200;
            }
        }

        if (!is_null($response)) {
            return [
                'order_id' => $orderNr,
                'fulfillmentStatus' => $status,
                'response' => is_null($errorResponse) ? $response : $errorResponse,
                'success' => !isset($response['error']),
                'http_code' => is_null($errorResponse) ? $httpCode : 500
            ];
        }
        else {
            return [
                'order_id' => $orderNr,
                'fulfillmentStatus' => $status,
                'response' => 'null',
                'http_code' => $httpCode
            ];
        }
    }

    public function setOrderNr()
    {
        $orders = app('db')->connection('mysql')->table('orders')
            ->whereNull('ozon_order_nr')
            ->take(20)
            ->get();
        if ($orders) {
            foreach ($orders as $key => $order) {
                $orderInfo = $this->getOrderInfo($order->ozon_order_id);
                Log::debug(json_encode($orderInfo, JSON_UNESCAPED_UNICODE));
                if (!is_null($orderInfo) && !isset($orderInfo['error']) && !isset($orderInfo['errorCode'])) {
                    $orders = app('db')->connection('mysql')->table('orders')
                        ->where('id', $order->id)
                        ->update(['ozon_order_nr' => $orderInfo['order_nr']]);
                }
            }
        }
    }


    //Categories
    public function getCategory($id)
    {
        $category = app('db')->connection('mysql')->table('ozon_category')
            ->where('id', $id)
            ->first();
        if ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name
            ];
        }
        else {
            return null;
        }
    }

    public function getCategoryList()
    {
        $categories = app('db')->connection('mysql')->table('ozon_category')
            ->select('id', 'name')
            ->get();
        if ($categories) {
            return $categories;
        }
        else {
            return null;
        }
    }

    public function insertCategories()
    {
        $response = $this->sendData($this->categoryListUrl, null);
        $response = $response['response'];
        $result = json_decode($response, true);
        $this->insertChildCategories($result['result'], '');
    }

    protected function insertChildCategories($categories, $path)
    {
        foreach ($categories as $key => $category) {
            if(is_null($category['children'])) {
                try {
                    $categoryResult = app('db')->connection('mysql') ->table('ozon_category')
                    ->insert([
                        'id' => $category['category_id'],
                        'name' => $category['title'],
                        'path' => trim(($path . ' -> ' . $category['title']), ' -> ')
                    ]);
                } catch (\Exception $e) {
                    $categoryResult = false;
                }
                if ($categoryResult) {
                    $response = $this->sendData(str_replace('{category_id}', $category['category_id'], $this->categoryAttributeListUrl), null);
                    $response = $response['response'];
                    $result = json_decode($response, true);
                    foreach ($result['result'] as $key => $attribute) {
                        if(!in_array($attribute['id'], $this->attributes)) {
                            array_push($this->attributes, $attribute['id']);
                            if($attribute['type'] == 'option') {
                                try {
                                    $attributeResult = app('db')->connection('mysql')->table('attribute')
                                        ->insert([
                                            'id' => $attribute['id'],
                                            'name' => $attribute['name'],
                                            'description' => $attribute['description'],
                                            'is_collection' => $attribute['is_collection'],
                                            'required' => $attribute['required']
                                        ]);
                                }
                                catch (\Exception $e) {
                                    $attributeResult = true;
                                }
                                if ($attributeResult)  {
                                    try {
                                        app('db')->connection('mysql')->table('ozon_category_attribute')
                                        ->insert([
                                            'attribute_id' => $attribute['id'],
                                            'ozon_category_id' => $category['category_id']
                                        ]);
                                    }
                                    catch (\Exception $e) {
                                        $attributeResult = true;
                                    }
                                    foreach ($attribute['option'] as $key => $option) {
                                        try {
                                            app('db')->connection('mysql')->table('attribute_value')
                                            ->insert([
                                                'attribute_id' => $attribute['id'],
                                                'ozon_id' => $option['id'],
                                                'value' => $option['value']
                                            ]);
                                        }
                                        catch (\Exception $e) {
                                            $attributeResult = true;
                                        }
                                    }
                                }
                            }
                        }
                        else {
                            try {
                                app('db')->connection('mysql')->table('ozon_category_attribute')
                                    ->insert([
                                        'attribute_id' => $attribute['id'],
                                        'ozon_category_id' => $category['category_id']
                                    ]);
                            }
                            catch (\Exception $e) {
                            }

                        }
                    }
                }
            }
            else {
                 $this->insertChildCategories($category['children'], ($path . ' -> ' . $category['title']));
            }
        }
    }

    public function getChatIds()
    {
        $page = 1;
        $result = [];
        while (true){
            $chatList = $this->sendData('/v1/chat/list',['page_size'=>null,'page'=>$page/*'chat_id_list'=>['c7da6dab-7e78-4bdb-95f3-b6dd67ea69a5','867d9798-45ab-46f6-bc7e-3d5d78abc2a5']*/]);
            $response = json_decode($chatList['response'],1);
            if (empty($response['result'])){
                break;
            }
            foreach ($response['result'] as $responseItem)
            {
                if ($responseItem['last_message_id'] > 0)
                {
                    array_push($result,$responseItem);
                }

            }
            $page ++;
        }
        return array_column($result,'id') ;
    }
    public function getChats(){
        $page = 1;
        $ids = $this->getChatIds();
        $chatList = $this->sendData('/v1/chat/list',['page_size'=>50,'page'=>$page, 'chat_id_list'=>$ids]);
        $response = json_decode($chatList['response'],1);
        if (isset($response['error']))
        {
            Log::info('Get chats error: ' . $response['error']['message']);
            return false;
        }
        return $response['result'];
    }
    public function getChatMessages($chatId, $messageId = null, $limit = null){
        $messageData = $this->sendData('/v1/chat/updates',['chat_id'=>$chatId,'from_message_id'=>$messageId, 'limit'=>$limit]);
        return json_decode($messageData['response'],1);
    }
    public function isChatTicketExists($chatId)
    {
        $chatTicket = app('db')->connection('mysql')->table('chat_eddy_ticket')
            ->where('chat_id', $chatId)
            ->first();
        return $chatTicket ? $chatTicket->id > 0 : false;
    }
    public function addChatMessage($chatId,$text)
    {
        $isMessageAdded = $this->sendData('/v1/chat/send/message',['chat_id'=>$chatId,'text'=>strip_tags($text)]);
        return $isMessageAdded;
    }
    ////Common


    private function sendData($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            'Client-Id: ' . config('app.ozon_api_client_id'),
            'Api-Key: ' . config('app.ozon_api_key')
        );
        if (!is_null($data)) {
            $data_string = json_encode($data, JSON_UNESCAPED_UNICODE);
            $headers = array_merge($headers, ['Content-Type: application/json', 'Content-Length: ' . strlen($data_string)]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        Log::debug($this->interactionId . ' => Url: ' . $this->baseUrl . $url);
        Log::debug($this->interactionId . ' => Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Log::debug($this->interactionId . ' => Response http code:' . $http_code);
        Log::debug($this->interactionId . ' => Response data:' . json_encode($response));
        
        return [
            'http_code' => $http_code,
            'response' => $response
        ];
    }
}