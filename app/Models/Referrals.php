<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referrals extends Model
{
  protected $fillable = [
    'user_id',
    'referred_by'
  ];

  public function user() {
    return $this->belongsTo('App\Models\User')->first();
  }

  public function referredBy() {
    return $this->belongsTo('App\Models\User', 'referred_by')->first();
  }
}
