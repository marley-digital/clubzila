<?php

namespace App\Models;

use App\Models\AdminSettings;
use App\Models\Notifications;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;



use Mail;

class Subscriptions extends Model
{

	protected $guarded = array();

	public function user()
	{
		return $this->belongsTo('App\Models\User')->first();

	}

		public function subscribed()
		{
			return $this->belongsTo('App\Models\User', 'stripe_price', 'plan')->first();
		}

		public static function sendEmailAndNotify($subscriber, $user)
		{
			$user = User::find($user);
			$settings = AdminSettings::first();
			$titleSite = $settings->title;
			$sender    = $settings->email_no_reply;
			$emailUser   = $user->email;
			$fullNameUser = $user->name;
			$subject = $subscriber.' '.trans('users.has_subscribed');

			if ($user->email_new_subscriber == 'yes') {
				//<------ Send Email to User ---------->>>
				Mail::send('emails.new_subscriber', [
					'body' => $subject,
					'title_site' => $titleSite,
					'fullname'   => $fullNameUser
				],
					function($message) use ($sender, $subject, $fullNameUser, $titleSite, $emailUser)
						{
					    $message->from($sender, $titleSite)
										  ->to($emailUser, $fullNameUser)
											->subject($subject.' - '.$titleSite);
						});
					//<------ End Send Email to User ---------->>>
			}

			if ($user->notify_new_subscriber == 'yes') {
				// Send Notification to User --- destination, author, type, target
				Notifications::send($user->id, auth()->user()->id, '1', $user->id);
			}
		}

    public static function sendEmailAndNotifyCallBack($subscriber, $user,$user_id)
    {
        $user = User::find($user);
        $settings = AdminSettings::first();
        $titleSite = $settings->title;
        $sender    = $settings->email_no_reply;
        $emailUser   = $user->email;
        $fullNameUser = $user->name;
        $subject = $subscriber.' '.trans('users.has_subscribed');

        if ($user->email_new_subscriber == 'yes') {
            //<------ Send Email to User ---------->>>
            Mail::send('emails.new_subscriber', [
                'body' => $subject,
                'title_site' => $titleSite,
                'fullname'   => $fullNameUser
            ],
                function($message) use ($sender, $subject, $fullNameUser, $titleSite, $emailUser)
                {
                    $message->from($sender, $titleSite)
                        ->to($emailUser, $fullNameUser)
                        ->subject($subject.' - '.$titleSite);
                });
            //<------ End Send Email to User ---------->>>
        }

        if ($user->notify_new_subscriber == 'yes') {
            // Send Notification to User --- destination, author, type, target
            Notifications::send($user->id, $user_id, '1', $user->id);
        }
    }
}
