<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\OzonService;
use App\Services\DropshippService;

class OrderController extends BaseController
{
    public function getOrderInfo(OzonService $ozonService, Request $request, $orderNr)
    {
        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Get order info:'. $orderNr);
        $result = $ozonService->getOrderInfoCommon($orderNr);
        Log::info($interactionId . ' => Get order info result:'. json_encode($result));
        return response()->json($result);
    }

    public function setOrderStatus(OzonService $ozonService, Request $request, $orderNr)
    {
        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Set order status:'. $orderNr);
        Log::info($interactionId . ' => Set order status:'. json_encode($request->getContent()));
        $statusInfo = json_decode($request->getContent(), true);
        $result = $ozonService->setOrderStatus(
            $orderNr,
            $statusInfo['fulfillmentStatus'],
            isset($statusInfo['trackingNumber']) ? $statusInfo['trackingNumber'] : null,
            isset($statusInfo['items']) ? $statusInfo['items'] : null);
        Log::info($interactionId . ' => Set order status result:'. json_encode($result));
        return response()->json($result);
    }

    public function setOrderStatus1(OzonService $ozonService, Request $request, $orderNr)
    {
        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Set order status:'. $orderNr);
        Log::info($interactionId . ' => Set order status:'. json_encode($request->getContent()));
        $statusInfo = json_decode($request->getContent(), true);
        $result = $ozonService->setOrderStatus1(
            $orderNr,
            $statusInfo['fulfillmentStatus'],
            isset($statusInfo['trackingNumber']) ? $statusInfo['trackingNumber'] : null,
            isset($statusInfo['items']) ? $statusInfo['items'] : null);
        Log::info($interactionId . ' => Set order status result:'. json_encode($result));
        return response()->json($result);
    }

    public function getOrderList(OzonService $ozonService, DropshippService $dropshippService)
    {
        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Get list of orders');
        $notifyingOrderIds = $ozonService->getOrderList();
        return response()->json($notifyingOrderIds);
    }

    public function sendOrderNotifications(DropshippService $dropshippService)
    {
        Log::info(' => Send order notifications');
        //$notifyResult = $dropshippService->notifyOrders();
        $notifyResult = [];
        Log::info(' => Send order notifications ready');
        return response()->json($notifyResult);
    }

    public function setOrderNr(OzonService $ozonService)
    {
        $interactionId = $ozonService->getInteractionId();
        Log::info($interactionId . ' => Set order nr');
        $result = $ozonService->setOrderNr();
        return response()->json($result);
    }
}
