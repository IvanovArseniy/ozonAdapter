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
        $currentEddyTickets = $es->getTickets();
        return $currentEddyTickets;


        /*get ozone chat list*/
//        $chatList = $os->sendData('/v1/chat/list',['page_size' => '10']);
//        return $chatList;
    }
}