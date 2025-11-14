<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BuyerEmailResetPasswordCode extends Mailable
{
    use Queueable, SerializesModels;

    public $buyerName;
    public $resetCode;
    public $type;

    public function __construct($buyerName = '', $resetCode, $type = "new")
    {
        $this->buyerName = $buyerName;
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
