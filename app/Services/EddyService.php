<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 24.04.2019
 * Time: 0:18
 */

namespace App\Services;


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
        var_dump($url);
        //die();
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

    public function addTicket($chatData){
        $eddyData = self::convertChatData($chatData);
        $addTicketResponse = $this->sendData($this->addTicketUrl,$eddyData,true);
        return $addTicketResponse;
    }

    public function addMessage($ticket,$text){
        $url = str_replace('{ticketId}', $ticket, $this->addMessageUrl );
        $messageData = $this->sendData($url,['text'=>$text], true);
        return $messageData;
    }

    public static function convertChatData($chatData){
        return [
            'title' => 'Ozon order number: ' . $chatData['order_number'],
            'description' => 'Ozon order number: ' . $chatData['order_number'],
            'owner_id' => '2116',

        ];
    }


}