<?php

namespace App\Mail;

use App\Models\Promote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PromoteSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $promotion;
    public $seller;

    /**
     * Create a new message instance.
     */
    public function __construct($promotion, $seller)
    {
        $this->promotion = $promotion;
        $this->seller = $seller;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Promotion Submission on alebaz.com')
                    ->markdown('emails.promote.success');
    }
}
