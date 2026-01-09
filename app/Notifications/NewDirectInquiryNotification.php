<?php
namespace App\Notifications;

use App\Models\DirectInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NewDirectInquiryNotification extends Notification
{
    use Queueable;

    protected $inquiry;

    public function __construct(DirectInquiry $inquiry)
    {
        $this->inquiry = $inquiry;
    }

    public function via($notifiable)
    {
        return ['database']; // in-app notification
    }

    public function toDatabase($notifiable)
    {
        return [
            'inquiry_id' => $this->inquiry->id,
            'product_id' => $this->inquiry->product_id,
            'message'    => $this->inquiry->message,
        ];
    }
}
