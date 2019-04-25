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

        /*$currentEddyTickets = json_decode($es->getTickets(['status_list'=>'open','search'=>'Ozon order number']),1);
        var_dump($currentEddyTickets);


        $chatList = json_decode($os->getChats(),1);
        var_dump($chatList);*/

        $es->addMessage(303896,'testmess');



    }
}