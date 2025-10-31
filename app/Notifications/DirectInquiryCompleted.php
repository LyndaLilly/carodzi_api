<?php
namespace App\Notifications;

use App\Models\DirectInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DirectInquiryCompleted extends Notification
{
    use Queueable;

    protected $inquiry;

    public function __construct(DirectInquiry $inquiry)
    {
        $this->inquiry = $inquiry;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("Your Inquiry has been Completed by {$this->inquiry->seller->business_name}")
            ->view('emails.direct_inquiry_completed', [
                'inquiry' => $this->inquiry,
            ]);
    }

}
