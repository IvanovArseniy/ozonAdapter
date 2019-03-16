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

    public function setOrderStatus(OzonService $ozonService, Request $request)
    {
        Log::info('Set order status:'. $request->input('orderId'));
        $result = $ozonService->setOrderStatus($request->input('orderId'), $request->getContent());
        Log::info('Set order status result:'. json_encode($result));
        return response()->json($result);
    }

    public function getOrderList(OzonService $ozonService, DropshippService $dropshippService)
    {
        Log::info('Get list of orders');
        $notifyingOrderIds = $ozonService->getOrderList();
        $notifyResult = $dropshippService->notifyOrders($notifyingOrderIds);
        return response()->json($notifyResult);
    }
}
