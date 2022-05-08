<?php

namespace App\Http\Controllers\Traits;

use DB;
use Mail;
use App\Helper;
use App\Models\User;
use App\Models\AdminSettings;
use App\Models\Subscriptions;
use App\Models\Notifications;
use App\Models\Comments;
use App\Models\Like;
use App\Models\Updates;
use App\Models\Reports;
use App\Models\VerificationRequests;
use App\Models\Referrals;
use App\Models\ReferralTransactions;
use App\Models\PaymentGateways;
use App\Models\Conversations;
use App\Models\Messages;
use App\Models\Bookmarks;
use App\Models\Transactions;
use App\Models\PayPerViews;
use App\Models\Deposits;
use App\Notifications\TipReceived;
use App\Notifications\PayPerViewReceived;
use App\Models\TwoFactorCodes;
use App\Notifications\SendTwoFactorCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

trait Functions {

	public function __construct(AdminSettings $settings) {
    $this->settings = $settings::first();
  }

	// Users on Card Explore
	public function userExplore()
	{
		return User::where('status','active')
			->where('id', '<>', auth()->user()->id ?? 0)
				->whereVerifiedId('yes')
				->where('id', '<>', $this->settings->hide_admin_profile == 'on' ? 1 : 0)
				->where('price', '<>', 0.00)
				->whereFreeSubscription('no')
				->whereHideProfile('no')
				->where('blocked_countries', 'NOT LIKE', '%'.Helper::userCountry().'%')
			->orWhere('status','active')
				->where('id', '<>', auth()->user()->id ?? 0)
					->whereVerifiedId('yes')
					->where('id', '<>', $this->settings->hide_admin_profile == 'on' ? 1 : 0)
					->whereFreeSubscription('yes')
					->whereHideProfile('no')
					->where('blocked_countries', 'NOT LIKE', '%'.Helper::userCountry().'%')
				->inRandomOrder()
				->paginate(3);
	}// End Method

	// CCBill Form
	public function ccbillForm($price, $userAuth, $type, $creator = null, $isMessage = null)
	{
		// Get Payment Gateway
		$payment = PaymentGateways::whereName('CCBill')->firstOrFail();

		if ($creator) {
		$user  = User::whereVerifiedId('yes')->whereId($creator)->firstOrFail();
		}

		$currencyCodes = [
			'AUD' => 036,
			'CAD' => 124,
			'JPY' => 392,
			'GBP' => 826,
			'USD' => 840,
			'EUR' => 978
		];

		if ($type == 'wallet') {
			if ($this->settings->currency_code == 'JPY') {
				$formPrice = round($price + ($price * $payment->fee / 100) + $payment->fee_cents, 2, '.', ',');
			} else {
				$formPrice = number_format($price + ($price * $payment->fee / 100) + $payment->fee_cents, 2, '.', ',');
			}
		} else {
			$formPrice = number_format($price, 2);
		}

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
		$input['type']          = $type;
		$input['isMessage']     = $isMessage;
		$input['creator']       = $user->id ?? null;
		$input['user']          = $userAuth;
		$input['amountFixed']   = $type == 'wallet' ? $price : null;

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

	// Admin and user earnings calculation
	public function earningsAdminUser($userCustomFee, $amount, $paymentFee, $paymentFeeCents)
	{
		$settings = AdminSettings::first();

		$feeCommission = $userCustomFee == 0 ? $settings->fee_commission : $userCustomFee;

		if (isset($paymentFee)) {
			$processorFees = $amount - ($amount * $paymentFee/100) - $paymentFeeCents;

			// Earnings Net User
			$earningNetUser = $processorFees - ($processorFees * $feeCommission/100);
			// Earnings Net Admin
			$earningNetAdmin = $processorFees - $earningNetUser;
		} else {
			// Earnings Net User
      $earningNetUser = $amount - ($amount * $feeCommission/100);

      // Earnings Net Admin
      $earningNetAdmin = ($amount - $earningNetUser);
		}

		if (isset($paymentFee)) {
			$paymentFees =  $paymentFeeCents == 0.00 ? $paymentFee.'% + ' : $paymentFee.'%'.' + '.$paymentFeeCents.' + ';
		} else {
			$paymentFees = null;
		}

		// Percentage applied
		$percentageApplied = $paymentFees.$feeCommission.'%';


		if ($settings->currency_code == 'JPY') {
			$userEarning = floor($earningNetUser);
			$adminEarning = floor($earningNetAdmin);
		} else {
			$userEarning = number_format($earningNetUser, 2, '.', '');
			$adminEarning = number_format($earningNetAdmin, 2, '.', '');
		}

		return [
			'user' => $userEarning,
			'admin' => $adminEarning,
			'percentageApplied' => $percentageApplied
		];

	}// End Method

	// Insert Transaction
	public function transaction(
		$txnId,
		$userId,
		$subscriptionsId,
		$subscribed,
		$amount,
		$userEarning,
		$adminEarning,
		$paymentGateway,
		$type,
		$percentageApplied,
		$approved = '1'
		) {
				$settings = AdminSettings::first();

				// Check Referred
				if ($settings->referral_system == 'on') {

					// Check for referred
					$referred = Referrals::whereUserId($userId)->first();

							if ($referred) {

								// Check if the user who referred exists
								$referredBy = User::find($referred->referred_by);

								if ($referredBy) {

									// Check numbers of transactions
									$transactions = ReferralTransactions::whereUserId($userId)->count();

									if ($settings->referral_transaction_limit == 'unlimited'
											|| $transactions < $settings->referral_transaction_limit
										) {

											$adminEarningFinal = $adminEarning - ($adminEarning * $settings->percentage_referred/100);

								      $earningNetUser = ($adminEarning - $adminEarningFinal);
								      $adminEarning   = ($adminEarning - $earningNetUser);

											if ($settings->currency_code == 'JPY') {
												$earningNetUser = floor($earningNetUser);
												$adminEarning   = floor($adminEarning);
											} else {
												$earningNetUser = number_format($earningNetUser, 2, '.', '');
												$adminEarning   = number_format($adminEarning, 2, '.', '');
											}

											// Insert User Earning
											$newTransaction = new ReferralTransactions();
											$newTransaction->referrals_id = $referred->id;
											$newTransaction->user_id = $referred->user_id;
											$newTransaction->referred_by = $referred->referred_by;
											$newTransaction->earnings = $earningNetUser;
											$newTransaction->type = $type;
											$newTransaction->save();

											// Add Earnings to User
							        $referred->referredBy()->increment('balance', $earningNetUser);

											// Notify to user - destination, author, type, target
											Notifications::send($referred->referred_by, $referred->referred_by, 11, $referred->referred_by);

											$referredCommission = true;
									}

								}//=== $referredBy

							}// $referred

				}// referral_system On

					// Insert Transaction
					$txn = new Transactions();
					$txn->txn_id  = $txnId;
					$txn->user_id = $userId;
					$txn->subscriptions_id = $subscriptionsId;
					$txn->subscribed = $subscribed;
					$txn->amount   = $amount;
					$txn->earning_net_user  =  $userEarning;
					$txn->earning_net_admin = $adminEarning;
					$txn->payment_gateway = $paymentGateway;
					$txn->type = $type;
					$txn->percentage_applied = $percentageApplied;
					$txn->approved = $approved;
					$txn->referred_commission = $referredCommission ?? false;
					$txn->save();

				}// End Method

	// Insert PayPerViews
	public function payPerViews($user_id, $updates_id, $messages_id)
	{
		$sql = new PayPerViews();
		$sql->user_id = $user_id;
		$sql->updates_id = $updates_id;
		$sql->messages_id = $messages_id;
		$sql->save();

	}// End Method

	// Send notification via Email to creator that you have received a tip
	protected function notifyEmailNewTip($user, $tipper, $amount)
	{
		$data = [
				'tipper' => $tipper,
				'amount' => $amount
			];

			try {
				$user->notify(new TipReceived($data));
			} catch (\Exception $e) {
				\Log::info($e->getMessage());
			}
	} // End Method

	// Send notification via Email to creator that you have received a PPV
	protected function notifyEmailNewPPV($user, $buyer, $media, $type)
	{
		$data = [
				'buyer' => $buyer,
				'content' => $media,
				'type' => $type
			];

			try {
				$user->notify(new PayPerViewReceived($data));
			} catch (\Exception $e) {
				\Log::info($e->getMessage());
			}
	} // End Method

	// Insert Deposit (Add funds user wallet)
	public function deposit($userId, $txnId, $amount, $paymentGateway, $screenshotTransfer = '')
	{
		$payment = PaymentGateways::whereName($paymentGateway)->firstOrFail();
		$paymentFee = $payment->fee;
		$paymentFeeCents = $payment->fee_cents;

		// Percentage applied
		$percentageApplied =  $paymentFeeCents == 0.00 ? $paymentFee.'%' : $paymentFee.'%'.' + '.$paymentFeeCents;

		// Percentage applied amount
		$transactionFeeAmount = number_format($amount + ($amount * $paymentFee / 100) + $paymentFeeCents, 2, '.', ',');
		$transactionFeeAmount1 = ($amount + ($amount * $paymentFee / 100) + $paymentFeeCents);
		$transactionFee = ($transactionFeeAmount1 - $amount);
		$sql = new Deposits();
		$sql->user_id = $userId;
		$sql->txn_id = $txnId;
		$sql->amount = $amount;
		$sql->payment_gateway = $paymentGateway;
		$sql->status = $paymentGateway == 'Bank Transfer' ? 'pending' : 'active';
    $sql->screenshot_transfer = $screenshotTransfer;
		$sql->percentage_applied = $percentageApplied;
		$sql->transaction_fee = $transactionFee;
		$sql->save();

	}// End Method

	public function generateTwofaCode($user)
  {
    $code = rand(1000, 9999);

    // Delete old session user id
    session()->forget('user:id');

    // Create session user
    session()->put('user:id', $user->id);

        TwoFactorCodes::updateOrCreate([
          'user_id' => $user->id,
          'code' => $code
        ]);

        try {
            $data = ['code' => $code];

            $user->notify(new SendTwoFactorCode($data));

        } catch (Exception $e) {
            \Log::info("Error: ". $e->getMessage());
        }
  }// End method

}// End Class
