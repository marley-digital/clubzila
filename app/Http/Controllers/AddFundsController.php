<?php

namespace App\Http\Controllers;

use Mail;
use App\Helper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\AdminSettings;
use App\Models\Notifications;
use Fahim\PaypalIPN\PaypalIPNListener;
use Illuminate\Support\Facades\Validator;
use App\Models\PaymentGateways;
use App\Models\Transactions;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AdminDepositPending;
use App\Models\Conversations;
use App\Models\Messages;
use App\Models\Deposits;
use Yabacon\Paystack;


class AddFundsController extends Controller
{
  use Traits\Functions;

  public function __construct(Request $request, AdminSettings $settings) {
    $this->request = $request;
    $this->settings = $settings::first();
  }

  /**
	 *  Wallet View
	 *
	 * @return Response
	 */
  public function wallet()
  {
    if ($this->settings->disable_wallet == 'on') {
      abort(404);
    }
    $data = Deposits::whereUserId(auth()->user()->id)->orderBy('id', 'desc')->paginate(20);

    $equivalent_money = Helper::equivalentMoney($this->settings->wallet_format);

    return view('users.wallet', ['data' => $data, 'equivalent_money' => $equivalent_money]);
  }

  /**
	 *  Add Funds Request
	 *
	 * @return Response
	 */
  public function send() {

    // Validate Payment Gateway
    Validator::extend('check_payment_gateway', function($attribute, $value, $parameters) {
      return PaymentGateways::find($value);
    });

    // Currency Position
    if ($this->settings->currency_position == 'right') {
      $currencyPosition =  2;
    } else {
      $currencyPosition =  null;
    }

    $messages = array (
      'amount.min' => trans('general.amount_minimum'.$currencyPosition, ['symbol' => $this->settings->currency_symbol, 'code' => $this->settings->currency_code]),
      'amount.max' => trans('general.amount_maximum'.$currencyPosition, ['symbol' => $this->settings->currency_symbol, 'code' => $this->settings->currency_code]),
      'payment_gateway.check_payment_gateway' => trans('general.payments_error'),
      'image.required_if' => trans('general.please_select_image'),
  );

  //<---- Validation
  $validator = Validator::make($this->request->all(), [
      'amount' => 'required|integer|min:'.$this->settings->min_deposits_amount.'|max:'.$this->settings->max_deposits_amount,
      'payment_gateway' => 'required|check_payment_gateway',
      'image' => 'required_if:payment_gateway,==,3|mimes:jpg,gif,png,jpe,jpeg|max:'.$this->settings->file_size_allowed_verify_account.'',
      ], $messages);

    if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->getMessageBag()->toArray(),
            ]);
        }

    switch ($this->request->payment_gateway) {
      case 1:
        return $this->sendPayPal();
        break;

      case 2:
        return $this->sendStripe();
        break;

      case 3:
        return $this->sendBankTransfer();
        break;

      case 4:
        return $this->ccbillForm($this->request->amount, auth()->user()->id, 'wallet');
        break;

      case 5:
      return $this->sendPaystack();
        break;

        case 6:
        return $this->sendCoinpayments();
          break;

        case 7:
            return $this->swahiliesPayment();
            break;
    }

    return response()->json([
      'success' => true,
      'insertBody' => '<i></i>'
    ]);

  } // End method Send

  /**
	 *  Add funds PayPal
	 *
	 * @return Response
	 */
  protected function sendPayPal()
  {
    // Get Payment Gateway
    $payment = PaymentGateways::whereId(1)->whereName('PayPal')->firstOrFail();

    // Verify environment Sandbox or Live
    if ($payment->sandbox == 'true') {
      $action = "https://www.sandbox.paypal.com/cgi-bin/webscr";
      } else {
      $action = "https://www.paypal.com/cgi-bin/webscr";
      }

      $urlSuccess = route('paymentProcess');
      $urlCancel   = url('my/wallet');

      $urlPaypalIPN = url('paypal/add/funds/ipn');

      $feePayPal   = $payment->fee;
      $centsPayPal =  $payment->fee_cents;

      $amountFixed = number_format($this->request->amount + ($this->request->amount * $feePayPal / 100) + $centsPayPal, 2, '.', ',');

      return response()->json([
                  'success' => true,
                  'insertBody' => '<form id="form_pp" name="_xclick" action="'.$action.'" method="post"  style="display:none">
                  <input type="hidden" name="cmd" value="_xclick">
                  <input type="hidden" name="return" value="'.$urlSuccess.'">
                  <input type="hidden" name="cancel_return"   value="'.$urlCancel.'">
                  <input type="hidden" name="notify_url" value="'.$urlPaypalIPN.'">
                  <input type="hidden" name="currency_code" value="'.$this->settings->currency_code.'">
                  <input type="hidden" name="amount" id="amount" value="'.$amountFixed.'">
                  <input type="hidden" name="no_shipping" value="1">
                  <input type="hidden" name="custom" value="id='.auth()->user()->id.'&amount='.$this->request->amount.'">
                  <input type="hidden" name="item_name" value="'.__('general.add_funds').' @'.auth()->user()->username.'">
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
      public function paypalIpn() {

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

        // Validate Amount
        if ($_POST['mc_gross'] < $this->settings->min_deposits_amount) {
          exit;
        }

  	    if ($verified) {
  				if ($payment_status == 'Completed') {

  	          // Check outh POST variable and insert in DB
  						$verifiedTxnId = Deposits::where('txn_id', $txn_id)->first();

  			if (! isset($verifiedTxnId)) {

          // Insert Deposit ($userID, $txnId, $amount, $paymentGateway)
          $this->deposit($data['id'], $txn_id, $data['amount'], 'PayPal');

  				//Add Funds to User
  				User::find($data['id'])->increment('wallet', $data['amount']);

  			}// <--- Verified Txn ID

  	      } // <-- Payment status
  	    } else {
  	    	//Some thing went wrong in the payment !
  	    }

      }//<----- End Method paypalIpn()

  /**
	 *  Add funds Stripe
	 *
	 * @return Response
	 */
  protected function sendStripe()
  {
        // Get Payment Gateway
        $payment = PaymentGateways::whereName('Stripe')->firstOrFail();

        $feeStripe   = $payment->fee;
      	$centsStripe =  $payment->fee_cents;

        if ($this->settings->currency_code == 'JPY') {
          $amountFixed = round($this->request->amount + ($this->request->amount * $feeStripe / 100) + $centsStripe);
        } else {
          $amountFixed = number_format($this->request->amount + ($this->request->amount * $feeStripe / 100) + $centsStripe, 2, '.', ',');
        }

      	$amountGross = ($this->request->amount);
      	$amount   = $this->settings->currency_code == 'JPY' ? $amountFixed : ($amountFixed*100);

      	$currency_code = $this->settings->currency_code;
      	$description = __('general.add_funds').' @'.auth()->user()->username;

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

  protected function generatePaymentResponse($intent) {
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

      // Insert Deposit ($userID, $txnId, $amount, $paymentGateway)
      $this->deposit(auth()->user()->id, $intent->id, $this->request->amount, 'Stripe');

      // Add Funds to User
      User::find(auth()->user()->id)->increment('wallet', $this->request->amount);

      return response()->json([
        "success" => true
      ]);
    } else {
      # Invalid status
      http_response_code(500);
      return response()->json(['error' => 'Invalid PaymentIntent status']);
    }
  }// End generatePaymentResponse

  public function sendBankTransfer()
  {
    // PATHS
    $path = config('path.admin');

    if ($this->request->hasFile('image')) {

    $extension = $this->request->file('image')->getClientOriginalExtension();
    $fileImage = 'bt_'.strtolower(auth()->user()->id.time().str_random(40).'.'.$extension);

    $this->request->file('image')->storePubliclyAs($path, $fileImage);

   }//<====== End HasFile

   // Insert Deposit ($userID, $txnId, $amount, $paymentGateway)
   $this->deposit(auth()->user()->id, 'bt_'.str_random(25), $this->request->amount, 'Bank Transfer', $fileImage);

    // Notify Admin via Email
    try {
      Notification::route('mail' , $this->settings->email_admin)
          ->notify(new AdminDepositPending($sql));
    } catch (\Exception $e) {
      \Log::info($e->getMessage());
    }

    return response()->json([
      "success" => true,
      "status" => 'pending',
      'status_info' => __('general.pending_deposit')
    ]);

  } // End method sendBankTransfer

  public function sendPaystack()
  {

    $payment = PaymentGateways::whereName('Paystack')->whereEnabled(1)->firstOrFail();
    $paystack = new Paystack($payment->key_secret);

    $fee   = $payment->fee;
    $cents = $payment->fee_cents;

    $amount = number_format($this->request->amount + ($this->request->amount * $fee / 100) + $cents, 2, '.', ',');

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
        // Verify Deposit
        $verifyTxnId = Deposits::where('txn_id', $tranx->data->reference)->first();

        if (! isset($verifyTxnId)) {

          // Insert Deposit ($userID, $txnId, $amount, $paymentGateway)
          $this->deposit(auth()->user()->id, $tranx->data->reference, $this->request->amount, 'Paystack');

          // Add Funds to User
          User::find(auth()->user()->id)->increment('wallet', $this->request->amount);

        return response()->json([
          "success" => true,
          'instantPayment' => true
        ]);
      }// verifyTxnId
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
            $('#formAddFunds').append(input);
            $('#addFundsBtn').trigger('click');
          },
          onClose: function() {
              alert('Window closed');
          }
        });
        handler.openIframe();</script>"
    ]);
   }
  }// end method

  /**
	 *  Add funds CoinPaments
	 *
	 * @return Response
	 */
  protected function sendCoinpayments()
  {
    // Get Payment Gateway
    $payment = PaymentGateways::whereName('Coinpayments')->firstOrFail();

      $urlSuccess = route('paymentProcess');
      $urlCancel   = url('my/wallet');

      $urlIPN = route('coinpaymentsIPN', ['user' => auth()->user()->id]);

      $fee   = $payment->fee;
      $cents =  $payment->fee_cents;

      $amountFixed = number_format($this->request->amount + ($this->request->amount * $fee / 100) + $cents, 2, '.', ',');

      return response()->json([
                  'success' => true,
                  'insertBody' => '<form name="_click" action="https://www.coinpayments.net/index.php" method="post"  style="display:none">
                  <input type="hidden" name="cmd" value="_pay">
                  <input type="hidden" name="reset" value="1"/>
                  <input type="hidden" name="merchant" value="'.$payment->key.'">
                  <input type="hidden" name="success_url" value="'.$urlSuccess.'">
                  <input type="hidden" name="cancel_url"   value="'.$urlCancel.'">
                  <input type="hidden" name="ipn_url" value="'.$urlIPN.'">
                  <input type="hidden" name="currency" value="'.$this->settings->currency_code.'">
                  <input type="hidden" name="amountf" value="'.$amountFixed.'">
                  <input type="hidden" name="want_shipping" value="0">
                  <input type="hidden" name="item_name" value="'.__('general.add_funds').' @'.auth()->user()->username.'">
                  <input type="hidden" name="email" value="'.auth()->user()->email.'">
                  <input type="hidden" name="first_name" value="'.auth()->user()->firstname.'">
                  <input type="hidden" name="last_name" value="'.auth()->user()->lastname.'">
                  <input type="submit">
                  </form> <script type="text/javascript">document._click.submit();</script>',
              ]);
      }



      // sendCoinpayments


    protected function swahiliesPayment()

    {
        $url  = "https://swahiliesapi.invict.site/Api";

      $resp['api'] = 170;
      $resp['code'] = 101;
      $data['api_key'] = "Y2YzZmYxNDhmMzFmNDljOTljMzEyYzkyNTc5YzA2NzY=";



     $meta['user_id'] = auth()->user()->id;
     $meta['name'] = auth()->user()->name;
     $meta['email'] = auth()->user()->email;




        $data["order_id"]=  md5(substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10));
        $data["amount"] =  $this->request->amount;

        $data["phone_number"]  = "255746480986";
        $data["cancel_url"] = url('my/wallet');
        //$data['is_live'] = false;
        $data["webhook_url"]= url('swahiliesCallback');
        $data["success_url"] =  route('paymentProcess');
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


    // CoinPaments IPN
      public function coinPaymentsIPN(Request $request)
      {
        // Get Payment Gateway
        $payment = PaymentGateways::whereName('Coinpayments')->firstOrFail();

        $merchantId = $payment->key;
        $ipnSecret = $payment->key_secret;

        $currency = $this->settings->currency_code;

        // Find user
        $user = User::findOrFail($request->user);

        // Validations...
    		if (! isset($_POST['ipn_mode']) || $_POST['ipn_mode'] != 'hmac') {
    			exit;
    		}

        if (! isset($_SERVER['HTTP_HMAC']) || empty($_SERVER['HTTP_HMAC'])) {
    			exit;
    		}

    		$getRequest = file_get_contents('php://input');

    		if ($getRequest === FALSE || empty($getRequest)) {
    			exit;
    		}

    		if (! isset($_POST['merchant']) || $_POST['merchant'] != trim($merchantId)) {
    			exit;
    		}

    		$hmac = hash_hmac("sha512", $getRequest, trim($ipnSecret));
    		if (!hash_equals($hmac, $_SERVER['HTTP_HMAC'])) {
    			exit;
    		}

        // Variables
        $ipn_type = $_POST['ipn_type'];
    		$txn_id = $_POST['txn_id'];
    		$item_name = $_POST['item_name'];
    		$amount1 = floatval($_POST['amount1']);
    		$amount2 = floatval($_POST['amount2']);
    		$currency1 = $_POST['currency1'];
    		$currency2 = $_POST['currency2'];
    		$status = intval($_POST['status']);

        // Check Button payment
        if ($ipn_type != 'button') {
    			exit;
    		}

        // Check currency
        if ($currency1 != $currency) {
    			exit;
    		}

        if ($status >= 100 || $status == 2) {
          try {

            // Insert Deposit ($userID, $txnId, $amount, $paymentGateway)
            $this->deposit($user->id, $txn_id, $amount1, 'Coinpayments');

            // Add Funds to User
            $user->increment('wallet', $amount1);

          } catch (\Exception $e) {
            Log::info($e->getMessage());
          }
        } // status >= 100

      } // coinPaymentsIPN

      // Return Success Page Payment in Process
      public function paymentProcess()
      {
          return redirect('my/wallet')->with(['payment_process' => true]);
      }




    public function swahihiliesCallBack(Request $d)
    {
         $request = $this->request->input();
        $request = file_get_contents("php://input");
        $data = json_decode($request);
        if($data->code == 200){
             $txn_details = $data->transaction_details;
             $metadata = $txn_details->metadata;
        User::find($metadata->user_id)->increment('wallet', $txn_details->amount);}

         return "done";
    }





}
