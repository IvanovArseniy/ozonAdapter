<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\OzonService;
use App\Services\DropshippService;

class ProductController extends BaseController
{
    public function getProductInfo(Request $request, $productId)
    {
        $ozonService = new OzonService ();
        Log::info('Get product info:'. json_encode($productId));
        $result = $ozonService->getProductFullInfo($productId);
        Log::info('Get product info response:'. json_encode($result));
        return response()->json($result);
    }

    public function createProduct(OzonService $ozonService, Request $request)
    {
        $product = json_decode($request->getContent(), true);
        Log::info('Create product:'. json_encode($request->getContent()));
        if (!is_null($product) && isset($product['name']) && isset($product['variants']) && count($product['variants']) > 0) {
            $result = $ozonService->createNewProduct($product);
            Log::info('Create product response:'. json_encode($result));
            return response()->json($result);
        }
        else {
            return response()->json(['Error' => 'Required fields are not present!']);
        }
    }

    public function createProductCombination(OzonService $ozonService, Request $request, $productId)
    {
        $combinations = json_decode($request->getContent(), true);
        Log::info('Create product combination:'. json_encode($request->getContent()));
        $result = $ozonService->createProductCombination($productId, $combinations);
        Log::info('Create product combination response:'. json_encode(['id' => $productId]));
        return response()->json($result);
    }

    public function updateProduct(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        $product = json_decode($request->getContent());
        Log::info('Update product:'. json_encode($request->getContent()));
        $result = $ozonService->updateProduct($product, $productId);
        Log::info('Update product response:'. json_encode($result));
        return response()->json($result);
    }

    public function deleteProduct(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        Log::info('Delete product:'. json_encode($request->getContent()));
        $result = $ozonService->deleteProduct($productId);
        Log::info('Delete product response:'. json_encode($result));
        return response()->json($result);
    }

    public function addMainImage(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        $image = json_decode($request->getContent());
        Log::info('Add main image:'. json_encode($request->getContent()));
        $imageIds = $ozonService->addMainImage($productId, $image);
        Log::info('Add main image response:'. json_encode($imageIds));
        return response()->json($imageIds);
    }

    public function addGalleryImage(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        $image = json_decode($request->getContent());
        Log::info('Add gallery image:'. json_encode($request->getContent()));
        $imageIds = $ozonService->addGalleryImage($productId, $image);
        Log::info('Add gallery image response:'. json_encode($imageIds));
        return response()->json($imageIds);
    }

    public function deleteGalleryImage(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        $imageId = $request->input('imageId');
        Log::info('Delete gallery image:'. $imageId);
        $result = $ozonService->deleteGalleryImage($imageId, $productId);
        Log::info('Delete gallery image response:'. json_encode($result));
        return response()->json($result);
    }

    public function addGalleryImageForCombination(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        $mallVariantId = $request->input('combinationId');
        $image = json_decode($request->getContent());
        Log::info('Add gallery image for combination:'. json_encode($request->getContent()));
        $imageIds = $ozonService->addGalleryImageForCombination($productId, $mallVariantId, $image);
        Log::info('Add gallery image for combunation response:'. json_encode($imageIds));
        return response()->json($imageIds);
    }

    public function syncProducts(OzonService $ozonService, DropshippService $dropshippService)
    {
        Log::info('Sync started!');
        $notifyingProductIds = $ozonService->syncProducts();
        $dropshippService->notifyProducts($notifyingProductIds);
        return response()->json(['OK']);
    }
}
