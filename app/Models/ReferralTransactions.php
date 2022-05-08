<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralTransactions extends Model
{
  protected $fillable = [
    'referrals_id',
    'user_id',
    'referred_by',
    'earnings',
    'type',
    'created_at'
  ];

    public function user() {
          return $this->belongsTo('App\Models\User')->first();
      }

		public function referredBy() {
	        return $this->belongsTo('App\Models\User', 'referred_by')->first();
	    }

}
