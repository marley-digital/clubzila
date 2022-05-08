<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveOnlineUsers extends Model
{
  protected $guarded = array();
  protected $fillable = [
    'user_id',
    'live_streamings_id'
  ];

  use HasFactory;
}
