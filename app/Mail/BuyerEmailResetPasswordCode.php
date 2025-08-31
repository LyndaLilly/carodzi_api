<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BuyerEmailResetPasswordCode extends Mailable
{
    use Queueable, SerializesModels;

    public $firstname;
    public $resetCode;

    public function __construct($firstname, $resetCode)
    {
        $this->firstname = $firstname;
        $this->resetCode = $resetCode;
    }

    public function build()
    {
        return $this->subject('Verify Your Email')
                    ->view('emails.buyerpasswordcode');
    }
}
