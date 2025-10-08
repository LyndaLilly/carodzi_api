<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SellerOrderAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via($notifiable)
    {
        return ['database']; // stored in DB for dashboard
    }

    public function toArray($notifiable)
    {
        return [
            'title'   => 'New Order Placed',
            'message' => 'An order has been placed for your product "' . $this->order->product_name . '". Admin will process it.',
            'order_id'=> $this->order->id,
        ];
    }
}
