<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 21.04.2019
 * Time: 1:10
 */

namespace App\Http\Controllers;

use App\Services\EddyService;
use App\Services\OzonService;
use Laravel\Lumen\Routing\Controller as BaseController;

class ChatController extends BaseController
{
    public function SyncChat(OzonService $os, EddyService $es){
        /*get ozone chat list*/
        $chatList = $os->sendData('/v1/chat/list',['page_size'=>100]);
        $arChats = json_decode($chatList ,1);
        $testChat = $arChats['result'][2];
//        echo "<pre>";
//        var_dump($arChats);
//        echo "</pre>";

        $added = $es->addTicket($testChat);
        return $added;



        $currentEddyTickets = $es->getTickets();

        $arTickets = json_decode($currentEddyTickets,1);
        echo "<pre>";
        var_dump($arTickets);
        //var_dump( $arTickets['data']['303892'] );
        echo "</pre>";
        //return response()->json($currentEddyTickets );




    }
}