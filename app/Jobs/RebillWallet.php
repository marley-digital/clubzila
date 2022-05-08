<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Http\Controllers\Traits\Functions;
use App\Models\Notifications;
use App\Models\Subscriptions;

class RebillWallet implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Functions;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      $subscriptions = Subscriptions::where('ends_at', '<', now())
      ->whereRebillWallet('on')
      ->whereCancelled('no')
      ->get();

      if ($subscriptions) {

        foreach ($subscriptions as $subscription) {

          // Get price of creator
          $amount = $subscription->subscribed()->price;

          if ($subscription->user()->wallet >= $amount && $subscription->subscribed()->free_subscription == 'no') {

            // Admin and user earnings calculation
            $earnings = $this->earningsAdminUser($subscription->subscribed()->custom_fee, $amount, null, null);

            // Insert Transaction
            // $txnId, $userId, $subscriptionsId, $subscribed, $amount, $userEarning, $adminEarning, $paymentGateway, $type, $percentageApplied
            $this->transaction('subw_'.str_random(25), $subscription->user()->id, $subscription->id, $subscription->subscribed()->id, $amount, $earnings['user'], $earnings['admin'], 'Wallet', 'subscription', $earnings['percentageApplied']);

            // Subtract user funds
            $subscription->user()->decrement('wallet', $amount);

            // Add Earnings to User
            $subscription->subscribed()->increment('balance', $earnings['user']);

            // Send Notification to User --- destination, author, type, target
            Notifications::send($subscription->subscribed()->id, $subscription->user()->id, 12, $subscription->user()->id);

            $subscription->update([
      						'ends_at' => now()->addMonth()
      					]);
          }
        }
      }

    }
}
