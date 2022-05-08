<?php

namespace App\Listeners;

use App\Events\LiveBroadcasting;
use App\Models\Notifications;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LiveBroadcastingListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\LiveBroadcasting  $event
     * @return void
     */
    public function handle(LiveBroadcasting $event)
    {
        $user = $event->user;

        // Get Subscriptions Active
        $subscriptionsActive = $user->mySubscriptions()
            ->where('stripe_id', '=', '')
              ->where('ends_at', '>=', now())
              ->where('cancelled', '=', 'no')
              ->orWhere('stripe_status', 'active')
                ->where('stripe_id', '<>', '')
                  ->whereStripePrice($user->plan)
              ->orWhere('stripe_id', '=', '')
                ->where('free', '=', 'yes')
                ->whereStripePrice($user->plan)
                    ->chunk(500, function ($subscriptions) use ($user) {
                      foreach ($subscriptions as $subscription) {
                        // Notify to subscriber - Destination, Author, Type, Target
                        Notifications::send($subscription->user_id, $user->id, 14, $user->id);
                      }
                    });
    }
}
