<?php

namespace App\Http\Controllers;

use Log;
use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request as Request;
use App\Services\OzonService;
use App\Services\DropshippService;

class OrderController extends BaseController
{
    public function getOrderInfo(OzonService $ozonService, Request $request)
    {
        Log::info('Get order info:'. $request->input('orderId'));
        $result = $ozonService->getOrderInfo($request->input('orderId'));
        //TODO:status mapping

        //paymentStatus: AWAITING_PAYMENT, ID, CANCELLED, REFUNDED, PARTIALLY_REFUNDED, INCOMPLETE
        $response = [
            //'paymentStatus': {paymentStatus} ??
            'fulfillmentStatus' => $result->status,
            //'ipAddress': {ipAddress}, ??
            'email' => $result->address->email,
            //'createDate': {createDate}, now?? // Формат: '2018-05-31 15:08:36 +0000'
            'refererUrl' => 'http://ozon.ru/',
            'shippingPerson' => [
                'name' => $result->adress->addressee,
                'phone' => $result->adress->phone,
                //'countryCode': {countryCode}, ??
                'postalCode' => $result->adress->zip_code, // почтовый индекс
                //'stateOrProvinceName': {stateOrProvinceName}, ?? // область, край округ и т.п.
                'city' => $result->adress->city,
                'street' => $result->adress->address_tail, // улица, дом, корпус, квартира
            ],
            'items' => array()
        ];

        foreach ($result->items as $key => $item) {
            
            array_push($response->items, [
                'price' => $item->price,
                'shipping': {shipping}, // ?? Стоимость доставки рассчитанная для этой позиции
                'quantity' => $item=>quantity,
                'name': $item->name,
                'imageUrl': $item->imageUrl // Урл картинки будет фактически использоваться в интерфейсе Dropshipp
                //'smallThumbnailUrl': {smallThumbnailUrl}, ?? //Миниатюра
                //'description': {description} ??
            ]);
        }
        Log::info('Set order status result:'. $response);

        return response()->json($response);
    }

    public function setOrderStatus(OzonService $ozonService, Request $request)
    {
        Log::info('Set order status:'. $request->input('orderId'));
        $result = $ozonService->setOrderStatus($request->input('orderId'), $request->getContent());
        return response()->json($result);
    }

    public function getOrderList(OzonService $ozonService, DropshippService $dropshippService)
    {
        Log::info('Get list of orders');
        $notifyOrders = $ozonService->getOrderList();
        Log::info($notifyOrders);
        $notifyResult = $dropshippService->notifyOrders($notifyOrders);
        return response()->json($notifyOrders);
    }
}
