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

    public function __construct(){
        $this->baseUrl = config('app.eddy_base_url');
        $this->getTicketsUrl = config('app.eddy_get_tickets_url');
    }

    private function sendData($url, $data = []){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (!empty($data)){
            $queryString = http_build_query($data);
            $url .= '?' . $queryString;
        }

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, config('app.eddy_login') . ':' . config('app.eddy_api_key'));

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;

    }

    public function getTickets($filter=[]){
        $ticketsResponse = $this->sendData($this->getTicketsUrl,$filter);
        return $ticketsResponse;
    }

}