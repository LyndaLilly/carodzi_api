<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $resetCode;
    public $sellerName;
    public $type; // 'new' or 'resent'

    public function __construct($resetCode, $sellerName = '', $type = 'new')
    {
        $this->resetCode   = $resetCode;
        $this->sellerName  = $sellerName;
        $this->type        = $type;
    }

    public function build()
    {
        $subject = $this->type === 'resent' 
            ? 'Your Password Reset Code (Resent)' 
            : 'Your Password Reset Code';

        return $this->subject($subject)
                    ->view('emails.password_reset')
                    ->with([
                        'resetCode'  => $this->resetCode,
                        'sellerName' => $this->sellerName,
                        'type'       => $this->type,
                    ]);
    }
}
