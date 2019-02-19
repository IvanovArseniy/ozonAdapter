<?php

namespace App\Services;
use Log;

class OzonService
{
    protected $baseUrl = 'http://cb-api.test.ozon.ru';
    protected $productListUrl = '/v1/product/info';
    protected $createProductsUrl = '/v1/product/import';

    protected function addHeaders($ch)
    {
        curl_setopt($ch,
            CURLOPT_HTTPHEADER,
            array('Client-Id: ' . config('app.ozon_api_client_id'), 'Api-Key: ' . config('app.ozon_api_key')));
    }

    // public function getCategoryList()
    // {
    //     # code...
    // }

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

    // public function getProductInfo()
    // {
    //     # code...
    // }

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
                //app('db')->connection('mysql')->insert('insert into product (Id, ozonProductId, ozonProductChangeDate) values(?, ?, ?)', [$variant->mallVariantId, 1, date("Y-m-d H:i:s")]);
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

    // public function ActivateProduct()
    // {
    //     # code...
    // }

    // public function DeactivateProduct()
    // {
    //     # code...
    // }

    // //Update product here

    // public function getOrderList()
    // {
    //     # code...
    // }

    // public function getOrderInfo()
    // {
    //     # code...
    // }
    
}