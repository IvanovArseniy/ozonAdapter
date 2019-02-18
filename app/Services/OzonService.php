<?php

namespace App\Services;

class OzonService
{
    protected $baseUrl = 'http://cb-api.test.ozon.ru';
    protected $productListUrl = '/v1/categories/tree';
    protected $createProductsUrl = '/v1/product/import';

    protected function addHeaders($ch)
    {
        curl_setopt($ch,
            CURLOPT_HTTPHEADER,
            array('Client-Id: 466', 'Api-Key: 9753260e-2324-fde7-97f1-7848ed7ed097'));
    }

    // public function getCategoryList()
    // {
    //     # code...
    // }

    public function getProductList()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->productListUrl);
        $this->addHeaders($ch);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        //Log::info('Ozon products: ' . $response);
        return $result;
    }

    // public function getProductInfo()
    // {
    //     # code...
    // }

    public function createProduct($products)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->createProductsUrl);
        //$this->addHeaders($ch);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data_string = json_encode(['items' => $products]); 
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
        //Log::info('Ozon products: ' . $response);
        return $result;
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