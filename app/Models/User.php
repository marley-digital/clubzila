<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Billable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Contracts\Translation\HasLocalePreference;
use App\Models\Notifications;
use Carbon\Carbon;

class User extends Authenticatable implements HasLocalePreference
{
    use Notifiable, Billable;

    const CREATED_AT = 'date';
  	const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username',
        'countries_id',
        'name',
        'email',
        'password',
        'avatar',
        'cover',
        'status',
        'role',
        'permission',
        'confirmation_code',
        'oauth_uid',
        'oauth_provider',
        'token',
        'story',
        'verified_id',
        'ip',
        'language'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function userSubscriptions()
    {
          return $this->hasMany('App\Models\Subscriptions');
      }

    public function mySubscriptions()
    {
          return $this->hasMany('App\Models\Subscriptions', 'stripe_price', 'plan');
      }

    public function myPayments()
    {
          return $this->hasMany('App\Models\Transactions');
      }

    public function myPaymentsReceived()
    {
          return $this->hasMany('App\Models\Transactions', 'subscribed')->where('approved', '<>', '0');
      }

    public function updates()
    {
      return $this->hasMany('App\Models\Updates')->where('status', 'active');
    }

    public function media()
    {
      return $this->belongsToMany('App\Models\Updates', 'media','user_id','updates_id')->where('updates.status', 'active')->where('media.status', 'active');
    }

      public function withdrawals()
      {
        return $this->hasMany('App\Models\Withdrawals');
    }

  	public function country()
    {
          return $this->belongsTo('App\Models\Countries', 'countries_id')->first();
      }

      public function notifications()
      {
            return $this->hasMany('App\Models\Notifications', 'destination');
        }

      public function messagesInbox()
      {
            return $this->hasMany('App\Models\Messages', 'to_user_id')->where('status','new')->count();
        }

      public function comments()
      {
            return $this->hasMany('App\Models\Comments');
        }

      public function likes()
      {
        return $this->hasMany('App\Models\Like');
      }

      public function category()
      {
        return $this->belongsTo('App\Models\Categories', 'categories_id');
      }

      public function verificationRequests()
      {
            return $this->hasMany('App\Models\VerificationRequests')->whereStatus('pending')->count();
        }

      public static function notificationsCount()
      {
        // Notifications Count
      	$notifications_count = auth()->user()->notifications()->where('status', '0')->count();
        // Messages
      	$messages_count = auth()->user()->messagesInbox();

        if( $messages_count != 0 &&  $notifications_count != 0 ) {
          $totalNotifications = ( $messages_count + $notifications_count );
        } else if( $messages_count == 0 &&  $notifications_count != 0  ) {
          $totalNotifications = $notifications_count;
        } else if ( $messages_count != 0 &&  $notifications_count == 0 ) {
          $totalNotifications = $messages_count;
        } else {
          $totalNotifications = null;
        }

       return $totalNotifications;
    }

      function getFirstNameAttribute()
      {
        $name = explode(' ', $this->name);
        return $name[0] ?? null;
      }

      function getLastNameAttribute()
      {
        $name = explode(' ', $this->name);
        return $name[1] ?? null;
      }

      public function bookmarks()
      {
        return $this->belongsToMany('App\Models\Updates', 'bookmarks','user_id','updates_id');
      }

      public function likesCount()
      {
        return $this->hasManyThrough('App\Models\Like', 'App\Models\Updates', 'user_id', 'updates_id')->where('likes.status', '=', '1')->count();
      }

      public function checkSubscription($user)
      {
        return $this->userSubscriptions()
            ->where('stripe_price', $user->plan)
            ->where('stripe_id', '=', '')
            ->where('ends_at', '>=', now())

              ->orWhere('stripe_status', 'active')
                ->where('stripe_price', $user->plan)
              ->where('stripe_id', '<>', '')
              ->whereUserId($this->id)

              ->orWhere('stripe_id', '<>', '')
                ->where('stripe_price', $user->plan)
                ->where('stripe_status', 'canceled')
                ->where('ends_at', '>=', now())
              ->whereUserId($this->id)

                ->orWhere('stripe_price', $user->plan)
                ->where('stripe_id', '=', '')
              ->whereFree('yes')
              ->whereUserId($this->id)
              ->first();
            }

        public function subscriptionsActive()
        {
          return $this->mySubscriptions()
              ->where('stripe_id', '=', '')
                ->where('ends_at', '>=', now())
                ->orWhere('stripe_status', 'active')
                  ->where('stripe_id', '<>', '')
                    ->whereStripePrice($this->plan)
                    ->orWhere('stripe_id', '=', '')
                  ->where('stripe_price', $this->plan)
              ->where('free', '=', 'yes')
            ->first();
        }

        public function totalSubscriptionsActive()
        {
          return $this->mySubscriptions()
              ->where('stripe_id', '=', '')
                ->where('ends_at', '>=', now())
                ->orWhere('stripe_status', 'active')
                  ->where('stripe_id', '<>', '')
                    ->whereStripePrice($this->plan)
                    ->orWhere('stripe_id', '=', '')
                  ->where('stripe_price', $this->plan)
              ->where('free', '=', 'yes')
            ->count();
        }

        public function payPerView()
        {
          return $this->belongsToMany('App\Models\Updates', 'pay_per_views','user_id','updates_id');
        }


        public function payPerViewMessages()
        {
          return $this->belongsToMany('App\Models\Messages', 'pay_per_views','user_id','messages_id');
        }

    /**
     * Get the user's preferred locale.
     */
    public function preferredLocale()
    {
        return $this->language;
    }

    /**
     * Get the user's is Super Admin.
     */
    public function isSuperAdmin()
    {
      if ($this->permissions == 'full_access') {
        return $this->id;
      }
        return false;
    }

    /**
     * Get the user's permissions.
     */
    public function hasPermission($section)
    {
      $permissions = explode(',', $this->permissions);

      return in_array($section, $permissions)
            || $this->permissions == 'full_access'
            || $this->permissions == 'limited_access'
            ? true
            : false;
    }

    /**
     * Get the user's blocked countries.
     */
    public function blockedCountries()
    {
      return explode(',', $this->blocked_countries);
    }

    /**
     * Get Referrals.
     */
    public function referrals()
    {
      return $this->hasMany('App\Models\Referrals', 'referred_by');
    }

    public function referralTransactions() {
      return $this->hasMany('App\Models\ReferralTransactions', 'referred_by');
    }

    /**
     * Broadcasting Live
     */
     public function isLive()
     {
       return $this->hasMany('App\Models\LiveStreamings')
         ->where('updated_at', '>', now()->subMinutes(5))
         ->whereStatus('0')
         ->orderBy('id', 'desc')
         ->first();
     }

}
