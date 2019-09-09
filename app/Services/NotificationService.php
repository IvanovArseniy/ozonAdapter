<?php
namespace App\Services;

use Log;

class NotificationService
{
    protected $orderUrl;
    protected $interactionId;

    public function __construct() {
        $this->baseUrl = config('app.dropshipp_base_url');
        $this->tokenUrl = config('app.dropshipp_token_url');
        $this->notificationUrl = config('app.dropshipp_notification_url');

        $this->interactionId = uniqid();
    }

    public function sendWeekAgoOrderEmail($orderNr)
    {
        $result = $this->sendData([
            'subject' => 'Статус заказа "Ожидает сборки" не сменился за 7 дней',
            'body' => '<p>Статус "Ожидает сборки" заказа <strong>' . $orderNr . '</strong> не сменился за 7 дней</p>'
        ]);
        return $result;
    }

    private function sendData($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $this->addToken($this->notificationUrl));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $data_string = json_encode($data, JSON_UNESCAPED_UNICODE);
        Log::info($this->interactionId . ' => New notification: ' . $data_string);
        $headers = ['Content-Type: application/json', 'Content-Length: ' . strlen($data_string)];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        curl_close($ch);
        Log::info($this->interactionId . ' => New notification result: ' . $response);

        $response = json_decode($response, true);

        return $response;
    }
}