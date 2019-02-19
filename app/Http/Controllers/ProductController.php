<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\OzonService;
use App\Entities\Product;

class ProductController extends BaseController
{
    //public function getProductList()
    public function getProductList(OzonService $ozonService, Request $request)
    {
        //$test = app(AuthServiceProvider:class);
        // $res = $ozonProvider->getProductList();
        //$c = new OzonService();
        Log::info('Get product list:'. $request->input('productId'));
        $res = $ozonService->getProductList($request->input('productId'));
        return response()->json($res['result']);
    }

    public function createProduct(OzonService $ozonService, Request $request)
    {
        // $barcode = $request->input('barcode');
        // $description = $request->input('description');
        // $category_id = $request->input('category_id');
        // $name = $request->input('name');
        // $offer_id = $request->input('offer_id');
        // $price = $request->input('price');
        // $old_price = $request->input('old_price');
        // $vat = $request->input('vat');
        // $vendor = $request->input('vendor');
        // $vendor_code = $request->input('vendor_code');
        // $height = $request->input('height');
        // $depth = $request->input('depth');
        // $width = $request->input('width');
        // $dimension_unit = $request->input('dimension_unit');
        // $weight = $request->input('weight');
        // $weight_unit = $request->input('weight_unit');
        $product = json_decode($request->getContent());
        Log::info('Create product:'. json_encode($request->getContent()));
        $result = $ozonService->createProduct($product);
        return response()->json($result);
    }
}
