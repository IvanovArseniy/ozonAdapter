<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\OzonService;

class ProductController extends BaseController
{
    //public function getProductList()
    public function getProductList(OzonService $ozonService, Request $request)
    {
        Log::info('Get product list:'. $request->input('productId'));
        $res = $ozonService->getProductList($request->input('productId'));
        return response()->json($res['result']);
    }

    public function createProduct(OzonService $ozonService, Request $request)
    {
        $product = json_decode($request->getContent());
        Log::info('Create product:'. json_encode($request->getContent()));
        $productId = $ozonService->createProduct($product);
        return response()->json(['id' => $productId]);
    }

    public function createProductCombination(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        $combination = json_decode($request->getContent());
        Log::info('Create product combination:'. json_encode($request->getContent()));
        $result = $ozonService->createProductCombination($productId, $combination);
    }

    public function updateProduct(OzonService $ozonService, Request $request)
    {
        # code...
    }

    public function deleteProduct(OzonService $ozonService, Request $request)
    {
        # code...
    }

    public function addMainImage(OzonService $ozonService, Request $request)
    {
        # code...
    }

    public function addGalleryImage(OzonService $ozonService, Request $request)
    {
        # code...
    }

    public function deleteGalleryImage(OzonService $ozonService, Request $request)
    {
        # code...
    }

    public function addGalleryImageForCombination(OzonService $ozonService, Request $request)
    {
        # code...
    }
}
