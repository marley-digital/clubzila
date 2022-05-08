<?php

namespace App\Http\Controllers;

use Mail;
use App\Helper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AdminSettings;
use Fahim\PaypalIPN\PaypalIPNListener;
use Illuminate\Support\Facades\Validator;
use App\Models\PaymentGateways;
use App\Models\Transactions;
use Laravel\Cashier\Exceptions\IncompletePayment;
use App\Models\Notifications;
use App\Models\Conversations;
use App\Models\Messages;
use App\Models\Updates;
use App\Models\PayPerViews;
use Yabacon\Paystack;


class PayPerViewController extends Controller
{
    use Traits\Functions;

    public function __construct(Request $request, AdminSettings $settings) {
        $this->request = $request;
        $this->settings = $settings::first();
    }

    /**
     *  Send Request
     *
     * @return Response
     */
    public function send()
    {

        if ($this->request->isMessage) {
            // Find Message
            $media = Messages::whereId($this->request->id)->wherePrice($this->request->amount)->whereToUserId(auth()->user()->id)->firstOrFail();

            // Verify that the user has not purchased the content
            if (PayPerViews::whereUserId(auth()->user()->id)->whereMessagesId($this->request->id)->first()) {
                return response()->json([
                    "success" => false,
                    "errors" => ['error' => __('general.already_purchased_content')]
                ]);
            }

        } else {
            // Find Post
            $media = Updates::whereId($this->request->id)->wherePrice($this->request->amount)->where('user_id', '<>', auth()->user()->id)->firstOrFail();

            // Verify that the user has not purchased the content
            if (PayPerViews::whereUserId(auth()->user()->id)->whereUpdatesId($this->request->id)->first()) {
                return response()->json([
                    "success" => false,
                    "errors" => ['error' => __('general.already_purchased_content')]
                ]);
            }
        }

        $messages = [
            'payment_gateway_ppv.required' => trans('general.choose_payment_gateway')
        ];

        //<---- Validation
        $validator = Validator::make($this->request->all(), [
            'payment_gateway_ppv' => 'required'
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->getMessageBag()->toArray(),
            ]);
        }

        switch ($this->request->payment_gateway_ppv) {
            case 'wallet':
                return $this->sendWallet();
                break;

            case 1:
                return $this->sendPayPal();
                break;

            case 2:
                return $this->sendStripe();
                break;

            case 4:
                return $this->sendCCbill();
                break;

            case 5:
                return $this->sendPaystack();

            case "mobile":
                return $this->swahiliesPayment();
                break;
        }


        return response()->json([
            'success' => true,
            'insertBody' => '<i></i>'
        ]);

    } // End method Send

    /**
     *  Send  Wallet
     *
     * @return Response
     */
    protected function sendWallet()
    {
        $amount = $this->request->amount;

        if (auth()->user()->wallet < $amount) {
            return response()->json([
                "success" => false,
                "errors" => ['error' => __('general.not_enough_funds')]
            ]);
        }

        // Check if it is a Message or Post
        $media = $this->request->isMessage ? Messages::find($this->request->id) : Updates::find($this->request->id);

        // Admin and user earnings calculation
        $earnings = $this->earningsAdminUser($media->user()->custom_fee, $amount, null, null);

        // Insert Transaction
        // $txnId, $userId, $subscriptionsId, $subscribed, $amount, $userEarning, $adminEarning, $paymentGateway, $type, $percentageApplied
        $this->transaction('ppv_'.str_random(25), auth()->user()->id, 0, $media->user()->id, $amount, $earnings['user'], $earnings['admin'], 'Wallet', 'ppv', $earnings['percentageApplied']);

        // Subtract user funds
        auth()->user()->decrement('wallet', $amount);

        // Add Earnings to User
        $media->user()->increment('balance', $earnings['user']);

        // Check if is sent by message
        if ($this->request->isMessage) {
            // $user_id, $updates_id, $messages_id
            $this->payPerViews(auth()->user()->id, 0, $this->request->id);
            $url = url('messages', $media->user()->id);

            // Send Email Creator
            if ($media->user()->email_new_ppv == 'yes') {
                $this->notifyEmailNewPPV($media->user(), auth()->user()->username, $media->message, 'message');
            }

            // Send Notification - destination, author, type, target
            Notifications::send($media->user()->id, auth()->user()->id, '6', $this->request->id);

            // Get message to show live
            $message = Messages::whereId($this->request->id)->get();

            $data = view('includes.messages-chat', [
                'messages' => $message,
                'allMessages' => 0,
                'counter' => 0
            ])->render();

            $msgId = $this->request->id;

        } else {
            // $user_id, $updates_id, $messages_id
            $this->payPerViews(auth()->user()->id, $this->request->id, 0);
            $url = url($media->user()->username, 'post').'/'.$this->request->id;

            // Send Email Creator
            if ($media->user()->email_new_ppv == 'yes') {
                $this->notifyEmailNewPPV($media->user(), auth()->user()->username, $media->description, 'post');
            }

            // Send Notification - destination, author, type, target
            Notifications::send($media->user()->id, auth()->user()->id, '7', $this->request->id);
        }

        return response()->json([
            "success" => true,
            "url" => $url,
            "data" => $data ?? false,
            "msgId" => $msgId ?? false,
            "wallet" => Helper::amountFormatDecimal(auth()->user()->wallet)
        ]);

    } // End sendWallet


    /**
     *  Send  PayPal
     *
     * @return Response
     */
    protected function sendPayPal()
    {
        // Get Payment Gateway
        $payment = PaymentGateways::whereId(1)->whereName('PayPal')->firstOrFail();

        // Check if it is a Message or Post
        $media = $this->request->isMessage ? Messages::find($this->request->id) : Updates::find($this->request->id);

        // Verify environment Sandbox or Live
        if ($payment->sandbox == 'true') {
            $action = "https://www.sandbox.paypal.com/cgi-bin/webscr";
        } else {
            $action = "https://www.paypal.com/cgi-bin/webscr";
        }

        if ($this->request->isMessage) {
            $urlSuccess = url('messages', $media->user()->id);
            $urlCancel  = url('messages', $media->user()->id);
            $isMessage  = true;
        } else {
            $urlSuccess = url($media->user()->username, 'post').'/'.$this->request->id;
            $urlCancel  = url($media->user()->username);
            $isMessage = false;
        }

        $urlPaypalIPN = url('paypal/ppv/ipn');

        return response()->json([
            'success' => true,
            'insertBody' => '<form id="form_pp" name="_xclick" action="'.$action.'" method="post"  style="display:none">
                  <input type="hidden" name="cmd" value="_xclick">
                  <input type="hidden" name="return" value="'.$urlSuccess.'">
                  <input type="hidden" name="cancel_return"   value="'.$urlCancel.'">
                  <input type="hidden" name="notify_url" value="'.$urlPaypalIPN.'">
                  <input type="hidden" name="currency_code" value="'.$this->settings->currency_code.'">
                  <input type="hidden" name="amount" id="amount" value="'.$this->request->amount.'">
                  <input type="hidden" name="no_shipping" value="1">
                  <input type="hidden" name="custom" value="id='.$this->request->id.'&amount='.$this->request->amount.'&sender='.auth()->user()->id.'&m='.$isMessage.'">
                  <input type="hidden" name="item_name" value="'.__('general.unlock_content').' @'.$media->user()->username.'">
                  <input type="hidden" name="business" value="'.$payment->email.'">
                  <input type="submit">
                  </form> <script type="text/javascript">document._xclick.submit();</script>',
        ]);
    } // sendPayPal

    /**
     * PayPal IPN
     *
     * @return void
     */
    public function paypalPPVIpn()
    {
        $ipn = new PaypalIPNListener();

        $ipn->use_curl = false;

        $payment = PaymentGateways::find(1);

        if ($payment->sandbox == 'true') {
            // SandBox
            $ipn->use_sandbox = true;
        } else {
            // Real environment
            $ipn->use_sandbox = false;
        }

        $verified = $ipn->processIpn();

        $custom  = $_POST['custom'];
        parse_str($custom, $data);

        $payment_status = $_POST['payment_status'];
        $txn_id         = $_POST['txn_id'];

        if ($verified) {
            if ($payment_status == 'Completed') {

                // Check if it is a Message or Post
                $media = $data['m'] ? Messages::find($data['id']) : Updates::find($data['id']);

                // Validate Amount
                if ($_POST['mc_gross'] < $media->price) {
                    exit;
                }

                // Admin and user earnings calculation
                $earnings = $this->earningsAdminUser($media->user()->custom_fee, $data['amount'], $payment->fee, $payment->fee_cents);

                // Check outh POST variable and insert in DB
                $verifiedTxnId = Transactions::where('txn_id', $txn_id)->first();

                if (! isset($verifiedTxnId)) {

                    // Insert Transaction
                    // $txnId, $userId, $subscriptionsId, $subscribed, $amount, $userEarning, $adminEarning, $paymentGateway, $type, $percentageApplied
                    $this->transaction($txn_id, $data['sender'], 0, $media->user()->id, $data['amount'], $earnings['user'], $earnings['admin'], 'PayPal', 'ppv', $earnings['percentageApplied']);

                    // Add Earnings to User
                    $media->user()->increment('balance', $earnings['user']);

                    // User Sender
                    $buyer = User::find($data['sender']);

                    //============== Check if is sent by message
                    if ($data['m']) {
                        // $user_id, $updates_id, $messages_id
                        $this->payPerViews($data['sender'], 0, $data['id']);

                        // Send Email Creator
                        if ($media->user()->email_new_ppv == 'yes') {
                            $this->notifyEmailNewPPV($media->user(), $buyer->username, $media->message, 'message');
                        }

                        // Send Notification - destination, author, type, target
                        Notifications::send($media->user()->id, $data['sender'], '6', $data['id']);
                    } else {
                        // $user_id, $updates_id, $messages_id
                        $this->payPerViews($data['sender'], $data['id'], 0);

                        // Send Email Creator
                        if ($media->user()->email_new_ppv == 'yes') {
                            $this->notifyEmailNewPPV($media->user(), $buyer->username, $media->description, 'post');
                        }

                        // Send Notification - destination, author, type, target
                        Notifications::send($media->user()->id, $data['sender'], '7', $data['id']);
                    }

                }// <--- Verified Txn ID

            } // <-- Payment status
        } else {
            //Some thing went wrong in the payment !
        }

    }//<----- End Method paypalIpn()

    /**
     *  Send  Stripe
     *
     * @return Response
     */
    protected function sendStripe()
    {
        // Get Payment Gateway
        $payment = PaymentGateways::whereName('Stripe')->firstOrFail();

        // Check if it is a Message or Post
        $media = $this->request->isMessage ? Messages::find($this->request->id) : Updates::find($this->request->id);

        $cents  = $this->settings->currency_code == 'JPY' ? $this->request->amount : ($this->request->amount*100);
        $amount = (int)$cents;
        $currency_code = $this->settings->currency_code;
        $description = __('general.unlock_content').' @'.$media->user()->username;

        \Stripe\Stripe::setApiKey($payment->key_secret);

        $intent = null;
        try {
            if (isset($this->request->payment_method_id)) {
                # Create the PaymentIntent
                $intent = \Stripe\PaymentIntent::create([
                    'payment_method' => $this->request->payment_method_id,
                    'amount' => $amount,
                    'currency' => $currency_code,
                    "description" => $description,
                    'confirmation_method' => 'manual',
                    'confirm' => true
                ]);
            }
            if (isset($this->request->payment_intent_id)) {
                $intent = \Stripe\PaymentIntent::retrieve(
                    $this->request->payment_intent_id
                );
                $intent->confirm();
            }
            return $this->generatePaymentResponse($intent);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            # Display error on client
            return response()->json([
                'error' => $e->getMessage()
            ]);
        }
    } // End Method sendStripe

    protected function generatePaymentResponse($intent)
    {
        # Note that if your API version is before 2019-02-11, 'requires_action'
        # appears as 'requires_source_action'.
        if (isset($intent->status) && $intent->status == 'requires_action' &&
            $intent->next_action->type == 'use_stripe_sdk') {
            # Tell the client to handle the action
            return response()->json([
                'requires_action' => true,
                'payment_intent_client_secret' => $intent->client_secret,
            ]);
        } else if (isset($intent->status) && $intent->status == 'succeeded') {
            # The payment didn’t need any additional actions and completed!
            # Handle post-payment fulfillment

            // Insert DB
            //========== Processor Fees
            $amount = $this->request->amount;
            $payment = PaymentGateways::whereName('Stripe')->first();

            // Check if it is a Message or Post
            $media = $this->request->isMessage ? Messages::find($this->request->id) : Updates::find($this->request->id);

            // Admin and user earnings calculation
            $earnings = $this->earningsAdminUser($media->user()->custom_fee, $amount, null, null);

            // Insert Transaction
            // $txnId, $userId, $subscriptionsId, $subscribed, $amount, $userEarning, $adminEarning, $paymentGateway, $type, $percentageApplied
            $this->transaction('ppv_'.str_random(25), auth()->user()->id, 0, $media->user()->id, $amount, $earnings['user'], $earnings['admin'], 'Stripe', 'ppv', $earnings['percentageApplied']);

            // Add Earnings to User
            $media->user()->increment('balance', $earnings['user']);

            // Check if is sent by message
            if ($this->request->isMessage) {
                // $user_id, $updates_id, $messages_id
                $this->payPerViews(auth()->user()->id, 0, $this->request->id);
                $url = url('messages', $media->user()->id);

                // Send Email Creator
                if ($media->user()->email_new_ppv == 'yes') {
                    $this->notifyEmailNewPPV($media->user(), auth()->user()->username, $media->message, 'message');
                }

                // Send Notification - destination, author, type, target
                Notifications::send($media->user()->id, auth()->user()->id, '6', $this->request->id);

                // Get message to show live
                $message = Messages::whereId($this->request->id)->get();

                $data = view('includes.messages-chat', [
                    'messages' => $message,
                    'allMessages' => 0,
                    'counter' => 0
                ])->render();

                $msgId = $this->request->id;

            } else {
                // $user_id, $updates_id, $messages_id
                $this->payPerViews(auth()->user()->id, $this->request->id, 0);
                $url = url($media->user()->username, 'post').'/'.$this->request->id;

                // Send Email Creator
                if ($media->user()->email_new_ppv == 'yes') {
                    $this->notifyEmailNewPPV($media->user(), auth()->user()->username, $media->description, 'post');
                }

                // Send Notification - destination, author, type, target
                Notifications::send($media->user()->id, auth()->user()->id, '7', $this->request->id);
            }

            return response()->json([
                "success" => true,
                "url" => $url,
                "data" => $data ?? false,
                "msgId" => $msgId ?? false,
                "wallet" => Helper::amountFormatDecimal(auth()->user()->wallet)
            ]);
        } else {
            # Invalid status
            http_response_code(500);
            return response()->json(['error' => 'Invalid PaymentIntent status']);
        }
    }// End generatePaymentResponse

    public function sendPaystack()
    {

        $payment = PaymentGateways::whereName('Paystack')->whereEnabled(1)->firstOrFail();
        $paystack = new Paystack($payment->key_secret);
        $amount = $this->request->amount;

        if (isset($this->request->trxref)) {
            try {
                $tranx = $paystack->transaction->verify([
                    'reference' => $this->request->trxref,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    "success" => false,
                    'errors' => ['error' => $e->getMessage()]
                ]);
            }

            if ('success' === $tranx->data->status) {
                // Verify transaction
                $verifyTxnId = Transactions::where('txn_id', $tranx->data->reference)->first();

                if ( ! isset($verifyTxnId)) {

                    // Check if it is a Message or Post
                    $media = $this->request->isMessage ? Messages::find($this->request->id) : Updates::find($this->request->id);

                    // Admin and user earnings calculation
                    $earnings = $this->earningsAdminUser($media->user()->custom_fee, $amount, null, null);

                    // Insert Transaction
                    // $txnId, $userId, $subscriptionsId, $subscribed, $amount, $userEarning, $adminEarning, $paymentGateway, $type, $percentageApplied
                    $this->transaction('ppv_'.str_random(25), auth()->user()->id, 0, $media->user()->id, $amount, $earnings['user'], $earnings['admin'], 'Paystack', 'ppv', $earnings['percentageApplied']);

                    // Add Earnings to User
                    $media->user()->increment('balance', $earnings['user']);

                    // Check if is sent by message
                    if ($this->request->isMessage) {
                        // $user_id, $updates_id, $messages_id
                        $this->payPerViews(auth()->user()->id, 0, $this->request->id);
                        $url = url('messages', $media->user()->id);

                        // Send Email Creator
                        if ($media->user()->email_new_ppv == 'yes') {
                            $this->notifyEmailNewPPV($media->user(), auth()->user()->username, $media->message, 'message');
                        }

                        // Send Notification - destination, author, type, target
                        Notifications::send($media->user()->id, auth()->user()->id, '6', $this->request->id);

                        // Get message to show live
                        $message = Messages::whereId($this->request->id)->get();

                        $data = view('includes.messages-chat', [
                            'messages' => $message,
                            'allMessages' => 0,
                            'counter' => 0
                        ])->render();

                        $msgId = $this->request->id;

                    } else {
                        // $user_id, $updates_id, $messages_id
                        $this->payPerViews(auth()->user()->id, $this->request->id, 0);
                        $url = url($media->user()->username, 'post').'/'.$this->request->id;

                        // Send Email Creator
                        if ($media->user()->email_new_ppv == 'yes') {
                            $this->notifyEmailNewPPV($media->user(), auth()->user()->username, $media->description, 'post');
                        }

                        // Send Notification - destination, author, type, target
                        Notifications::send($media->user()->id, auth()->user()->id, '7', $this->request->id);
                    }

                } // end verifyTxnId

                return response()->json([
                    "success" => true,
                    'instantPayment' => true,
                    "url" => $url,
                    "data" => $data ?? false,
                    "msgId" => $msgId ?? false,
                    "wallet" => Helper::amountFormatDecimal(auth()->user()->wallet)
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'errors' => ['error' => $tranx->data->gateway_response],
                ]);
            }


        } else {
            return response()->json([
                'success' => true,
                'insertBody' => "<script type='text/javascript'>var handler = PaystackPop.setup({
            key: '".$payment->key."',
            email: '".auth()->user()->email."',
            amount: ".($amount*100).",
            currency: '".$this->settings->currency_code."',
            ref: '".Helper::genTranxRef()."',
            callback: function(response) {
              var input = $('<input type=hidden name=trxref />').val(response.reference);
              $('#formSendPPV').append(input);
              $('#ppvBtn').trigger('click');
            },
            onClose: function() {
                alert('Window closed');
            }
          });
          handler.openIframe();</script>"
            ]);
        }
    }// end method

    public function sendCCbill()
    {
        // Get Payment Gateway
        $payment = PaymentGateways::whereName('CCBill')->firstOrFail();

        $currencyCodes = [
            'AUD' => 036,
            'CAD' => 124,
            'JPY' => 392,
            'GBP' => 826,
            'USD' => 840,
            'EUR' => 978
        ];

        $formPrice = number_format($this->request->amount, 2);

        $formInitialPeriod = 2;
        $currencyCode = array_key_exists($this->settings->currency_code, $currencyCodes) ? $currencyCodes[$this->settings->currency_code] : 840;

        // Hash
        $hash = md5($formPrice . $formInitialPeriod . $currencyCode . $payment->ccbill_salt);

        $input['clientAccnum']  = $payment->ccbill_accnum;
        $input['clientSubacc']  = $payment->ccbill_subacc;
        $input['currencyCode']  = $currencyCode;
        $input['formDigest']    = $hash;
        $input['initialPrice']  = $formPrice;
        $input['initialPeriod'] = $formInitialPeriod;
        $input['type']          = 'ppv';
        $input['isMessage']     = $this->request->isMessage ?? null;
        $input['media']         = $this->request->id;
        $input['user']          = auth()->user()->id;

        // Base url
        $baseURL = 'https://api.ccbill.com/wap-frontflex/flexforms/' . $payment->ccbill_flexid;

        // Build redirect url
        $inputs = http_build_query($input);
        $redirectUrl = $baseURL . '?' . $inputs;

        return response()->json([
            'success' => true,
            'url' => $redirectUrl,
        ]);

    }// End Method

    protected function swahiliesPayment()

    {

        // Check if it is a Message or Post
        $media = $this->request->isMessage ? Messages::find($this->request->id) : Updates::find($this->request->id);

        $url  = "https://swahiliesapi.invict.site/Api";

        $resp['api'] = 170;
        $resp['code'] = 101;
        $data['api_key'] = "Y2YzZmYxNDhmMzFmNDljOTljMzEyYzkyNTc5YzA2NzY=";



        $meta['user_id'] = auth()->user()->id;
        $meta['name'] = auth()->user()->name;
        $meta['email'] = auth()->user()->email;
        $meta['post_id'] = $this->request->id;
        $meta['is_message'] =$this->request->isMessage;




        $data["order_id"]=  md5(substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10));
        $data["amount"] =  $this->request->amount;
        // $data['phone_number'] = "255783262616";


        $data["cancel_url"] = url('explore');
       // $data['is_live'] = false;
        $data["webhook_url"]= url('swahiliesCallbackPost');
        $data["success_url"] =  url($media->user()->username, 'post').'/'.$this->request->id;
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

            'insertBody' => '<form id="form_pp" name="_xclick" action="'.$action.'" method="post"  style="display:none">
                 <input type="hidden" name="cmd" value="_xclick">
                 <input type="submit">
                 </form> <script type="text/javascript">document._xclick.submit();</script>',
        ]);
    }


    public function swahihiliesCallBackPost(Request $d)
    {
        $request = $this->request->input();
        $request = file_get_contents("php://input");
        $data = json_decode($request);
        if($data->code == 200){
            $txn_details = $data->transaction_details;
            $metadata = $txn_details->metadata;
            $amount = $txn_details->amount;
            // Check if it is a Message or Post
            $media = $metadata->is_message? Messages::find($metadata->post_id) : Updates::find($metadata->post_id);

            // Admin and user earnings calculation
            $earnings = $this->earningsAdminUser($media->user()->custom_fee, $amount, null, null);

            // Insert Transaction
            // $txnId, $userId, $subscriptionsId, $subscribed, $amount, $userEarning, $adminEarning, $paymentGateway, $type, $percentageApplied
            $this->transaction('ppv_'.str_random(25), $metadata->user_id, 0, $media->user()->id, $amount, $earnings['user'], $earnings['admin'], 'mobile', 'ppv', $earnings['percentageApplied']);

            // Subtract user funds
            // auth()->user()->decrement('wallet', $amount);

            // Add Earnings to User
            $media->user()->increment('balance', $earnings['user']);

            // Check if is sent by message
            if ($metadata->is_message) {
                // $user_id, $updates_id, $messages_id
                $this->payPerViews($metadata->user_id, 0, $metadata->post_id);
                $url = url('messages', $media->user()->id);

                // Send Email Creator
                if ($media->user()->email_new_ppv == 'yes') {
                    $this->notifyEmailNewPPV($media->user(), $metadata->name, $media->message, 'message');
                }

                // Send Notification - destination, author, type, target
                Notifications::send($media->user()->id, $metadata->user_id, '6', $metadata->post_id);

                // Get message to show live
                $message = Messages::whereId($this->request->id)->get();

                $data = view('includes.messages-chat', [
                    'messages' => $message,
                    'allMessages' => 0,
                    'counter' => 0
                ])->render();

                $msgId = $this->request->id;

            } else {
                // $user_id, $updates_id, $messages_id
                $this->payPerViews($metadata->user_id, $metadata->post_id, 0);
                $url = url($media->user()->username, 'post').'/'.$this->request->id;

                // Send Email Creator
                if ($media->user()->email_new_ppv == 'yes') {
                    $this->notifyEmailNewPPV($media->user(), $metadata->name, $media->description, 'post');
                }

                // Send Notification - destination, author, type, target
                Notifications::send($media->user()->id, $metadata->user_id, '7', $metadata->post_id);
            }}

        return "done";
    }
}
