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

        $currentEddyTickets = json_decode($es->getTickets(['status_list'=>'open','search'=>'Ozon order number']),1);
        $preparedTickets = $es::prepareList($currentEddyTickets);
        $chatList = json_decode($os->getChats(),1);

        foreach ($chatList['result'] as $key => $chatData){

            if (array_key_exists($chatData['order_number'], $preparedTickets)){
                //Заявка по заказу чата уже есть
                if ($chatData['last_message_id'] > 0){
                    $chatId = $chatData['id'];
                    $messId = $chatData['last_message_id'];
                    $lastMess = $os->getChatMessage($chatId, $messId);
                    if (!empty($lastMess['result'])){
                        $es->addMessage($chatData['order_number'],$lastMess['result']['0']['text']);
                    }
                }
            }
            else{
                $es->addTicket($chatData, $os);
            }
        }

        echo 'Done';
    }

}