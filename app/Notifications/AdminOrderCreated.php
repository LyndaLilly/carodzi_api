<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminOrderCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('🛍️ New Order Received')
            ->greeting('Hello Admin,')
            ->line('A new order has been placed on the platform.')
            ->line('Product: ' . $this->order->product_name)
            ->line('Buyer ID: ' . $this->order->buyer_id)
            ->line('Seller ID: ' . $this->order->seller_id)
            ->line('Payment Method: ' . ucfirst($this->order->payment_method))
            ->line('Total Amount: ₦' . number_format($this->order->total_amount, 2))
            ->action('View Order', url('/admin/orders/' . $this->order->id))
            ->line('Thank you for managing our marketplace!');
    }
}
