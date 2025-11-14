<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BuyerPasswordResetSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $buyerName;

    public function __construct($buyerName = '')
    {
        $this->buyerName = $buyerName;
    }

    public function build()
    {
        return $this->subject('Your Alebaz Password Was Changed')
                    ->view('emails.buyer_password_reset_success')
                    ->with(['buyerName' => $this->buyerName]);
    }
}
