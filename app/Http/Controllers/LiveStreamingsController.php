<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LiveStreamings;
use App\Models\LiveOnlineUsers;
use App\Models\LiveComments;
use App\Models\LiveLikes;
use App\Models\AdminSettings;
use App\Events\LiveBroadcasting;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Helper;

class LiveStreamingsController extends Controller
{
  use Traits\Functions;

  public function __construct(Request $request, AdminSettings $settings) {
    $this->request = $request;
    $this->settings = $settings::first();
    $this->middleware('auth');
  }

  // Create live Stream
  public function create()
  {
    // Currency Position
    if ($this->settings->currency_position == 'right') {
      $currencyPosition =  2;
    } else {
      $currencyPosition =  null;
    }

    $messages = [
      'price.min' => trans('general.amount_minimum'.$currencyPosition, ['symbol' => $this->settings->currency_symbol, 'code' => $this->settings->currency_code]),
      'price.max' => trans('general.amount_maximum'.$currencyPosition, ['symbol' => $this->settings->currency_symbol, 'code' => $this->settings->currency_code]),
  ];

    $validator = Validator::make($this->request->all(), [
        'name' => 'required|max:50',
        'price' => 'required|integer|min:'.$this->settings->live_streaming_minimum_price.'|max:'.$this->settings->live_streaming_max_price,
    ], $messages);

    if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->getMessageBag()->toArray(),
            ]);
        }

        // Create Live Stream
        $live          = new LiveStreamings();
        $live->user_id = auth()->id();
        $live->name    = $this->request->name;
        $live->channel = 'live_'.str_random(5) .'_'.auth()->id();
        $live->price   = $this->request->price;
        $live->save();

       // Notify to subscribers
       event(new LiveBroadcasting(auth()->user()));

       return response()->json([
         'success' => true,
         'url' => url('live', auth()->user()->username)
       ]);

  }// End method create

  // End Live Stream
  public function finish($id)
  {
    if (! $this->request->expectsJson()) {
        abort(404);
    }

    $live = LiveStreamings::whereId($id)->whereUserId(auth()->id())->firstOrFail();
    $live->status = '1';
    $live->save();

    return response()->json([
      'success' => true
    ]);
  }// End method finish

  // Show Live Stream
  public function show()
  {
    if ($this->settings->live_streaming_status == 'off') {
      return redirect('/');
    }

    // Find Creator
    $creator = User::whereUsername($this->request->username)
    ->whereVerifiedId('yes')
    ->firstOrFail();

    // Search last Live Streaming
    $live = LiveStreamings::whereUserId($creator->id)
    ->where('updated_at', '>', now()->subMinutes(5))
    ->whereStatus('0')
    ->orderBy('id', 'desc')
    ->first();

    // Check subscription
    $checkSubscription = auth()->user()->checkSubscription($creator);

    // Check User Online (Already paid)
    if ($live) {
      $userPaidAccess = LiveOnlineUsers::whereUserId(auth()->id())
          ->whereLiveStreamingsId($live->id)
          ->first();

      $likes = $live->likes->count();
      $likeActive = $live->likes()->whereUserId(auth()->id())->first();
    }

    // Insert Online user
    if ($live
        && $checkSubscription
        && $checkSubscription->free == 'no'
        && $creator->id != auth()->id()
      ) {
          $onlineUser = LiveOnlineUsers::firstOrCreate([
          'user_id' => auth()->id(),
          'live_streamings_id' => $live->id
        ]);

        if ($onlineUser->exists) {
          $onlineUser->updated_at = now();
          $onlineUser->update();
        }

      // User must have pay for see the live
      $paymentRequiredToAccess = false;
    } elseif ($live && $creator->id == auth()->id()) {
      $paymentRequiredToAccess = false;
    } elseif ($live && $userPaidAccess) {
      $paymentRequiredToAccess = false;
    } else {
      $paymentRequiredToAccess = true;
    }

    return view('users.live', [
      'creator' => $creator,
      'live' => $live,
      'checkSubscription' => $checkSubscription,
      'comments' => $live->comments ?? null,
      'likes' => $likes ?? null,
      'likeActive' => $likeActive ?? null,
      'paymentRequiredToAccess' => $paymentRequiredToAccess,
    ]);

  }// End method show

  public function getDataLive()
  {
    if (! auth()->check()) {
      return response()->json([
        'session_null' => true
      ]);
    }

    // Find Live Streaming
    $live = LiveStreamings::whereId($this->request->live_id)
    ->whereUserId($this->request->creator)
    ->where('updated_at', '>', now()->subMinutes(5))
    ->whereStatus('0')
    ->first();

    $status = $live ? 'online' : 'offline';

    if ($status == 'offline') {
      return response()->json([
        'success' => true,
        'total' => null,
        'comments' => [],
        'onlineUsers' => null,
        'status' => 'offline'
      ]);
    }

    // Online users
    $onlineUsers = $live->onlineUsers->count();

    // Comments
    $comments = $live->comments()
    ->where('id', '>', $this->request->get('last_id'))
    ->get();

    $totalComments = $comments->count();
    $allComments = array();

    if ($totalComments != 0) {

      foreach ($comments as $comment) {

      $allComments[] = view('includes.comments-live', [
          'comments' => $comments
          ])->render();

      }//<--- foreach
    }//<--- IF != 0

    // Likes
    $likes = $live->likes->count();

    return response()->json([
      'success' => true,
      'total' => $totalComments,
      'comments' => $allComments,
      'onlineUsers' => $onlineUsers,
      'likes' => $likes,
      'status' => $status
    ]);

  }// End method getDataLive

  public function paymentAccess()
  {
    // Verify that the user has not paid
    if (LiveOnlineUsers::whereUserId(auth()->id())
        ->whereLiveStreamingsId($this->request->id)
        ->first())
        {
          return response()->json([
            "success" => false,
            "errors" => ['error' => trans('general.already_payment_live_access')]
          ]);
        }

    // Find live exists
    $live = LiveStreamings::whereId($this->request->id)
    ->where('updated_at', '>', now()->subMinutes(5))
    ->whereStatus('0')
    ->firstOrFail();

    $messages = [
      'payment_gateway_live.required' => trans('general.choose_payment_gateway')
    ];

  //<---- Validation
  $validator = Validator::make($this->request->all(), [
      'payment_gateway_live' => 'required'
      ], $messages);

    if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->getMessageBag()->toArray(),
            ]);
        }

        if (auth()->user()->wallet < $live->price) {
          return response()->json([
            "success" => false,
            "errors" => ['error' => __('general.not_enough_funds')]
          ]);
        }

        // Admin and user earnings calculation
        $earnings = $this->earningsAdminUser($live->user()->custom_fee, $live->price, null, null);

        // Insert Transaction
        // $txnId, $userId, $subscriptionsId, $subscribed, $live->price, $userEarning, $adminEarning, $paymentGateway, $type, $percentageApplied
        $this->transaction('live_'.str_random(25), auth()->id(), 0, $live->user()->id, $live->price, $earnings['user'], $earnings['admin'], 'Wallet', 'live', $earnings['percentageApplied']);

        // Subtract user funds
        auth()->user()->decrement('wallet', $live->price);

        // Add Earnings to User
        $live->user()->increment('balance', $earnings['user']);

        // Insert user to Online User
        $sql = new LiveOnlineUsers();
        $sql->user_id = auth()->id();
        $sql->live_streamings_id = $live->id;
        $sql->save();

        // Inser Comment Joined User
        $sql            = new LiveComments();
        $sql->user_id   = auth()->id();
        $sql->live_streamings_id = $live->id;
        $sql->save();

        return response()->json([
          "success" => true,
        ]);

  }// End method paymentAccess

  // Comments
  public function comments()
  {
    // Find Live Streaming
    $live = LiveStreamings::whereId($this->request->live_id)
    ->where('updated_at', '>', now()->subMinutes(5))
    ->whereStatus('0')
    ->firstOrFail();

    $messages = [
      'comment.required' => trans('general.please_write_something'),
    ];

  //<---- Validation
  $validator = Validator::make($this->request->all(), [
      'comment' =>  'required|max:100|min:1',
      ], $messages);

    if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->getMessageBag()->toArray(),
            ]);
        }

   $sql            = new LiveComments();
   $sql->user_id   = auth()->id();
   $sql->live_streamings_id = $live->id;
   $sql->comment   = trim(Helper::checkTextDb($this->request->comment));
   $sql->joined    = 0;
   $sql->save();

   return response()->json([
     'success' => true,
   ]);

 }//<--- End Method

 public function like()
 {
   // Find Live Streaming
   $live = LiveStreamings::whereId($this->request->id)
   ->where('updated_at', '>', now()->subMinutes(5))
   ->whereStatus('0')
   ->firstOrFail();

   $like = LiveLikes::firstOrNew([
     'user_id' => auth()->id(),
     'live_streamings_id' => $this->request->id
   ]);

   if ($like->exists) {
       $like->delete();
   } else {
     $like->save();
   }

   $likes = $live->likes->count();

   return response()->json([
     'success' => true,
     'likes' => $likes
   ]);

 }//<---- End Method


}// End Class
