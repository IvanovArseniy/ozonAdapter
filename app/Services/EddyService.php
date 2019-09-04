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
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    //'Content-Type: application/json',
                    'Content-Type: application/x-www-form-urlencoded',
                    'Cache-Control: no-cache',
                    'Content-Length: ' . strlen($queryString),

                )
            );
        }
        $response = curl_exec($ch);
        if($response === false)
        {
            var_dump( 'Ошибка curl: ' . curl_error($ch));
        }
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
    public function getTicketMessages($ticketId)
    {
        $url = str_replace('{ticketId}', $ticketId, $this->addMessageUrl );
        $currentPage = 1;
        $totalPages = 1;
        $result = [];
        while ($currentPage <= $totalPages)
        {
            $getMessagesResponse = json_decode( $this->sendData($url, ['page'=>$currentPage],false),1);
            foreach ($getMessagesResponse['data'] as $dataItem)
            {
                $result[] = $dataItem;
            }
            $currentPage ++;
            $totalPages = $getMessagesResponse['pagination']['total_pages'];
        }
        $getMessagesResponse['result'] = $result;
        return $getMessagesResponse;
    }
    public function addTicket($chatData){
        $eddyData = self::convertChatData($chatData);
        $addTicketResponse = json_decode($this->sendData($this->addTicketUrl,$eddyData,true),1);
        return $addTicketResponse;
    }

    public function getRegisteredTickets()
    {
        $tickets = app('db')->connection('mysql')->table('chat_eddy_ticket')
            ->where('eddy_ticket_unique_id','IXF-00846')
            ->get()
            ->all();
        return $tickets;
    }
    public function registerTicketInDb($chatData, $ticketData)
    {
        try {
            $ticketRegisterResult = app('db')->connection('mysql')->table('chat_eddy_ticket')
                ->insert([
                    'chat_id' => $chatData['id'],
                    'eddy_ticket_id' => $ticketData['id'],
                    'eddy_ticket_unique_id' => $ticketData['unique_id'],
                    'last_message_id' => $chatData['last_message_id'],
                    'last_added_message_id' => null,
                ]);
        } catch (\Exception $e) {
            $ticketRegisterResult = false;
        }
    }

    public function addMessage($ticket,$text,$files = null){
        $url = str_replace('{ticketId}', $ticket, $this->addMessageUrl );
        $messagePayload = ['text'=>$text];
        if (is_array($files) && !empty($files)){
            $messagePayload['files'] = $files;
        }
        $addMessageResponse = json_decode( $this->sendData($url,$messagePayload, true),1);
        return $addMessageResponse;
    }

    public static function getByExistingChatId($chatId)
    {
        $ticket = app('db')->connection('mysql')->table('chat_eddy_ticket')
            ->where('chat_id', $chatId)
            ->first();
        return $ticket;
    }

    public static function updateRegisteredTicket($eddyTicketId,$fields)
    {
        app('db')->connection('mysql')->table('chat_eddy_ticket')
            ->where('eddy_ticket_id', $eddyTicketId)
            ->update($fields);

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

    public static function getRelatedOrders($customerId)
    {
        $orderResult = app('db')->connection('mysql')
            ->table('orders')
            ->where('ozon_order_nr', 'like', $customerId . '-%')
            ->where('deleted', 0)
            ->get()
            ->all();
        return $orderResult;
    }
    public static function convertChatData($chatData){
        $customerId = self::getCustomerId($chatData);
        $customerOrders = self::getRelatedOrders($customerId);
        $ticketDescriptionOrders = [];
        foreach ($customerOrders as $customerOrder)
        {
            array_push($ticketDescriptionOrders, self::makeChatOrderLink($customerOrder));
        }
        return [
            'title' => 'Пользователь: ' . $customerId,
            'description' => empty($ticketDescriptionOrders) ? 'Нет заказов' : 'Номера заказов Озон:' . implode("<br />",$ticketDescriptionOrders),
            'owner_id' => '2116',

        ];
    }

    public static function makeChatOrderLink($orderData)
    {
        if (empty($orderData)){
            return '';
        }
        $url = config('app.dev_dropship_url') . '/orders/?txt=' . $orderData->ozon_order_id;
        return "<a href='{$url}'>" . $orderData->ozon_order_nr ."</a>";
    }
    public static function getCustomerId($chatData)
    {
        foreach ($chatData['users'] as $user){
            if ($user['type'] == 'customer'){
                return $user['id'];
            }
        }
    }
}