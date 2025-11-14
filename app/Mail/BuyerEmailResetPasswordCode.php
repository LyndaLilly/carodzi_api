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
    public $type;

    public function __construct($firstname, $resetCode, $type = "new")
    {
        $this->firstname = $firstname;
        $this->resetCode = $resetCode;
        $this->type      = $type;
    }

    public function build()
    {
        $subject = $this->type === 'resent'
            ? 'Your Password Reset Code (Resent)'
            : 'Your Password Reset Code';

        return $this->subject($subject)
        ->view('emails.buyerpasswordcode')
        ->with([
            'resetCode' => $this->resetCode,
            'buyerName' => $this->buyerName,
            'type' => $this->type
        ]);
    }
}
