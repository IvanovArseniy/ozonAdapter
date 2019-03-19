<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\OzonService;
use App\Services\DropshippService;

class OrderController extends BaseController
{
    public function getOrderInfo(OzonService $ozonService, Request $request, $orderId)
    {
        Log::info('Get order info:'. $orderId);
        $result = $ozonService->getOrderInfo($orderId);
        Log::info('Get order info result:'. json_encode($result));
        return response()->json($result);
    }

    public function setOrderStatus(OzonService $ozonService, Request $request, $orderId)
    {
        Log::info('Set order status:'. $orderId);
        Log::info('Set order status:'. json_encode($request->getContent()));
        $statusInfo = json_decode($request->getContent(), true);
        $result = $ozonService->setOrderStatus($orderId, $statusInfo['fulfillmentStatus'], isset($statusInfo['trackingNumber']) ? $statusInfo['trackingNumber'] : null);
        Log::info('Set order status result:'. json_encode($result));
        return response()->json($result);
    }

    public function getOrderList( DropshippService $dropshippService)
    {$ozonService = new OzonService();
        Log::info('Get list of orders');
        $notifyingOrderIds = $ozonService->getOrderList();
        $notifyResult = $dropshippService->notifyOrders($notifyingOrderIds);
        return response()->json($notifyResult);
    }
}
