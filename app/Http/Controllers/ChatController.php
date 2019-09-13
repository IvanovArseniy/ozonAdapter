<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 21.04.2019
 * Time: 1:10
 */

namespace App\Http\Controllers;

use App\Services\EddyService;
use App\Services\GearmanService;
use App\Services\OzonService;
use Laravel\Lumen\Routing\Controller as BaseController;
use vendor\project\StatusTest;
use Log;

class ChatController extends BaseController
{
    public function SyncChat(OzonService $os, EddyService $es){
        if ( file_exists(storage_path() . '/app/chatsync.lock') || file_exists(storage_path() . '/app/chatsync_ozon.lock') ){
            return 0;
        }
        file_put_contents(storage_path() . '/app/chatsync.lock', 'Start');
        $chatAnswer = $os->getChats();
        if (!$chatAnswer)
        {
            $unlink = unlink(storage_path() . '/app/chatsync.lock');
            Log::info('Unlink chatsynk.lock cause getChats error.');
            return 0;
        }
        $chatList = $chatAnswer;
        $newTicketsCount = 0;
        Log::info('Chats count getted: ' . count($chatList));
        foreach ($chatList as $chatItem)
        {
            if ($os->isChatTicketExists($chatItem['id']))
            {
                while (true)
                {
                    $ticket = $es::getByExistingChatId($chatItem['id']);
                    if ($chatItem['last_message_id'] == $ticket->last_added_message_id)
                    {
                        break;
                    }
                    $chatMessages = $os->getChatMessages($chatItem['id'],$ticket->last_added_message_id,10);
                    if (!array_key_exists('result',$chatMessages))
                    {
                        continue;
                    }
                    foreach ($chatMessages['result'] as $chatMessage)
                    {
                        if (empty($chatMessage)){
                            continue;
                        }

                        if ($chatMessage['type'] == 'file')
                        {
                            $chatMessage['text'] = "<a href={$chatMessage['file']['url']}>{$chatMessage['file']['name']}</a>";
                        }
                        $isMessageAdded = $es->addMessage($ticket->eddy_ticket_id, $chatMessage['text'],null);
                    }
                    $es::updateRegisteredTicket($ticket->eddy_ticket_id,['last_added_message_id'=>$chatMessage['id']]);;
                }
                continue;
            }
            else{
                $ticket = $es->addTicket($chatItem);
                if (isset($ticket['errors'])){
                    Log::info('addTicket error: ' . $chatItem['id']);
                    continue;
                }
                $newTicketsCount += 1;
                $es->registerTicketInDb($chatItem,$ticket['data']);
                while (true)
                {
                    sleep(1);
                    $exTicket = $es::getByExistingChatId($chatItem['id']);
                    if ($exTicket == null || !is_object($exTicket))
                    {
                        Log::info('Bad exTicket: ' . $chatItem['id']);
                        continue;
                    }
                    if ($exTicket->last_added_message_id == $chatItem['last_message_id']){
                        break;
                    }
                    $chatMessages = $os->getChatMessages($chatItem['id'],$exTicket->last_added_message_id,10);
                    if (!array_key_exists('errors',$ticket))
                    {
                        if (is_array($chatMessages) && array_key_exists('result', $chatMessages))
                        {
                            foreach ($chatMessages['result'] as $chatMessage)
                            {
                                if (empty($chatMessage)){
                                    continue;
                                }
                                if ($chatMessage['type'] == 'file')
                                {
                                    $chatMessage['text'] = "<a href={$chatMessage['file']['url']}>{$chatMessage['file']['name']}</a>";
                                }
                                $isMessageAdded = $es->addMessage($ticket['data']['id'], $chatMessage['text'],null);
                            }
                            $es::updateRegisteredTicket($exTicket->eddy_ticket_id,['last_added_message_id'=>$chatMessage['id']]);;
                        }
                    }
                    else{
                        Log::info('Add ticket error: ' . print_r($ticket['errors']));
                        continue;
                    }
                }
            }
        }
        $unlink = unlink(storage_path() . '/app/chatsync.lock');
        Log::info('New tickets count: ' . $newTicketsCount);
        Log::info('Unlink in 83: ' . $unlink);
        return 0;
    }
    public function SyncChatsFromHelpdesk(OzonService $os, EddyService $es){
        Log::info('From Ozon to Eddy: start');

        if (file_exists(storage_path() . '/app/chatsync.lock') || file_exists(storage_path() . '/app/chatsync_ozon.lock')){
            Log::info('From Ozon to Eddy: exit');
            return 0;
        }
        file_put_contents(storage_path() . '/app/chatsync_ozon.lock', 'Start');
        $chats = $os->getChats();
        if (!$chats)
        {
            $unlink = unlink(storage_path() . '/app/chatsync_ozon.lock');
            Log::info('Unlink chatsync_ozon.lock cause getChats error.');
            return 0;
        }
        if (!is_array($chats)){
            $chatAnswer = json_decode($chats,1);
        }
        else{
            $chatAnswer = $chats;
        }


        Log::info('From Ozon to Eddy: chats count = ' . count($chatAnswer));
        $chatList = $chatAnswer;
        foreach ($chatList as $chatItem)
        {
            $exTicket = $es::getByExistingChatId($chatItem['id']);
            if (!is_object($exTicket))
            {
                continue;
            }
            $exTicketMessages = $es->getTicketMessages($exTicket->eddy_ticket_id);
            $chatMessages = $os->getChatMessages($chatItem['id']);
            if (array_key_exists('result',$exTicketMessages) && array_key_exists('result',$chatMessages)){
                $unsyncedMessagesCount = (count($exTicketMessages['result']) - 1) - count($chatMessages['result']);
                if ($unsyncedMessagesCount > 0)
                {
                    Log::info('From Ozon to Eddy: unsyncedMessagesCount = ' . $unsyncedMessagesCount);
                    for ($i = $unsyncedMessagesCount; $i > 0; $i--)
                    {
                        $isMessageAdded = $os->addChatMessage($chatItem['id'],$exTicketMessages['result'][$i-1]['text']);
                        Log::info('From Ozon to Eddy: add message to ' . $chatItem['id']);
                    }
                    $chatMessagesNew = $os->getChatMessages($chatItem['id']);
                    $lastAddedMessageId = $chatMessagesNew['result'][count($chatMessagesNew['result']) - 1]['id'];
                    $es::updateRegisteredTicket($exTicket->eddy_ticket_id,['last_added_message_id'=>$lastAddedMessageId]);;
                }
            }
        }
        $unlink_ozon = unlink(storage_path() . '/app/chatsync_ozon.lock');
        Log::info('From Ozon to Eddy: end');
    }
    public static function handle()
    {
        GearmanService::chatEddySync();
    }

    public static function handleEddyChats()
    {
        GearmanService::eddyChatSync();
    }
}