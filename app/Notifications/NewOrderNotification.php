<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\NewOrderNotification;

class NewOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $productName;

    /**
     * Create a new notification instance.
     *
     * @param string $productName
     */
    public function __construct($productName)
    {
        $this->productName = $productName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        // We’ll store in DB so it can show in dashboard
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'message' => "Order from your product: {$this->productName}. Wait for admin delivery and payment.",
        ];
    }
}
