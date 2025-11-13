<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $sellerName;

    public function __construct($sellerName = '')
    {
        $this->sellerName = $sellerName;
    }

    public function build()
    {
        return $this->subject('Your Alebaz Password Was Changed')
                    ->view('emails.password_reset_success')
                    ->with(['sellerName' => $this->sellerName]);
    }
}
