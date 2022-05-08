<?php

namespace App\Http\Controllers;

use App\Models\Conversations;
use App\Models\Messages;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Subscriptions;
use App\Models\AdminSettings;
use App\Models\Withdrawals;
use App\Models\Notifications;
use App\Models\Transactions;
use Fahim\PaypalIPN\PaypalIPNListener;
use App\Helper;
use Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Models\PaymentGateways;
use Image;


class SubscriptionsController extends Controller
{
  use Traits\Functions;

  public function __construct(Request $request, AdminSettings $settings) {
    $this->request = $request;
    $this->settings = $settings::first();
  }

  /**
	 * Buy subscription
	 *
	 * @return Response
	 */
  public function buy()
  {
    // Find the User
    $user = User::whereVerifiedId('yes')
        ->whereId($this->request->id)
        ->where('id', '<>', auth()->user()->id)
        ->firstOrFail();

    // Check if subscription exists
    $checkSubscription = auth()->user()->mySubscriptions()
      ->whereStripePrice($user->plan)
        ->where('ends_at', '>=', now())->first();

    if ($checkSubscription) {
      return response()->json([
          'success' => false,
          'errors' => ['error' => trans('general.subscription_exists')],
      ]);
    }

  //<---- Validation
  $validator = Validator::make($this->request->all(), [
      'payment_gateway' => 'required',
      'agree_terms' => 'required',
      ]);

    if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->getMessageBag()->toArray(),
            ]);
        }

        // Wallet
        if ($this->request->payment_gateway == 'wallet') {
          return $this->sendWallet();
        }

      if ($this->request->payment_gateway == 'mobile') {
          return $this->swahiliesPayment();
      }



        // Get name of Payment Gateway

        $payment = PaymentGateways::findOrFail($this->request->payment_gateway);

        // Send data to the payment processor
        return redirect()->route(str_slug($payment->name), $this->request->except(['_token']));

  }// End Method Send

  /**
	 * Free subscription
	 *
   */
  public function subscriptionFree()
  {
    // Find user
    $creator = User::whereId($this->request->id)
        ->whereFreeSubscription('yes')
        ->whereVerifiedId('yes')
          ->firstOrFail();

    // Verify subscription exists
    $subscription = Subscriptions::whereUserId(auth()->user()->id)
        ->whereStripePrice($creator->plan)
          ->whereFree('yes')
            ->first();

      if ($subscription) {
        return response()->json([
          'success' => false,
          'error' => trans('general.subscription_exists'),
        ]);
      }

    // Insert DB
    $sql          = new Subscriptions();
    $sql->user_id = auth()->user()->id;
    $sql->stripe_price = $creator->plan;
    $sql->free = 'yes';
    $sql->save();

    // Send Email to User and Notification
    Subscriptions::sendEmailAndNotify(auth()->user()->name, $creator->id);

    return response()->json([
      'success' => true,
    ]);
  } // End Method SubscriptionFree

  public function cancelFreeSubscription($id)
  {
    $checkSubscription = auth()->user()->userSubscriptions()->whereId($id)->firstOrFail();
    $creator = User::wherePlan($checkSubscription->stripe_price)->first();

    // Delete Subscription
    $checkSubscription->delete();

    session()->put('subscription_cancel', trans('general.subscription_cancel'));
    return redirect($creator->username);

  }// End Method cancelFreeSubscription

  public function cancelWalletSubscription($id)
  {
    $subscription = auth()->user()->userSubscriptions()->whereId($id)->firstOrFail();
    $creator = User::wherePlan($subscription->stripe_price)->first();

    // Delete Subscription
    $subscription->cancelled = 'yes';
    $subscription->save();

    session()->put('subscription_cancel', trans('general.subscription_cancel'));
    return redirect($creator->username);

  }// End Method cancelWalletSubscription

  /**
	 *  Send Tip Wallet
	 *
	 * @return Response
	 */
   protected function sendWallet()
   {
     // Find user
     $creator = User::whereId($this->request->id)
         ->whereVerifiedId('yes')
           ->firstOrFail();

     $amount = $creator->price;

     if (auth()->user()->wallet < $amount) {
       return response()->json([
         "success" => false,
         "errors" => ['error' => __('general.not_enough_funds')]
       ]);
     }

     // Insert DB
     $subscription              = new Subscriptions();
     $subscription->user_id     = auth()->user()->id;
     $subscription->stripe_price = $creator->plan;
     $subscription->ends_at     = now()->add(1, 'month');
     $subscription->rebill_wallet = 'on';
     $subscription->save();

     // Admin and user earnings calculation
     $earnings = $this->earningsAdminUser($creator->custom_fee, $amount, null, null);

     // Insert Transaction
     // $txnId, $userId, $subscriptionsId, $subscribed, $amount, $userEarning, $adminEarning, $paymentGateway, $type, $percentageApplied
     $this->transaction('subw_'.str_random(25), auth()->user()->id, $subscription->id, $creator->id, $amount, $earnings['user'], $earnings['admin'], 'Wallet', 'subscription', $earnings['percentageApplied']);

     // Subtract user funds
     auth()->user()->decrement('wallet', $amount);

     // Add Earnings to User
     $creator->increment('balance', $earnings['user']);

     // Send Email to User and Notification
     Subscriptions::sendEmailAndNotify(auth()->user()->name, $creator->id);

     return response()->json([
       'success' => true,
       'url' => url('buy/subscription/success', $creator->username)
     ]);

   } // End sendTipWallet


    protected function swahiliesPayment()

    {

        $creator = User::whereId($this->request->id)
            ->whereVerifiedId('yes')
            ->firstOrFail();

        $amount = $creator->price;


        $url  = "https://swahiliesapi.invict.site/Api";

        $resp['api'] = 170;
        $resp['code'] = 101;
        $data['api_key'] = "Y2YzZmYxNDhmMzFmNDljOTljMzEyYzkyNTc5YzA2NzY=";



        $meta['user_id'] = auth()->user()->id;
        $meta['name'] = auth()->user()->name;
        $meta['email'] = auth()->user()->email;
        $meta['post_id'] = $this->request->id;
        //$meta['is_message'] =$this->request->isMessage;




        $data["order_id"]=  md5(substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10));
        $data["amount"] =  $amount;


        $data["cancel_url"] = url('explore');
        //$data['is_live'] = false;
        $data["webhook_url"]= url('swahiliesCallbackSubscription');
        $data["success_url"] =  url('buy/subscription/success', $creator->username);
        $data['metadata'] = $meta;

        $resp['data']  =$data;






        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($resp));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        $result = curl_exec($ch);

        $ds = json_decode($result);
        $action = $ds->payment_url;

        return response()->json([
            'success' => true,
//"ss"=>$resp
            'insertBody' => '<form id="form_pp" name="_xclick" action="'.$action.'" method="post"  style="display:none">
                  <input type="hidden" name="cmd" value="_xclick">

                  <input type="submit">
                  </form> <script type="text/javascript">document._xclick.submit();</script>',
        ]);
    }


    public function swahihiliesCallBack(): string
    {
        $request = $this->request->input();
        $request = file_get_contents("php://input");
        $data = json_decode($request);
        if($data->code == 200){
            $txn_details = $data->transaction_details;
            $metadata = $txn_details->metadata;
            $amount = $txn_details->amount;

            $creator = User::whereId($metadata->post_id)
                ->whereVerifiedId('yes')
                ->firstOrFail();

            $amount = $creator->price;



            // Insert DB
            $subscription              = new Subscriptions();
            $subscription->user_id     = $metadata->user_id;
            $subscription->stripe_price = $creator->plan;
            $subscription->ends_at     = now()->add(1, 'month');
            $subscription->rebill_wallet = 'on';
            $subscription->save();

            // Admin and user earnings calculation
            $earnings = $this->earningsAdminUser($creator->custom_fee, $amount, null, null);

            // Insert Transaction
            // $txnId, $userId, $subscriptionsId, $subscribed, $amount, $userEarning, $adminEarning, $paymentGateway, $type, $percentageApplied
            $this->transaction('subw_'.str_random(25), $metadata->user_id, $subscription->id, $creator->id, $amount, $earnings['user'], $earnings['admin'], 'mobile', 'subscription', $earnings['percentageApplied']);



            // Add Earnings to User
            $creator->increment('balance', $earnings['user']);

            // Send Email to User and Notification
            Subscriptions::sendEmailAndNotifyCallBack($metadata->name, $creator->id,$metadata->user_id);



        }

        return $subscription;
    }

}
