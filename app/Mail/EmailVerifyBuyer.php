<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerifyBuyer extends Mailable
{
    use Queueable, SerializesModels;

    public $firstname;
    public $code;

    public function __construct($firstname, $code)
    {
        $this->firstname = $firstname;
        $this->code = $code;
    }

    public function build()
    {
        return $this->subject('Verify Your Email')
                    ->view('emails.buyerverifyemail');
    }
}
