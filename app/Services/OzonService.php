<?php

namespace App\Services;
use Log;
use App\Services\DropshippService;

class OzonService
{
    protected $baseUrl;

    protected $productInfoUrl;
    protected $createProductsUrl;
    protected $updateStocksUrl;
    protected $updateProductUrl;
    protected $activateProductUrl;
    protected $deactivateProductUrl;
    protected $updatePricesUrl;

    protected $orderListUrl;
    protected $orderInfoUrl;
    protected $approveOrderUrl;
    protected $cancelOrderUrl;

    protected $categoryListUrl;
    protected $categoryAttributeListUrl;

    public function __construct() {
        $this->baseUrl = config('app.ozon_base_url');

        $this->productInfoUrl = config('app.ozon_productinfo_url');
        $this->createProductsUrl = config('app.ozon_createrpoduct_url');
        $this->updateStocksUrl = config('app.ozon_update_stocks_url');
        $this->updateProductUrl = config('app.ozon_updateproduct_url');
        $this->activateProductUrl = config('app.activate_product_url');
        $this->deactivateProductUrl = config('app.deactivate_product_url');
        $this->updatePricesUrl = config('app.update_productprices_url');
    
        $this->orderListUrl = config('app.ozon_orderlist_url');
        $this->orderInfoUrl = config('app.ozon_orderinfo_url');
        $this->approveOrderUrl = config('app.ozon_approveorder_url');
        $this->cancelOrderUrl = config('app.ozon_cancelorder_url');
    
        $this->categoryListUrl = config('app.ozon_categorylist_url');
        $this->categoryAttributeListUrl = config('app.ozon_categoryattributelist_url');
    }
    
    public function getProductFullInfo($productId)
    {
        $products = app('db')->connection('mysql')->
            select('
                select pv.ozon_product_id as ozonProductId, pv.mall_variant_id as mallVariantId, p.name, p.description, i.image_url as imageUrl, pv.color, pv.size
                    from product_variant pv
                    inner join product p on p.id = pv.product_id
                    left join image i on (i.product_variant_id = pv.id and i.default = 1)
                    where pv.deleted = 0
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

                $name = $response->result->name;
                $imageResult = $this->getImages($response->result->images);
                foreach ($imageResult as $key => $image) {
                    if ($image->default) {
                        $mainImage = $image->file_name;
                    }
                    else {
                        array_push($galleryImages, [
                            'id' => $image->id,
                            'imageUrl' => $image->file_name
                        ]);
                    }
                }

                $description = $product->description;
                array_push($variants, [
                    'mallVariantId' => $product->mallVariantId,
                    'inventory' => $response->result->stock,
                    'color' => $product->color,
                    'size' => $product->size,
                    'price' => $response->result->price,
                ]);
            }
        }
        else return [
            'Error' => 'Product with id=' . $productId . ' doesn\'t exists!'
        ];

        return [
            'name' => $name,
            'imageUrl' => $mainImage,
            'description' => $description,
            "galleryImages" => $galleryImages,
            'variants' => $variants
        ];
    }

    public function getProductInfo($productId, $mallVariantId)
    {
        $ozonProductResult = $this->getOzonProductId($productId, $mallVariantId);
        if ($ozonProductResult['Success']) {
            $response = $this->getProductFromOzon($ozonProductResult->ozonProductId);
            $result = json_decode($response, true);
            $result->result->productVariant = [
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
        $interactionId = mt_srand();
        Log::info($interactionId . ' => Get product info from ozon:' . $ozonProductId);
        $response = $this->sendData($this->productInfoUrl, ['product_id' => $ozonProductId]);
        Log::info($interactionId . ' => Ozon products: ' . $response);
        return $response;
    }

    public function createNewProduct($product)
    {
        $productId = 0;
        $pdo = app('db')->connection('mysql')->getPdo();
        $result = app('db')->connection('mysql')->table('product')
            ->insert([
                'name' => $product['name'],
                'sku' => $product['sku'],
                'enabled' => $product['enabled'],
                'update_date' => date('Y-m-d\TH:i:s.u')
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

        return $this->createProduct($product, $productId);
    }

    protected function createProduct($product, $productId)
    {
        $items = array();
        $innerItems = array();
        $errors = array();
        foreach ($product['variants'] as $key => $variant) {
            $item = $this->addProductToRequest(
                $variant['mallVariantId'],
                $product['sku'],
                $product['description'],
                $product['name'],
                $variant['price'],
                $variant['inventory'],
                $product['weight'],
                $product['unlimited'],
                $product['enabled'],
                $product['categoryIds'],
                $variant['color'],
                $variant['size'],
                $productId
            );

            if (!isset($item['success'])) {
                array_push($items, $item);
            }
            else {
                array_push($errors, $item);
            }
            array_push($innerItems, [
                'color' => $variant['color'],
                'size' => $variant['size'],
                'price' => $variant['price'],
                'description' => $product['description'],
                'name' => $product['name']
            ]);
        }

        $interactionId = mt_srand();
        Log::info($interactionId . ' => Create product request to ozon:' . json_encode(['items' => $items]));
        $response = $this->sendData($this->createProductsUrl, ['items' => $items]);
        Log::info($interactionId . ' => Create ozon products: ' . $response);
        $result = json_decode($response);

        $productId = null;
        $quantityItems = array();

        if (!$result->error) {
            for ($i=0; $i < count($result->result); $i++) { 
                app('db')->connection('mysql')->table('product_variant')
                    ->insert([
                        'ozon_product_id' => $result->result[i],
                        'product_id' => $items[i]->dropshippProductId,
                        'mall_variant_id' => $items[i]->mallVariantId,
                        'name' => $innerItems[i]->name,
                        'price' => $innerItems[i]->price,
                        'description' => $innerItems[i]->description,
                        'color' => $innerItems[i]->color,
                        'size' => $innerItems[i]->size,
                        'deleted' => 0
                    ]);

                array_push($quantityItems, [
                    'product_id' => $result->result[i],
                    'stock' => $items[i]->quantity
                ]);

                $productId = $items[i]->productId;
            }

            $this->setQuantity($quantityItems);
        }

        return [
            'id' => $productId,
            'errors' => $errors
        ];
    }

    protected function addProductToRequest($mallVariantId, $sku, $description, $name, $price, $weight, $quantity, $unlimited, $enabled, $categoryIds, $color, $size, $productId)
    {
        $ozonCategoryId = $this->getOzonCategory($categoryIds);
        if(!is_null($ozonCategoryId)) {
            $attributes = array([
                'id' => config('app.ozon_product_group_attribute'),
                'value' => $name . '(' . $sku . ')'
            ]);
            $colorAttribute = null;
            if (!is_null($color)) {
                $colorAttribute = $this->getAttribute($ozonCategoryId, $color);
                if (isset($colorAttribute['attributeId'])) {
                    $this->AddAttributeToRequest($attributes, $colorAttribute);
                }
            }
            $sizeAttribute = null;
            if (!is_null($size)) {
                $sizeAttribute = $this->getAttribute($ozonCategoryId, $size);
                if (isset($sizeAttribute['attributeId'])) {
                    $this->AddAttributeToRequest($attributes, $sizeAttribute);
                }
            }

            if ((is_null($color) || isset($colorAttribute['attributeId'])) && (is_null($size) || isset($sizeAttribute['attributeId']))) {
                return [
                    'dropshippProductId' => $productId,
                    'mallVariantId' => $mallVariantId,
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
                    "visibility_details" => [
                        'has_price' => true,
                        'has_stock' => $unlimited,
                        'active_product' => $enabled
                    ]
                ];
            }
            else {
                $errorAttributes = array();
                if (!is_null($color) && !isset($colorAttribute['attributeId'])) {
                    array_push($errorAttributes, 'Attribute color ' . $color . ' doesn\'t mapped correctly. Attribute map with ID=' . $colorAttribute['attributeMapId'] . ' was created.');
                }
                if (!is_null($size) && !isset($sizeAttribute['attributeId'])) {
                    array_push($errorAttributes, 'Attribute size= ' . $size . ' doesn\'t mapped correctly. Attribute map with ID=' . $sizeAttribute['attributeMapId'] . ' was created.');
                }

                return [
                    'success' => false,
                    'error' => json_encode($errorAttributes)
                ];
            }
        }
        else return [
            'success' => false,
            'error' => 'Category with Id ' . $categoryId . ' doesn\'t exists! Try to update category table manually.'
        ];
    }

    protected function AddAttributeToRequest($attributes, $attribute)
    {
        if ($attribute->isCollection) {
            array_push($attributes, [
                'id' => $colorAttribute->attributeId,
                'collection' => array($colorAttribute->attributeValueId)
            ]);
        }
        else {
            array_push($attributes, [
                'id' => $colorAttribute->attributeId,
                'value' => $colorAttribute->attributeValueId
            ]);
        }

        return $attributes;
    }

    public function getOzonCategory($categoryIds)
    {
        $ozonCategoryId = null;
        Log::info('****' . json_encode($categoryIds));
        foreach ($categoryIds as $key => $categoryId) {
            $ozonCategoryId = $this->classifyCategory($categoryId);
            if (is_null($ozonCategoryId)) {
                $result = app('db')->connection('mysql')->select('select oc.id as ozonCategoryId from ozon_category oc
                    left join category c on c.ozon_category_id = oc.id where c.id = \'' . $categoryId . '\' limit 1');
                if (!is_null($result) && isset($result['ozonCategoryId'])) {
                    $ozonCategoryId = $result->ozonCategoryId;
                }
                else {
                    app('db')->connection('mysql')->table('category')
                        ->insert(['id' => $categoryId]);
                }
            }
        }
        if (is_null($ozonCategoryId)) {
            return config('app.active_category_id');
        }
        else return $ozonCategoryId;
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
                Log::error('Attribute with value ' . $value . 'doesn\'t exists! Attribute map with id=' . $attributeMapId . ' was created.');
                return [
                    'attributeMapId' => $attributeMapId
                ];
            }
        }  
        return [
            'attributeId' => $result->attributeId,
            'attributeValueId' => $result->attributeValueId
        ];
    }

    protected function setQuantity($items)
    {
        $interactionId = mt_srand();
        Log::info($interactionId . ' => Update stocks request to ozon:', json_encode(['stocks' => $items]));
        $response = $this->sendData($this->updateStocksUrl, ['stocks' => $items]);
        Log::info($interactionId . ' => Update stocks response: ' . $response);
        $result = json_decode($response, true);
    }

    public function createProductCombination($productId, $combinations)
    {
        $product = app('db')->connection('mysql')->table('product')
            ->where('id', $productId)->first();
        if (!is_null($product)) {
            $result = $this->createProduct([
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'variannts' => $combinations
            ]);
            return $result;
        }
        else return array();
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
        if (!is_null($product->variants)) {

            if(!is_null($product->enabled)) {
                app('db')->connection('mysql')->table('product')
                    ->where('id', $productId)
                    ->update(['enabled' => $poduct->enabled]);
            }

            foreach ($product->variants as $key => $variant) {
                if (!is_null($variant->price) && !is_null($variant->inventory)) {
                    $item = [];
                    if (!is_null($product->name)) {
                        $item->name = $product->name;
                    }
                    if (!is_null($product->description)) {
                        $item->description = $product->description;
                    }
                    if (!is_null($product->enabled)) {
                        $item->enabled = $product->enabled;
                    }
                    if (!is_null($variant->price)) {
                        $item->price = $variant->price;
                    }
                    if (!is_null($variant->inventory)) {
                        $item->inventory = $variant->inventory;
                    }
                    if (!is_null($variant->color)) {
                        $item->color = $variant->color;
                    }
                    if (!is_null($variant->size)) {
                        $item->size = $variant->size;
                    }
                    $this->updateOzonProduct($item, $productId, $variant->mallVariantId);
                }
            }
        }
        
        return $result;
    }

    protected function updateOzonProduct($product, $productId, $mallVariantId)
    {
        $ozonProductResult = $this->getProductInfo($productId, $mallVariantId);
        if (!is_null($ozonProductResult->result)) {
            $interactionId = mt_srand();
            $updateFields = array();
            if (!is_null($product->color)) {
                $updateFields['color'] = $product->color;
            }
            if (!is_null($product->size)) {
                $updateFields['size'] = $product->size;
            }
            $request = ['product_id' => $ozonProductResult->result->Id];
            $updateNeeded = false;
            if (!is_null($product->name)) {
                $request->name = $product->name;
                $updateFields['name'] = $product->name;
                $updateNeeded = true;
            }
            if (!is_null($product->description)) {
                $request->description = $product->description;
                $updateFields['description'] = $product->description;
                $updateNeeded = true;
            }
            if(!is_null($product->images)) {
                $imagesResult = $this->compareImages(
                    $ozonProductResult->result->images,
                    $product->images,
                    $ozonProductResult->result->productVariant->id
                );

                foreach ($imageResult as $key => $image) {
                    if($image->default) {
                        $updateFields['image_url'] = $image->file_name;
                    }
                }

                if (count($imagesResult->images) > 0) {
                    $request->images = $imagesResult->images;
                    $updateNeeded = true;
                }
            }

            $attributes = array();
            if (!is_null($product->color)) {
                $colorAttribute = null;
                if (!is_null($color)) {
                    $colorAttribute = $this->getAttribute($ozonCategoryId, $color);
                    if (!is_null($colorAttribute->attributeId)) {
                        $this->AddAttributeToRequest($attributes, $colorAttribute);
                    }
                }
            }
            if (!is_null($product->size)) {
                $sizeAttribute = null;
                if (!is_null($size)) {
                    $sizeAttribute = $this->getAttribute($ozonCategoryId, $size);
                    if (!is_null($sizeAttribute->attributeId)) {
                        $this->AddAttributeToRequest($attributes, $sizeAttribute);
                    }
                }
            }
            if (count($attributes) > 0) {
                $request->attributes = $attributes;
                $updateNeeded = true;
            }

            if ($updateNeeded) {
                Log::info($interactionId . ' => Update product request to ozon:', json_encode($request));
                $response = $this->sendData($this->updateProductUrl, $request);
                Log::info($interactionId . ' => Update product response: ' . $response);
                $result = json_decode($response, true);
            }

            if (!is_null($product->quantity)) {
                $quanitiyResult = $this->setQuantity([
                    'product_id' => $ozonProductResult->result->Id,
                    'stock' => $product->quantity
                ]);
                $updateNeeded = true;
            }

            if (!is_null($product->enabled) && $product->enabled) {
                $response = $this->activateProduct($ozonProductResult->result->Id);
            }
            else if (!is_null($product->enabled) && !$product->enabled){
                $response = $this->deactivateProduct($ozonProductResult->result->Id);
            }

            if (!is_null($product->price)) {
                $priceResult = $this->setPrices([
                    'product_id' => $ozonProductResult->result->Id,
                    'price' => $product->price,
                    'old_price' => $ozonProductResult->result->old_price,
                    'vat' => $ozonProductResult->result->vat
                ]);
                $updateFields['price'] = $product->price;
                $updateNeeded = true;
            }

            if ($updateNeeded) {
                app('db')->connection('mysql')->table('product_variant')
                    ->where('deleted', 0)
                    ->where('product_id', $productId)
                    ->where('mall_variant_id', $mallVariantId)
                    ->update($updateFields);
            }

            return ['productId' => $productId, 'imageIds' => $imagesResult->imageIds];
        }
        else return $ozonProductResult;
    }

    protected function setPrices($items)
    {
        $interactionId = mt_srand();
        Log::info($interactionId . ' => Update prices request to ozon:', json_encode(['prices' => $items]));
        $response = $this->sendData($this->updatePricesUrl, ['prices' => $items]);
        Log::info($interactionId . ' => Update prices response: ' . $response);
        $result = json_decode($response, true);
    }

    public function deleteProduct($productId)
    {
        $products = app('db')->connection('mysql')
            ->select('select * from product_variant where deleted = 0 and product_id = ' . $productId);
        foreach ($products as $key => $product) {
            $this->deactivateProduct($product->getOzonProductId);
        }
        app('db')->connection('mysql')->table('product_variant')
            ->where('product_id', $productId)->update(['deleted' => 1]);        
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
        $productVariants = app('db')->connection('mysql')
            ->select('select ozon_product_id as ozonProductId, mall_variant_id as mallVariantId from product_variant where deleted = 0 and product_id=' . $productId);
        if (!is_null($productVariants)) {
            foreach ($productVariants as $key => $variant) {
                $result = $this->updateOzonProduct(['image' => array([
                    'file_name' => $image->externalUrl,
                    'default' => true
                ])], $productId, $variant->mallVariantId);
            }
        }
        return $result->imageIds;
    }

    public function addGalleryImage($image, $productId)
    {
        $productVariants = app('db')->connection('mysql')
            ->select('select ozon_product_id as ozonProductId, mall_variant_id as mallVariantId from product_variant where deleted = 0 and product_id=' . $productId);
        if (!is_null($productVariants)) {
            foreach ($productVariants as $key => $variant) {
                $result = $this->updateOzonProduct(['image' => array([
                    'file_name' => $image->externalUrl,
                    'default' => false
                ])], $productId, $variant->mallVariantId);
            }
        }
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
        $productVariants = app('db')->connection('mysql')
            ->select('select ozon_product_id as ozonProductId, mall_variant_id as mallVariantId from product_variant where deleted = 0 and product_id =' . $productId);
        if (!is_null($productVariants)) {
            foreach ($productVariants as $key => $variant) {
                $ozonProductInfo = $this->getProductInfo($productId, $variant->mallVariantId);
                $imageResult = $this->getImages($ozonProductInfo->result->images);
                $images = array();
                $result = array();
                foreach ($imageResult as $key => $image) {
                    if ($image->id == $imageId && count($imageResult) == 1) {
                        array_push($result, [
                            'Error' => 'Can\'t delete last image'
                        ]);
                    }
                    else if($image->id == $imageId && count($imageResult) > 1) {
                        if ($image->default) {
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
                            'file_name' => $image->file_name,
                            'default' => $image->default
                        ]);
                    }
                }
                $this->updateOzonProduct(['images' => $images], $productId, $variant->mallVariantId);
            }
        }

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
                    if($mage->file_name == $newImage->file_name) {
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
            ->select('select * from image where deleted = 0 and image_url IN (' . implode('\',\'', $imageFilenames) . ')');
        if ($imageResult) {
            return $imageResult;
        }
        else return array();
    }

    protected function saveImage($productVariantId, $image)
    {
        $pdo = app('db')->connection('mysql')->getPdo();
        $result = app('db')->connection('mysql')->table('image')
            ->insert([
                'image_url' => $image->file_name,
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



    ////ScheduledProductUpdate
    public function syncProducts($dropshippService)
    {
        $updatedProducts = array();
        $result = app('db')->connaction('mysql')->select('
            select 
                pv.id as id,
                pv.product_id as productId,
                pv.mall_variant_id as mallVariantId,
                pv.ozon_product_id as ozonProductId,
                p.name as name,
                p.description as description,
                pv.price as price,
                i.image_url as imageUrl
            from product p
            inner join product_variant pv on p.id = pv.product_id
            left join image i on (i.product_variant_id = pv.id and i.default = 1)
            where product_id in (select top ' . config(app.sync_portion) . ' id from product order by p.update_date asc)
        ');

            //Цена, наименование товара, описание, картинка
        if ($result) {
            foreach ($result as $key => $productVariant) {
                if (!in_array($productVariant->productId, $updatedProducts)) {
                    array_push($updatedProducts, $productVariant->productId);
                    $productInfo = $this->getProductFullInfo($productVariant->productId);

                    $updateFields = ['update_date' => date('Y-m-d\TH:i:s.u')];
                    foreach ($productInfo->variants as $key => $variant) {
                        if (
                            $variant->price != $productVariant->price
                            || $productInfo->name != $productVariant->name
                            || $productInfo->imageUrl != $productVariant->imageUrl) {
                                $updateFields->price = $variant->price;
                                $updateFields->name = $productInfo->name;
                                if ($productInfo->imageUrl != $productVariant->imageUrl) {
                                    app('db')->connection('mysql')->table('image')
                                    ->where('deleted', 0)
                                    ->where('product_variantId', $productVariant->id)
                                    ->update(['image_url' => $productInfo->imageUrl]);
                                }
                        }

                        app('db')->connection('mysql')->table('product_variant')
                            ->where('id', $productVariant->id)
                            ->update($updateFields);
                    }
                }
            }
        }
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

        $interactionId = mt_srand();
        Log::info($interactionId . ' => Get orders from ozon:', $data);
        $response = $this->sendData($this->orderListUrl, $data);
        Log::info($interactionId . ' => Ozon order info: ' . $response);
        $result = json_decode($response, true);

        $notifyOrders = ['newOrders' => array(), 'existedOrders' => array(), 'deletedOrders' => array()];
        $ozonOrders = $result->orders;
        $existedOrders = app('db')->connection('mysql')
            ->select('select id, status from order where deleted = 0 and ozon_order_id IN (' . implode(',', $result->order_ids) . ')');
        foreach ($ozonOrders as $key => $ozonOrder) {
            $orderExists = false;

            foreach ($existedOrders as $k => $existedOrder) {
                if ($ozonOrder->order_id == $existedOrder->id && $ozonOrder->status != $existedOrder->status) {
                    $orderExists = true;
                    array_push($notifyOrders->existedOrders, [
                        'id' => $existedOrder->id,
                        'oldStatus' => $this->mapOrderStatus($existedOrder->status),
                        'newStatus' => $this->mapOrderStatus($ozonOrder->status)
                    ]);
                    app('db')->connection('mysql')->table('order')
                        ->where('id', $existedOrder->id)
                        ->update(['status' => $ozonOrder->status]);
                }
            }

            if (!$orderExists) {
                array_push($notifyOrders->newOrders, $ozonOrder);
                app('db')->connection('mysql')->table('order')
                    ->insert([
                        'ozon_order_id' => $ozonOrder->order_id,
                        'create_date' => date('Y-m-d\TH:i:s.u'),
                        'status' => $ozonOrder->status,
                        'deleted' => 0
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
        $interactionId = mt_srand();

        $orderResult = app('db')->connection('mysql')->table('order')
            ->where('deleted', 0)
            ->where('id', $orderId)->first();
        if (!$orderResult)  {
            Log::error('Order with Id ' . $orderId . 'doesn\'t exists!');
            return [
                'Error' => 'Product with Id ' . $orderId . 'doesn\'t exists!'
            ];
        } 

        Log::info($interactionId . ' => Get order info from ozon: ' . $orderResult->ozon_order_id);
        $response = $this->sendData(str_replace('{orderId}', $orderResult->ozon_order_id, $this->orderInfoUrl));
        Log::info($interactionId . ' => Ozon order info: ' . $response);
        $order = json_decode($response, true);

        if (!is_null($order->result)) {
            $order->result->createDate = $orderResult->create_date;
            $fullItems = array();
            foreach ($order->result->items as $key => $item) {
                $product = app('db')->connection('mysql')->table('product_variant')
                    ->where('deleted', 0)    
                    ->where('ozon_product_id', $item->product_id)->first();
                $productResponse = $this->getProductFromOzon($item->product_id);
                $product = json_decode($productResponse, true);
                $productName = '';
                $productImage = '';
                if (!is_null($product->result)) {
                    $productName = $product->result->name;
                    $productImage = $product->result->images[0];
                }

                array_push($fullItems, [
                    'product_id' => $product->productid,
                    'item_id' => $item->item_id,
                    'quantity' => $item->quantity,
                    'offer_id' => $item->offer_id,
                    'price' => $item->price,
                    'tracking_number' => $item->tracking_number,
                    'status' => $item->status,
                    'cancel_reason_id' => $item->cancel_reason_id,
                    'auto_cancel_date' => $item->auto_cancel_date,
                    'shipping_provider_id' => $item->shipping_provider_id,
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
            'paymentStatus' => 'PAID',
            'fulfillmentStatus' => $status,
            'email' => $order->address->email,
            //'ipAddress': {ipAddress}, ??
            'createDate' => $order->createDate,
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
                'quantity' => $item->quantity,
                'name' => $item->name,
                'imageUrl' => $item->imageUrl,
                'smallThumbnailUrl' => $item->smallThumbnailUrl,
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
                array_push($items, $item->item_id);
            }

            if($status == config('app.order_approve_status'))
            {
                $interactionId = mt_srand();
                Log::info($interactionId . ' => Approve ozon order:' . $ozonProductId);
                $response = $this->sendData($this->approveOrderUrl, [
                    'order_id' => $order->result->order_id,
                    'item_ids' => $items
                ]);
                Log::info($interactionId . ' => Approve ozon order result: ' . $response);
            }
            if($status == config('app.order_cancel_status'))
            {
                $interactionId = mt_srand();
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


    //Tech insert categories
    public function insertCategories()
    {
        $response = $this->sendData($this->categoryListUrl);
        $result = json_decode($response, true);
        $this->insertChildCategories($result->result);
    }

    protected function insertChildCategories($category)
    {
        foreach ($result->result as $key => $category) {
            if(is_null($category->children)) {
                $categoryResult = app('db')->connection('mysql') ->table('ozon_category')
                    ->insert([
                        'id' => $category->category_id,
                        'name' => $category->title
                    ]);
                if ($categoryResult) {
                    $response = $this->sendData(str_replace('{category_id}', $category->category_id, $this->categoryAttributeListUrl));
                    $result = json_decode($response, true);
                    foreach ($result->result as $key => $attribute) {
                        if($attribute->type == 'option') {
                            $attributeResult = app('db')->connection('mysql')->table('attribute')
                                ->insert([
                                    'id' => $attribute->id,
                                    'is_collection' => $attribute->is_collection,
                                    'required' => $attribute->required
                                ]);
                        }
                        if ($attributeResult)  {
                            app('db')->connection('mysql')->table('ozon_category_attribute')
                                ->insert([
                                    'attribute_id' => $attribute->id,
                                    'ozon_category_id' => $category->category_id
                                ]);
                            foreach ($attribute->option as $key => $option) {
                                app('db')->connection('mysql')->table('attribute_value')
                                    ->insert([
                                        'attribute_id' => $attribute->id,
                                        'ozon_id' => $option->id,
                                        'value' => $option->value
                                    ]);
                            }
                        }
                    }
                }
                else {
                    $this->insertChildCategories($category);
                }
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
            $data_string = json_encode(['items' => $data]);
            array_push($headers, ['Content-Type: application/json', 'Content-Length: ' . strlen($data_string)]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}