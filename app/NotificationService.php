<?php

namespace App;

use Illuminate\Log\Logger;

class NotificationService
{
    public static function send($message,$phone)
    {
        $apikey = "qSjSdqpn1IUdLDkhWhQ6gvLkfY2TwpjWiCPv2eS3XNhIq";
        $senderid = "NOTIFY";

        return;
        
        $endPoint = 'https://api.mnotify.com/api/sms/quick';
        $url = $endPoint . '?key=' . $apikey;
        $mdata = [
            'recipient' => [$phone],
            'sender' => $senderid,
            'message' => strip_tags($message),
        ];

        $ch = curl_init();
        $headers = array();
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mdata));
        $result = curl_exec($ch);
        $data = json_decode($result, true);

        curl_close($ch);
    }
}