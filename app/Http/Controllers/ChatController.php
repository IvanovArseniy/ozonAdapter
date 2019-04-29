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

        var_dump($chatList);
        var_dump($preparedTickets);
        die();


        foreach ($chatList['result'] as $key => $chatData){
            $needSync = true;
            //Заявка по заказу чата уже есть
            if (array_key_exists($chatData['order_number'], $preparedTickets)){
                //Есть сообщения в чате
                if ($chatData['last_message_id'] > 0){
                    $lastSyncedMess = app('db')->connection('mysql')->table('chat_sync')
                        ->where('order_id', $chatData['order_number'])
                        ->select('last_message_id')
                        ->first();
                    $lastSyncedMessID = $lastSyncedMess->last_message_id;
//                    if (is_null($lastSyncedMess) || !isset($lastSyncedMess->last_message_id)){
//                        app('db')->connection('mysql')->table('chat_sync')
//                        ->insert([
//                                'order_id' => $chatData['order_number'],
//                                'last_message_id' => $chatData['last_message_id'],
//                            ]);
//                        $needSync = true;
//                    }
//                    else{
//                        $lastSyncedMessID = $lastSyncedMess->last_message_id;
//                        $needSync = false;
//                        if ($chatData['last_message_id'] > $lastSyncedMessID){
//                            $needSync = true;
//                        }
//
//                    }

                    if ($needSync){

                        $chatId = $chatData['id'];
                        $messId = $chatData['last_message_id'];
                        $chatMessages = $os->getChatMessages($chatId, $lastSyncedMessID );
                        if (!empty($chatMessages['result'])){
                            foreach ($chatMessages['result'] as $key => $chatMessage){
                                if ($chatMessage['id'] < $lastSyncedMessID && $chatMessage['text']){
                                   $es->addMessage($preparedTickets[$chatData['order_number']]['id'],$chatMessage['text']);
                                }

                            }
                            app('db')->connection('mysql')->table('chat_sync')
                                ->where('order_id',$chatData['order_number'])
                                ->update([
                                    'last_message_id' => $chatMessages['result'][count($chatMessages['result']) - 1]['id'],
                                ]);
                        }

                    }
                }
            }
            else{
                $es->addTicket($chatData, $os);
            }
        }
        var_dump('done');
    }

}