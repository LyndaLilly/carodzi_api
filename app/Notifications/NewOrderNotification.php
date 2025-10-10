<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOrderNotification extends Notification
{
    use Queueable;

    public $productName;

    public function __construct($productName)
    {
        $this->productName = $productName;
    }

    public function via($notifiable)
    {
        return ['database']; // store in notifications table
    }

    public function toArray($notifiable)
    {
        return [
            'message' => "Order from your product: {$this->productName}. Wait for admin delivery and payment.",
        ];
    }
}
