<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 21.04.2019
 * Time: 1:10
 */

namespace App\Http\Controllers;

use App\Services\OzonService;
use Laravel\Lumen\Routing\Controller as BaseController;

class ChatController extends BaseController
{
    public function SyncChat(){

        /*get eddy tickets*/
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://mallmycom.helpdeskeddy.com/api/v2/tickets/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "sergey.chernov@corp.mail.ru:sever754");
        //curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        //$response = curl_exec($ch);
        //curl_close($ch);
        //return $response;



        /*get ozone chat list*/
        $o = new OzonService();
        $chatList = $o->sendData('/v1/chat/list',['page_size' => '10']);
        return $chatList;
    }
}