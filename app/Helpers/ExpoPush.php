<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class ExpoPush
{
    public static function send($expoToken, $title, $body, $data = [])
    {
        return Http::post('https://exp.host/--/api/v2/push/send', [
            'to'    => $expoToken,
            'sound' => 'default',
            'title' => $title,
            'body'  => $body,
            'data'  => $data,
        ]);
    }
}

