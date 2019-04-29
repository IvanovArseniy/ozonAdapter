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
        $currentEddyTickets = json_decode($es->getTickets(['status_list'=>'open','search'=>'Ozon order number','owner_list'=>'2116']),1);
        $preparedTickets = $es::prepareList($currentEddyTickets);
        $chatList = json_decode($os->getChats(),1);
        if (is_array($chatList['result']) && !empty($chatList['result'])){
            $ticketsCount = 0;
            foreach ($chatList['result'] as $key => $chatData){
                var_dump($chatData);
                $isTicketExists = array_key_exists($chatData['order_number'], $preparedTickets);
                if (!$isTicketExists){
                    $addTicketResponse = $es->addTicket($chatData, $os);
                    $ticketsCount += 1;
                }
                else{

                    $lastAddedEddyMessage = app('db')->connection('mysql')->table('chat_sync')
                        ->where('order_id', $chatData['order_number'])
                        ->first();

                    $fromMessage = null;
                    $dbAction = 'insert';
                    if ($lastAddedEddyMessage){
                        $fromMessage = $lastAddedEddyMessage->last_message_id;
                        $dbAction = 'update';
                    }

                    $chatMessages = $os->getChatMessages($chatData['id'], $fromMessage, 1);
//                    var_dump($chatMessages);
//                    die();
                    $messagesCounter = 0;
                    $eddyMessageFiles = null;
                    foreach ($chatMessages['result'] as $key => $chatMessage){
                        if ($chatMessage['type'] == 'file'){
                            $eddyMessageText = "<a href='{$chatMessage['file']['url']}'>" . $chatMessage['file']['name'].$chatMessage['id'] . "</a>";
//                            $eddyMessageFiles = [
//                                [
//                                    "name" => $chatMessage['file']['name'],
//                                    "url" => $chatMessage['file']['url'],
//                                    //"url" => "http://dss_ozone/test.txt",
//                                    //"data_type" => $chatMessage['file']['mime']
//                                    "data_type" => 'txt'
//                                ]
//                            ];
                        }
                        else{
                            $eddyMessageText = $chatMessage['text'].$chatMessage['id'];
                        }
                        $messagesCounter = $key;
                        $ticketId = $preparedTickets[ $chatData['order_number'] ]['id'];
                        $addMessageResponse = $es->addMessage($ticketId, $eddyMessageText,$eddyMessageFiles);

                    }
                    $lastEddyMessageId = $chatMessages['result'][$messagesCounter]['id'];
                    app('db')->connection('mysql')->table('chat_sync')
                        ->$dbAction([
                            'order_id' => $chatData['order_number'],
                            'last_message_id' => $lastEddyMessageId,
                        ]);
                    var_dump($messagesCounter);
                    //die();
                }
            }
        }
        var_dump('Done');
        var_dump('Add ' . $ticketsCount . ' tickets');
    }

}