<?php

namespace App\Services;
use Log;
use App\Services\DropshippService;
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

    protected $orderListUrl;
    protected $orderInfoUrl;
    protected $approveOrderUrl;
    protected $cancelOrderUrl;
    protected $shipOrderUrl;

    protected $categoryListUrl;
    protected $categoryAttributeListUrl;

    protected $attributes;

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
    
        $this->orderListUrl = config('app.ozon_orderlist_url');
        $this->orderInfoUrl = config('app.ozon_orderinfo_url');
        $this->approveOrderUrl = config('app.ozon_approveorder_url');
        $this->cancelOrderUrl = config('app.ozon_cancelorder_url');
        $this->shipOrderUrl = config('app.ozon_shiporder_url');
    
        $this->categoryListUrl = config('app.ozon_categorylist_url');
        $this->categoryAttributeListUrl = config('app.ozon_categoryattributelist_url');

        $this->attributes = [];
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
                $response = $this->getProductFromOzon($product->ozonProductId);

                $response = json_decode($response, true);
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

    public function getProductInfo($productId, $mallVariantId)
    {
        $ozonProductResult = $this->getOzonProductId($productId, $mallVariantId);
        if ($ozonProductResult['Success']) {
            $response = $this->getProductFromOzon($ozonProductResult['ozonProductId']);
            $result = json_decode($response, true);
            $result['result']['productVariant'] = [
                'id' => $ozonProductResult['id'],
                'mallVariantId' => $ozonProductResult['mallVariantId'],
                'productId' => $ozonProductResult['productId'],
                'ozonProductId' => $ozonProductResult['ozonProductId']
            ];
            return $result;
        }
        else return $ozonProductResult;
    }

    protected function getProductFromOzon($ozonProductId)
    {
        $interactionId = mt_srand();
        Log::info($interactionId . ' => Get product info from ozon:' . json_encode(['product_id' => $ozonProductId]));
        $response = $this->sendData($this->productInfoUrl, ['product_id' => $ozonProductId]);
        Log::info($interactionId . ' => Ozon products: ' . $response);
        return $response;
    }

    protected function getProductFromOzonByOfferId($offerId)
    {
        $interactionId = mt_srand();
        Log::info($interactionId . ' => Get product info from ozon by sku:' . json_encode(['offer_id' => $offerId]));
        $response = $this->sendData($this->productInfoUrl, ['offer_id' => $offerId]);
        Log::info($interactionId . ' => Ozon products: ' . $response);
        return $response;
    }

    public function createNewProduct($product)
    {
        $productId = 0;
        $result = null;
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
                    'mall_category_name' => $product['mallCategoryName']
                ]); 
            if ($result) {
                $productId = $pdo->lastInsertId();
            }
            else
            {
                Log::error('Insert product to database failed' . $result);
                return [
                    'Error' => 'Failed to insert product to database.'
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


        $variantIds = $this->createVariants($product['variants'], $productId);

        return ['id' => $productId];
    }

    protected function createVariants($variants, $productId)
    {
        $insertedVariants = [];
        foreach ($variants as $key => $variant) {
            try {
                $pdo = app('db')->connection('mysql')->getPdo();
                $result = app('db')->connection('mysql')->table('product_variant')
                    ->insert([
                        'product_id' => $productId,
                        'mall_variant_id' => $variant['mallVariantId'],
                        'color' => $variant['color'],
                        'size' => $variant['size'],
                        'price' => $variant['price'],
                        'inventory' => $variant['inventory'],
                        'deleted' => 0,
                        'sent' => 0
                    ]);
    
                if ($result) {
                    $variantId = $pdo->lastInsertId();
                    array_push($insertedVariants, $variantId);
                }
            } catch (\Exception $e) {
                
            }

        }

        return $insertedVariants;
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
                    i.image_url as imageUrl
                from product_variant pv
                left join product p on pv.product_id = p.id
                left join product_variant_image pvi on pv.id = pvi.product_variant_id
                left join image i on i.id = pvi.image_id
                    where pv.deleted = 0 and i.deleted = 0 and i.is_default = 1 and pv.ozon_product_id is null and pv.sent = 0
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
                    i.image_url as imageUrl
                    from product_variant pv
                    left join product p on pv.product_id = p.id
                    left join product_variant_image pvi on pv.id = pvi.product_variant_id
                    left join image i on i.id = pvi.image_id
                    where pv.deleted = 0 and i.deleted = 0 and i.is_default = 1 and pv.ozon_product_id is null and pv.sent = 0 and pv.product_id = ' . $productId . '
            ');
        }

        $errorsCategories = [];
        $errorAttributes = [];
        if ($variants) {
            $items = [];
            $variantIds = [];
            foreach ($variants as $key => $variant) {
                if (!is_null($variant->imageUrl)) {
                    $categoryResult = $this->getOzonCategory($variant->defaultCategory, $variant->mallCategoryId, $variant->mallCategoryName);
                    if (!isset($categoryResult['error'])) {
                        $item = $this->addProductToRequest(
                            $variant->mallVariantId,
                            $variant->sku,
                            $variant->description,
                            $variant->name,
                            $variant->price,
                            $variant->inventory,
                            $variant->weight,
                            !$variant->unlimited,
                            $variant->enabled,
                            $categoryResult['categoryId'],
                            $variant->color,
                            $variant->size,
                            $variant->imageUrl,
                            $productId
                        );
                        if (!isset($item['success'])) {
                            array_push($items, $item);
                            array_push($variantIds, $variant->id);
                        }
                        else {
                            array_push($errorAttributes, $item['error']);
                        }
                    }
                    else {
                        array_push($errorsCategories, $categoryResult['error']);
                    }
                }
            }

            $result = $this->sendProductsToOzon($items);
            if (isset($result['result']) && isset($result['result']['task_id'])) {
                app('db')->connection('mysql')->table('product_variant')
                    ->whereIn('id', $variantIds)
                    ->update(['sent' => 1]);
            }
        }
        return [
            'categories' => $errorsCategories,  
            'attributes' => $errorAttributes
        ];
    }

    public function setOzonProductId()
    {
        $variants = app('db')->connection('mysql')->table('product_variant')
            ->whereNull('ozon_product_id')
            ->get();

        $errors = [];
        foreach ($variants as $key => $variant) {
            $response = $this->getProductFromOzonByOfferId($variant->mall_variant_id);
            $ozonProduct = json_decode($response, true);
            if(isset($ozonProduct['result'])) {
                app('db')->connection('mysql')->table('product_variant')
                    ->where('id', $variant->id)
                    ->update(['ozon_product_id' => $ozonProduct['result']['id']]);
            }
            else {
                array_push($errors, 'Product with id=' . $variant->product_id . ' not created yet in ozon');
            }
        }

        return $errors;
    }

    protected function sendProductsToOzon($items) {
        $interactionId = mt_srand();
        Log::info($interactionId . ' => Import product request to ozon:' . json_encode(['items' => $items]));
        $response = $this->sendData($this->importProductsUrl, ['items' => $items]);
        Log::info($interactionId . ' => Import ozon products: ' . $response);
        $result = json_decode($response, true);
        return $result;
    }

    protected function addProductToRequest($mallVariantId, $sku, $description, $name, $price, $weight, $quantity, $unlimited, $enabled, $ozonCategoryId, $color, $size, $mainImageUrl, $productId)
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

            return [
                'barcode' => strval($sku),
                'description' => $description,
                'category_id' => $ozonCategoryId,
                'name' => $name,
                'offer_id' => $mallVariantId,
                'price' => strval($price),
                'vat'=> '0',
                'weight' => $weight,
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
            //if ((is_null($color) || isset($colorAttribute['attributeId'])) && (is_null($size) || isset($sizeAttribute['attributeId']))) {

            //}
            // else {
            //     $errorAttributes = array();
            //     if (!is_null($color) && !isset($colorAttribute['attributeId'])) {
            //         array_push($errorAttributes, 'Attribute color ' . $color . ' doesn\'t mapped correctly. Attribute map with ID=' . $colorAttribute['attributeMapId'] . ' was created.');
            //     }
            //     if (!is_null($size) && !isset($sizeAttribute['attributeId'])) {
            //         array_push($errorAttributes, 'Attribute size= ' . $size . ' doesn\'t mapped correctly. Attribute map with ID=' . $sizeAttribute['attributeMapId'] . ' was created.');
            //     }

            //     return [
            //         'success' => false,
            //         'error' => json_encode($errorAttributes)
            //     ];
            // }
        }
        else return [
            'success' => false
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

    public function getOzonCategory($defaultCategory, $mallCategoryId, $mallCategoryName)
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
                $result = app('db')->connection('mysql')->table('ozon_category')
                    ->where('name', $mallCategoryName)
                    ->first();
                if ($result) {
                    return $result->id;
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
                        Log::info($error);
                    }
                    catch (\Exception $e) {
                        Log::error('Category ' . $mallCategoryId . ' already exists');
                    }
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
                where avm.value =\'' . $value . '\'
                    and oc.id = \'' . $ozonCategoryId .'\'
                limit 1
        ');
        if (!$result) {
            $result = app('db')->connection('mysql')->select('
                select a.id as attributeId, av.ozon_id as attributeValueId, a.is_collection as isCollection from ozon_category oc
                    inner join ozon_category_attribute ca on oc.id = ca.ozon_category_id
                    inner join attribute a on ca.attribute_id = a.id
                    inner join attribute_value av on a.id = av.attribute_id
                    where av.value = \'' . $value . '\'
                        and oc.id = ' . $ozonCategoryId . '
                    limit 1
            ');
            if (!$result)  {
                $pdo = app('db')->connection('mysql')->getPdo();
                $result = app('db')->connection('mysql')->table('attribute_value_map')
                    ->insert([
                        'value' => $value
                    ]);
       
                if ($result)  {
                    $attributeMapId = $pdo->lastInsertId();
                }
                Log::error('Attribute with value ' . $value . ' doesn\'t exists! Attribute map with id=' . $attributeMapId . ' was created.');
                return [
                    'attributeMapId' => $attributeMapId
                ];
            }
        }  
        return [
            'attributeId' => $result[0]->attributeId,
            'attributeValueId' => $result[0]->attributeValueId,
            'isCollection' => $result[0]->isCollection
        ];
    }

    protected function setQuantity($items)
    {
        $interactionId = mt_srand();
        Log::info($interactionId . ' => Update stocks request to ozon:' . json_encode(['stocks' => [$items]]));
        $response = $this->sendData($this->updateStocksUrl, ['stocks' => [$items]]);
        Log::info($interactionId . ' => Update stocks response: ' . $response);
        $result = json_decode($response, true);
    }

    public function createProductCombination($productId, $combinations)
    {
        $product = app('db')->connection('mysql')->table('product')
            ->where('id', $productId)->first();
        if (!is_null($product)) {
            $variantIds = $this->createVariants($combinations, $productId);
            $result = [];
            foreach ($variantIds as $key => $id) {
                array_push($result, [
                    'store_variant_id' => $id
                ]);
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
            ->where('deleted', 0)
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
                'Error' => 'Product with ID ' . $productId . ' doesn\'t exists!'
            ];
        }
    }

    public function updateProduct($product, $productId)
    {
        $result = [];
        if (isset($product['variants']) && count($product['variants']) > 0) {

            if(!is_null($product->enabled)) {
                app('db')->connection('mysql')->table('product')
                    ->where('id', $productId)
                    ->update(['enabled' => $product->enabled]);
            }

            foreach ($product['variants'] as $key => $variant) {
                if (!is_null($variant['price']) || !is_null($variant['inventory'])) {
                    $item = [];
                    if (!is_null($product['name'])) {
                        $item['name'] = $product['name'];
                    }
                    if (!is_null($product['description'])) {
                        $item['description'] = $product['description'];
                    }
                    if (!is_null($product['enabled'])) {
                        $item['enabled'] = $product['enabled'];
                    }
                    if (!is_null($variant['price'])) {
                        $item['price'] = $variant['price'];
                    }
                    if (!is_null($variant['inventory'])) {
                        $item['quantity'] = $variant['inventory'];
                    }
                    if (!is_null($variant['color'])) {
                        $item['color'] = $variant['color'];
                    }
                    if (!is_null($variant['size'])) {
                        $item['size'] = $variant['size'];
                    }
                    array_push($result, $this->updateOzonProduct($item, $productId, $variant->mallVariantId));
                }
            }
        }
        
        return $result;
    }

    public function updateProductLight($product, $productId)
    {
        $result = [];
        if (isset($product['variants']) && count($product['variants']) > 0) {
            foreach ($product['variants'] as $key => $variant) {
                if (!is_null($variant['price']) || !is_null($variant['inventory'])) {
                    $item = [];
                    if (!is_null($variant['price'])) {
                        $item['price'] = $variant['price'];
                    }
                    if (!is_null($variant['inventory'])) {
                        $item['quantity'] = $variant['inventory'];
                    }
                    if (!is_null($product['enabled'])) {
                        $item['enabled'] = $product['enabled'];
                    }
                    array_push($result, $this->updateOzonProduct($item, $productId, $variant['mallVariantId']));
                }
            }
        }
        
        return $result;

    }

    protected function updateOzonProduct($product, $productId, $mallVariantId)
    {
        $ozonProductResult = $this->getProductInfo($productId, $mallVariantId);
        if (isset($ozonProductResult['result'])) {
            $interactionId = mt_srand();
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
                foreach ($product['images'] as $key => $image) {
                    array_push($imagesResult, $image);
                }
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
                Log::info($interactionId . ' => Update product request to ozon:', json_encode($request));
                $response = $this->sendData($this->updateProductUrl, $request);
                Log::info($interactionId . ' => Update product response: ' . $response);
                $result = json_decode($response, true);
            }

            if (isset($product['quantity'])) {
                $quanitiyResult = $this->setQuantity([
                    'product_id' => $ozonProductResult['result']['id'],
                    'stock' => intval($product['quantity'])
                ]);
                $updateNeeded = true;
            }

            if (isset($product['enabled']) && $product['enabled']) {
                $response = $this->activateProduct($ozonProductResult['result']['id']);
            }
            else if (isset($product['enabled']) && !$product['enabled']){
                $response = $this->deactivateProduct($ozonProductResult['result']['id']);
            }

            if (!is_null($product['price'])) {
                $priceResult = $this->setPrices([
                    'product_id' => $ozonProductResult['result']['id'],
                    'price' => strval($product['price']),
                    'old_price' => strval($product['price']),
                    'vat' => "0"
                ]);
                $updateFields['price'] = $product['price'];
                $updateNeeded = true;
            }

            if ($updateNeeded) {
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

    protected function setPrices($items)
    {
        $interactionId = mt_srand();
        Log::info($interactionId . ' => Update prices request to ozon:' . json_encode(['prices' => [$items]]));
        $response = $this->sendData($this->updatePricesUrl, ['prices' => [$items]]);
        Log::info($interactionId . ' => Update prices response: ' . $response);
        $result = json_decode($response, true);
    }

    public function deleteProduct($productId)
    {
        $result = [];
        $products = app('db')->connection('mysql')->table('product_variant')
            ->where('product_id', $productId)
            ->where('deleted', 0)
            ->get();
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

    public function addMainImage($productId, $imageUrl)
    {
        $productVariants = $this->getProductVariants($productId);
        if (!is_null($productVariants) && count($productVariants) > 0) {
            $newImage = [
                'is_default' => true,
                'imageUrl' => $imageUrl,
                'deleted' => 0
            ];

            $firstProductVariantId = $productVariants[0]->id;
            $imagesResult = $this->compareImages(
                [$newImage],
                $firstProductVariantId
            );

            $relations = [];
            foreach ($productVariants as $key => $variant) {
                if ($variant->id != $firstProductVariantId) {
                    array_push($relations, [
                        'image_id' => $imagesResult['imageIds'][0],
                        'product_variant_id' => $variant->id
                    ]);
                }
                if (!is_null($variant->ozonProductId)) {
                    $updateResult = $this->updateOzonProduct(['images' => $imagesResult['images']], $productId, $variant->mallVariantId);
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
        $result = [];
        $productVariants = $this->getProductVariants($productId);
        if (!is_null($productVariants)) {
            foreach ($productVariants as $key => $variant) {
                $imagesResult = $this->compareImages(
                    [
                        'is_default' => true,
                        'imageUrl' => $image,
                        'deleted' => 0
                    ],
                    $variant->id
                );
                $result = $this->updateOzonProduct(['images' => $imagesResult['images']],$productId, $variant->mallVariantId);
                array_push($result, $imagesResult['imageIds']);
            }
        }
        return $result;
    }

    protected function getProductVariants($productId)
    {
        return app('db')->connection('mysql')->table('product_variant')
            ->where('product_id', $productId)
            ->where('deleted', 0)
            ->select('ozon_product_id as ozonProductId', 'mall_variant_id as mallVariantId', 'id')
            ->get();
    }

    public function addGalleryImageForCombination($productId, $mallVariantId, $image)
    {
        $result = [];
        $productVariants = app('db')->connection('mysql')->table('product_variant')
            ->where('product_id', $productId)
            ->where('mall_variant_id', $mallVariantId)
            ->where('deleted', 0)
            ->select('ozon_product_id as ozonProductId', 'mall_variant_id as mallVariantId', 'id')
            ->get();
        if (!is_null($productVariants) && count($productVariants) > 0) {
            $imagesResult = $this->compareImages(
                [
                    'is_default' => true,
                    'imageUrl' => $image,
                    'deleted' => 0
                ],
                $variant[0]->id
            );
            $updateResult = $this->updateOzonProduct(['images' => $imagesResult['images']], $productId, $mallVariantId);
            array_push($result, $imagesResult['imageIds']);
        }
        return $result;
    }

    public function deleteGalleryImage($imageId, $productId)
    {
        $result = [];
        $productVariants = $this->getProductVariants($productId);
        if (!is_null($productVariants)) {
            foreach ($productVariants as $key => $variant) {
                $ozonProductInfo = $this->getProductInfo($productId, $variant->mallVariantId);
                $imageResult = $this->getImages($ozonProductInfo->result->images, $variant->id);
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
        }

        return $result;
    }

    protected function compareImages($newImages, $productVariantId)
    {
        $resultImages = $newImages;
        $insertedImages = array();

        $imageResult = $this->getProductVariantImages($productVariantId);
        if (count($newImages) > 0) {
            $newMainImageExists = false;
            foreach ($newImages as $key => $newImage) {
                if ($newImage['is_default']) {
                    $newMainImageExists = true;
                }

                $imageExists = false;
                foreach ($imageResult as $key => $image) {
                    if($image->imageUrl == $newImage['imageUrl']) {
                        $imageExists = true;
                    }
                }

                if (!$imageExists) {
                    $newImageId = $this->saveImageWithRelation($productVariantId, $newImage);
                    array_push($insertedImages, $newImageId);
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
    protected function getProductListFromOzon()
    {
        $data = [
            'filter' => ['visibility' => 'ALL']
        ];
        $interactionId = mt_srand();
        Log::info($interactionId . ' => Get products from ozon:', $data);
        $response = $this->sendData($this->productListUrl, $data);
        Log::info($interactionId . ' => Ozon products info: ' . $response);
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
        if (true) {
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

            Log::info('******' . json_encode($ozonProductList));
            Log::info('******' . json_encode($ozonProductList['result']));
            Log::info('******' . json_encode($ozonProductList['result']['items']));

            foreach ($ozonProductList['result']['items'] as $key => $ozonProductId) {
                // if (strval($ozonProductId) != strval(1225867)
                //     && strval($ozonProductId) != strval(1225868)
                //     && strval($ozonProductId) != strval(1225870)) {
                //     continue;
                // }
                $productExists = false;
                foreach ($result as $key => $productVariant) {
                    if ($ozonProductId['product_id'] == $productVariant->ozonProductId) {
                        $productExists = true;
                    }
                }

                if (!$productExists) {
                    $response = $this->getProductFromOzon($ozonProductId['product_id']);
                    $ozonProductInfo = json_decode($response, true);

                    if (isset($ozonProductInfo['result'])) {
                        $productVariant = app('db')->connection('mysql')->table('product_variant')
                            ->where('ozon_product_id', $ozonProductInfo['result']['id'])
                            ->first();
                        if (!$productVariant) {
                            $mallItemId = $this->getMallItemId($ozonProductInfo['result']['offer_id']);
                            if (!is_null($mallItemId)) {
                                $productId = null;
                                $product = app('db')->connection('mysql')->table('product')
                                    ->where('sku', $mallItemId)
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
    public function getOrderList()
    {
        $to = new DateTime('now');
        $since = new DateTime('now');
        $since->modify('-10 day');
        $data = [
            'since' => $since->format('Y-m-d') . 'T' . $since->format('H:i:s') .'.000Z',
            'to' => $to->format('Y-m-d') . 'T' . $to->format('H:i:s') .'.999Z',
            //'since' => $since->format('Y-m-d\TH:i:s.u'),
            //'to' => $to->format('Y-m-d\TH:i:s.u'),
            'delivery_schema' => 'crossborder'
        ];

        $interactionId = mt_srand();
        Log::info($interactionId . ' => Get orders from ozon:', $data);
        $response = $this->sendData($this->orderListUrl, $data);
        Log::info($interactionId . ' => Ozon order info: ' . $response);
        $result = json_decode($response, true);

        $notifyingOrders = [];
        $notifyingOrderIds = [];
        $ozonOrders = $result['result']['orders'];
        $existedOrders = [];
        if (count($result['result']['order_ids']) > 0) {
            $existedOrders = app('db')->connection('mysql')
                ->select('select id, ozon_order_id as ozonOrderId, status from orders where ozon_order_id IN (' . implode(',', $result['result']['order_ids']) . ')');
        }


        foreach ($ozonOrders as $key => $ozonOrder) {
            $orderExists = false;

            foreach ($existedOrders as $k => $existedOrder) {
                if ($ozonOrder['order_id'] == $existedOrder->ozonOrderId) {
                    $orderExists = true;
                    if ($ozonOrder['status'] != $existedOrder->status) {

                        array_push($notifyingOrderIds, $existedOrder->id);
                        array_push($notifyingOrders, [
                            'type' => 'update',
                            'notified' => 0,
                            'data' => json_encode([
                                'oldFulfillmentStatus' => $this->mapOrderStatus($existedOrder->status),
                                'newFulfillmentStatus' => $this->mapOrderStatus($ozonOrder['status'])
                            ]),
                            'order_id' => $existedOrder->id
                        ]);

                        app('db')->connection('mysql')->table('orders')
                            ->where('id', $existedOrder->id)
                            ->update(['status' => $ozonOrder['status']]);
                    }
                }
            }

            if (!$orderExists) {
                $pdo = app('db')->connection('mysql')->getPdo();
                $orderResult = app('db')->connection('mysql')->table('orders')
                    ->insert([
                        'ozon_order_id' => $ozonOrder['order_id'],
                        'create_date' => date('Y-m-d\TH:i:s.u'),
                        'status' => $ozonOrder['status'],
                        'deleted' => 0
                    ]);
                if ($orderResult) {
                    $orderId = $pdo->lastInsertId();
                }
                array_push($notifyingOrderIds, $orderId);
                array_push($notifyingOrders, [
                    'data' => null,
                    'type' => 'create',
                    'notified' => 0,
                    'order_id' => $orderId
                ]);
            }
        }

        foreach ($existedOrders as $key => $existedOrder) {
            if (!in_array($existedOrder->ozonOrderId, $result['result']['order_ids'])) {
                app('db')->connection('mysql')->table('orders')
                    ->where('id', $existedOrder->id)
                    ->update(['deleted' => 1]);

                array_push($notifyingOrderIds, $existedOrder->id);
                array_push($notifyingOrders, [
                    'data' => null,
                    'type' => 'delete',
                    'notified' => 0,
                    'order_id' => $existedOrder->id
                ]);
            }
        }

        app('db')->connection('mysql')->table('order_notification')
            ->insert($notifyingOrders);

        return $notifyingOrderIds;
    }

    public function getOrderInfo($orderId)
    {
        $interactionId = mt_srand();

        $orderResult = app('db')->connection('mysql')->table('orders')
            ->where('deleted', 0)
            ->where('id', $orderId)
            ->first();
        if (!$orderResult)  {
            Log::error('Order with Id ' . $orderId . 'doesn\'t exists!');
            return [
                'Error' => 'Order with Id ' . $orderId . 'doesn\'t exists!'
            ];
        } 

        Log::info($interactionId . ' => Get order info from ozon: ' . $orderResult->ozon_order_id);
        $response = $this->sendData(str_replace('{orderId}', $orderResult->ozon_order_id, $this->orderInfoUrl), null);
        Log::info($interactionId . ' => Ozon order info: ' . $response);
        $order = json_decode($response, true);

        if (isset($order['result'])) {
            $order['result']['createDate'] = $orderResult->create_date;
            $fullItems = array();
            foreach ($order['result']['items'] as $key => $item) {
                $product = app('db')->connection('mysql')
                    ->select('select pv.product_id as productId, p.description as description, pv.mall_variant_id as mallVariantId from product_variant pv
                        left join product p on p.id = pv.product_id
                        where ozon_product_id = ' . $item['product_id']);
                $productResponse = $this->getProductFromOzon($item['product_id']);
                $ozonProduct = json_decode($productResponse, true);

                $productName = '';
                $productImage = '';
                if (!is_null($ozonProduct['result'])) {
                    $productName = $ozonProduct['result']['name'];
                    $productImage = $ozonProduct['result']['images'][0];
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

            $order['result']['items'] = $fullItems;
            return $this->mapOrder($order['result']);
        }
        else {
            return null;
        }
    }

    protected function mapOrder($order)
    {
        $status = $this->mapOrderStatus($order['status']);
        $response = [
            'order_id' => $order['order_id'],
            'paymentStatus' => 'PAID',
            'fulfillmentStatus' => $status,
            'email' => $order['address']['email'],
            //'ipAddress': {ipAddress}, ??
            'createDate' => $order['createDate'],
            'refererUrl' => 'http://ozon.ru/',
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

    public function setOrderStatus($orderId, $status, $trackingNumber)
    {
        $order = $this->getOrderInfo($orderId);
        if (!is_null($order)) {
            $items = [];
            $itemsFull = [];
            $shippingProviderId = null;
            foreach ($order['items'] as $key => $item) {
                array_push($items, $item['item_id']);
                array_push($itemsFull, [
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity']
                ]);
                $shippingProviderId = $item['shippingProviderId'];
            }

            if(strtoupper($status) == strtoupper(config('app.order_approve_status')))
            {
                $interactionId = mt_srand();
                Log::info($interactionId . ' => Approve ozon order:' . strval($order['order_id']));
                $response = $this->sendData($this->approveOrderUrl, [
                    'order_id' => $order['order_id'],
                    'item_ids' => $items
                ]);
                Log::info($interactionId . ' => Approve ozon order result: ' . json_encode($response));
            }
            if(strtoupper($status) == strtoupper(config('app.order_cancel_status')))
            {
                $interactionId = mt_srand();
                Log::info($interactionId . ' =>Cancel ozon order:' . strval($order['order_id']));
                $response = $this->sendData($this->cancelOrderUrl, [
                    'order_id' => $order['order_id'],
                    'reason_code' => config('app.order_cancel_reason'),
                    'item_ids' => $items
                ]);
                Log::info($interactionId . ' => Cancel ozon order result: ' . json_encode($response));
            }
            if (strtoupper($status) == strtoupper(config('app.order_ship_status'))) {
                $interactionId = mt_srand();
                Log::info($interactionId . ' =>Ship ozon order:' . strval($order['order_id']));
                $response = $this->sendData($this->shipOrderUrl, [
                    'order_id' => $order['order_id'],
                    "shipping_provider_id" =>  config('app.russianpost_shipping_provider'),
                    "tracking_number" => $trackingNumber,
                    'items' => $itemsFull
                ]);
                Log::info($interactionId . ' => Ship ozon order result: ' . json_encode($response));
            }

            return [
                'order_id' => $orderId,
                'fulfillmentStatus' => $status
            ];
        }

        return $order;
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
                    $categoryResult = true;
                }
                if ($categoryResult) {
                    $response = $this->sendData(str_replace('{category_id}', $category['category_id'], $this->categoryAttributeListUrl), null);
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


    ////Common
    protected function sendData($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            'Client-Id: ' . config('app.ozon_api_client_id'),
            'Api-Key: ' . config('app.ozon_api_key')
        );
        if (!is_null($data)) {
            $data_string = json_encode($data);
            $headers = array_merge($headers, ['Content-Type: application/json', 'Content-Length: ' . strlen($data_string)]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        Log::info('Url: ' . $this->baseUrl . $url);
        Log::info('Headers: ' . json_encode($headers));
        Log::info('Data: ' . json_encode($data));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}