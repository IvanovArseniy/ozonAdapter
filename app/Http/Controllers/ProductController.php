<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\OzonService;

class ProductController extends BaseController
{
    public function getProductInfo(OzonService $ozonService, Request $request)
    {
        Log::info('Get product info:'. json_encode($request->route('productId')));
        $result = $ozonService->getProductFullInfo($request->route('productId'));
        Log::info('Get product info response:'. json_encode($result));
        return response()->json($result);
    }

    public function createProduct(OzonService $ozonService, Request $request)
    {
        $product = json_decode($request->getContent(), true);
        Log::info('Create product:'. json_encode($request->getContent()));
        if (!is_null($product) && !isset($product['name']) && isset($product['variants']) && count($product['variants']) > 0) {
            $result = $ozonService->createNewProduct($product);
            Log::info('Create product response:'. json_encode($result));
            return response()->json($result);
        }
        else {
            //return response()->json(['Error' => 'Required fields are not present!']);
            $result = $ozonService->createNewProduct($product);
            Log::info('Create product response:'. json_encode($result));
            return response()->json($result);
        }
    }

    public function createProductCombination(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        $combinations = json_decode($request->getContent());
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
        $productId = $ozonService->updateProduct($product, $productId);
        Log::info('Update product response:'. json_encode(['id' => $productId]));
        return response()->json(['id' => $productId]);
    }

    public function deleteProduct(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        Log::info('Delete product:'. json_encode($request->getContent()));
        $productId = $ozonService->deleteProduct($productId);
        Log::info('Delete product response:'. json_encode(['id' => $productId]));
        return response()->json(['id' => $productId]);
    }

    public function addMainImage(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        $image = json_decode($request->getContent());
        Log::info('Add main image:'. json_encode($request->getContent()));
        $imageIds = $ozonService->addMainImage($productId, $image);
        Log::info('Add main image response:'. json_encode(['id' => $imageIds[0]]));
        return response()->json(['id' => $imageIds[0]]);
    }

    public function addGalleryImage(OzonService $ozonService, Request $request)
    {
        $productId = $request->input('productId');
        $image = json_decode($request->getContent());
        Log::info('Add gallery image:'. json_encode($request->getContent()));
        $imageIds = $ozonService->addGalleryImage($image, $productId);
        Log::info('Add gallery image response:'. json_encode(['id' => $imageIds[0]]));
        return response()->json(['id' => $imageIds[0]]);
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
        $imageIds = $ozonService->addGalleryImageForCombination($image, $productId, $mallVariantId);
        Log::info('Add gallery image for combunation response:'. json_encode(['id' => $imageIds[0]]));
        return response()->json(['id' => $imageIds[0]]);
    }
}
