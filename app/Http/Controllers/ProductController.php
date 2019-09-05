<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\OzonService;
use App\Services\DropshippService;
use App\Services\GearmanService;

class ProductController extends BaseController
{
    public function getProductInfo(OzonService $ozonService, Request $request, $productId)
    {
        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Get product info:'. json_encode($productId));
        $result = $ozonService->getProductFullInfo($productId);
        Log::info($interactionId . ' => Get product info response:'. json_encode($result));
        return response()->json($result);
    }

    public function createProduct(OzonService $ozonService, Request $request)
    {
        $interactionId = $ozonService->getInteractionId();
        $product = json_decode($request->getContent(), true);
        Log::info($interactionId . ' => Create product:'. json_encode($product));
        if (!is_null($product) && isset($product['name']) && isset($product['variants']) && count($product['variants']) > 0) {
            $result = $ozonService->createNewProduct($product);
            Log::info($interactionId . ' => Create product response:'. json_encode($result));
            return response()->json($result);
        }
        else {
            return response()->json(['Error' => 'Required fields are not present!']);
        }
    }

    public function scheduleProductsActivation(OzonService $ozonService)
    {
        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Schedule activation');
        $ozonService->scheduleActivation();
        Log::info($interactionId . ' => Send products and get IDs ready!');
    }

    public function scheduleJobs(OzonService $ozonService)
    {
        if (file_exists(storage_path() . '/app/scheduleJobs.lock')) {
            return response()->json(['error' => 'scheduleJobs job already in work']);
        }
        file_put_contents(storage_path() . '/app/scheduleJobs.lock', 'Start');

        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Send products and get IDs.');
        // $sendResult = $ozonService->scheduleProductCreation();
        // $sendResult = $ozonService->scheduleProductCreation();
        //$idsResult = $ozonService->setOzonProductId();
        Log::info($interactionId . ' => Send products result:' . json_encode($sendResult));
        //Log::info($interactionId . ' => Get ids result:' . json_encode($idsResult));
        Log::info($interactionId . ' => Send products and get IDs ready!');

        unlink(storage_path() . '/app/scheduleJobs.lock');

        return response()->json([
            'sendResult' => $sendResult,
            'idsResult' => $idsResult,
        ]);
    }

    public function scheduleProductCreation(OzonService $ozonService)
    {
        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Product creation scheduled. Start');
        //$response = $ozonService->scheduleProductCreation();
        ScheduleProductCreationJob::dispatch();

        Log::info($interactionId . ' => Product creation scheduled. Finish');
        return response()->json($response);
    }

    public function setProductExternalId(OzonService $ozonService)
    {
        // if (file_exists(storage_path() . '/app/setProductExternalId.lock')) {
        //     return response()->json(['error' => 'setProductExternalId job already in work']);
        // }
        // file_put_contents(storage_path() . '/app/setProductExternalId.lock', 'Start');

        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Set product external Ids started');
        $response = $ozonService->setOzonProductId();
        Log::info($interactionId . ' => Set product external Ids finished');

        // unlink(storage_path() . '/app/setProductExternalId.lock');

        return response()->json($response);
    }

    public function createProductCombination(OzonService $ozonService, Request $request, $productId)
    {
        $interactionId = $ozonService->getInteractionId();
        $combinations = json_decode($request->getContent(), true);
        Log::info($interactionId . ' => Create product combination:'. json_encode($request->getContent()));
        $result = $ozonService->createProductCombination($productId, $combinations);
        Log::info($interactionId . ' => Create product combination response:'. json_encode($result));
        return response()->json($result);
    }

    public function updateProduct(OzonService $ozonService, Request $request, $productId)
    {
        $interactionId = $ozonService->getInteractionId();
        $product = json_decode($request->getContent(), true);
        Log::info($interactionId . ' => Update product:'. strval($productId));
        Log::info($interactionId . ' => Update product:'. json_encode($product));
        $updateProductResult = $ozonService->updateProduct($product, $productId);
        $result = $updateProductResult['result'];
        $success = $updateProductResult['success'];
        Log::info($interactionId . ' => Update product response:'. json_encode($result));
        if ($success) {
            return response()->json($result);
        }
        else {
            return response()->json($result, 500);
        }
    }

    public function deleteProduct(OzonService $ozonService, Request $request, $productId)
    {
        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Delete product:'. strval($productId));
        $result = $ozonService->deleteProduct($productId);
        Log::info($interactionId . ' => Delete product response:'. json_encode($result));
        return response()->json($result);
    }

    public function addMainImage(OzonService $ozonService, Request $request, $productId)
    {
        $interactionId = $ozonService->getInteractionId();
        $externalUrl = $request->input('externalUrl');
        Log::info($interactionId . ' => Add main image: '. $externalUrl);
        $imageIds = $ozonService->addMainImage($productId, $externalUrl);
        Log::info($interactionId . ' => Add main image response:'. json_encode($imageIds));
        return response()->json($imageIds);
    }

    public function addGalleryImage(OzonService $ozonService, Request $request, $productId)
    {
        $interactionId = $ozonService->getInteractionId();
        $externalUrl = $request->input('externalUrl');
        Log::info($interactionId . ' => Add gallery image:'. $externalUrl);
        $imageIds = $ozonService->addGalleryImage($productId, $externalUrl);
        Log::info($interactionId . ' => Add gallery image response:'. json_encode($imageIds));
        if (count($imageIds) > 0) {
            return response()->json(['id' => $imageIds[0]]);
        }
        else {
            return response()->json([]);
        }
    }

    public function deleteGalleryImage(OzonService $ozonService, Request $request, $productId)
    {
        $interactionId = $ozonService->getInteractionId();
        $imageId = $request->input('imageId');
        Log::info($interactionId . ' => Delete gallery image:'. $imageId);
        $result = $ozonService->deleteGalleryImage($imageId, $productId);
        Log::info($interactionId . ' => Delete gallery image response:'. json_encode($result));
        return response()->json($result);
    }

    public function addGalleryImageForCombination(OzonService $ozonService, Request $request, $productId, $combinationId)
    {
        $interactionId = $ozonService->getInteractionId();
        $externalUrl = $request->input('externalUrl');
        Log::info($interactionId . ' => Add gallery image for combination:'. $externalUrl);
        $imageIds = $ozonService->addGalleryImageForCombination($productId, $combinationId, $externalUrl);
        Log::info($interactionId . ' => Add gallery image for combunation response:'. json_encode($imageIds));
        return response()->json($imageIds);
    }

    public function syncProducts(OzonService $ozonService, DropshippService $dropshippService)
    {
        Log::info('Sync started!');
        $notifyingProductIds = $ozonService->syncProducts();
        $dropshippService->notifyProducts($notifyingProductIds);
        return response()->json(['OK']);
    }

    public function notifyProducts(DropshippService $dropshippService)
    {
        Log::info('Notify product started!');
        $dropshippService->notifyProducts($notifyingProductIds);
        return response()->json(['OK']);
    }

    public function gearmanTry(OzonService $ozonService, DropshippService $dropshippService)
    {
        Log::info('Try add gearman job!');

        GearmanService::add([
            'msg' => 'Test msg',
            'ts' => time(),
            'dt' => date('Y-m-d H:i:s'),
        ]);

        return response()->json(['OK']);
    }

    public function testMethod(Request $request)
    {
        // $result = ['result' => 'OK'];
        // // $ozonService = new OzonService();
        // // $processProductResult = $ozonService->processProductToOzon(['mall_variant_id' => '8f350454-54b7-45fb-ae95-6c3834bf4281']);

        $dropshippService = new DropshippService();
        $result = $dropshippService->notifyOrder([
            'data' => null,
            'type' => 'decline',
            'notified' => 0,
            'order_nr' => '28618366-0039'
        ]);

        //  $result = GearmanService::processForRetry(new \GearmanJob);

        // //$resulr = boolval(preg_match('/^JNTCU(\d){10}YQ$/i', $test));
        // //GearmanService::addProcessProductToOzonNotification(['mall_variant_id' => $variant['mallVariantId']]);

        // // $product = json_decode($request->getContent(), true);

        // // $ozonService = new OzonService();
        // // $processProductResult = $ozonService->sendStockAndPriceAndEnabledForProduct($product);        

        return response()->json($result);
    }
}
