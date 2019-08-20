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
use vendor\project\StatusTest;

class ChatController extends BaseController
{
    public function SyncChat(OzonService $os, EddyService $es){
        //$currentEddyTickets = json_decode($es->getTickets(['status_list'=>'open','search'=>'Ozon order number','owner_list'=>'2116']),1);
        $chatAnswer = json_decode($os->getChats(),1);
        $chatList = $chatAnswer['result'];
        foreach ($chatList as $chatItem)
        {
            if ($os->isChatTicketExists($chatItem['id']))
            {

                $ticket = $es::getByExistingChatId($chatItem['id']);

                if ($chatItem['last_message_id'] == $ticket->last_added_message_id)
                {
                    echo "Synchronized (" . $chatItem['id'] . ")" ;
                    continue;
                }

                $chatMessages = $os->getChatMessages($chatItem['id'],$ticket->last_added_message_id,3);
                foreach ($chatMessages['result'] as $chatMessage)
                {
                    $isMessageAdded = $es->addMessage($ticket->eddy_ticket_id, $chatMessage['text'],$chatMessage['file']);
                }

                $es::updateRegisteredTicket($ticket->eddy_ticket_id,['last_added_message_id'=>$chatMessage['id']]);;
                return;
            }

            $ticket = $es->addTicket($chatItem);
            $chatMessages = $os->getChatMessages($chatItem['id'],null,3);
            if (!array_key_exists('errors',$ticket))
            {
                foreach ($chatMessages['result'] as $chatMessage)
                {
                    $isMessageAdded = $es->addMessage($ticket['data']['id'], $chatMessage['text'],$chatMessage['file']);
                }
                $es->registerTicketInDb($chatItem,$ticket['data'], $chatMessage['id']);
            }
        }
        echo 1;
    }
}