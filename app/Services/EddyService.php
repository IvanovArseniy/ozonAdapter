<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 24.04.2019
 * Time: 0:18
 */

namespace App\Services;

use App\Services\OzonService;

class EddyService
{
    protected $baseUrl;
    protected $getTicketsUrl;
    protected $getTicketsFieldsUrl;

    protected $addTicketUrl;
    protected $addMessageUrl;

    public function __construct(){
        $this->baseUrl = config('app.eddy_base_url');
        $this->getTicketsUrl = config('app.eddy_get_tickets_url');
        $this->getTicketsFieldsUrl = config('app.eddy_get_tickets_fields_url');

        $this->addTicketUrl = config('app.eddy_add_ticket_url');
        $this->addMessageUrl = config('app.eddy_add_message_url');
    }

    private function sendData($url, $data = [], $usePost = false){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (!empty($data)){
            $queryString = http_build_query($data);
            if (!$usePost){
                $url .= '?' . $queryString;
            }

        }
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, config('app.eddy_login') . ':' . config('app.eddy_api_key'));
        if ($usePost){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,$queryString);
        }


        $response = curl_exec($ch);
        curl_close($ch);
        return $response;

    }

    public function getTickets($filter=[]){
        $ticketsResponse = $this->sendData($this->getTicketsUrl,$filter);
        return $ticketsResponse;
    }

    public function getTicketFields(){
        $fieldsResponse = $this->sendData($this->getTicketsFieldsUrl);
        return $fieldsResponse;
    }

    public function addTicket($chatData, OzonService $ozon){
        $eddyData = self::convertChatData($chatData);
        $addTicketResponse = json_decode($this->sendData($this->addTicketUrl,$eddyData,true),1);

        if ($chatData['last_message_id'] > 0){
            $chatId = $chatData['id'];
            $messId = $chatData['last_message_id'];
            $lastMess = $ozon->getChatMessage($chatId, $messId);
            if (!empty($lastMess['result'])){
                $this->addMessage($addTicketResponse['data']['id'],$lastMess['result']['0']['text']);
            }
        }
        return $addTicketResponse;
    }

    public function addMessage($ticket,$text){
        $url = str_replace('{ticketId}', $ticket, $this->addMessageUrl );
        $messageData = $this->sendData($url,['text'=>$text], true);
        return $messageData;
    }

    public static function getOzonOrderNumber($title){
        $exploded = explode(':', $title);
        if (!empty($exploded[1])){
            return trim($exploded[1]);
        }
        return false;
    }

    public static function prepareList($list){
        $preparedData = [];
        foreach ($list['data'] as $ticketID => $ticketData){
            $ozonOrderNum = self::getOzonOrderNumber($ticketData['title']);
            $preparedData[$ozonOrderNum] = $ticketData;
        }
        return $preparedData;
    }

    public static function convertChatData($chatData){
        return [
            'title' => 'Ozon order number: ' . $chatData['order_number'],
            'description' => 'Ozon order number: ' . $chatData['order_number'],
            'owner_id' => '2116',

        ];
    }


}