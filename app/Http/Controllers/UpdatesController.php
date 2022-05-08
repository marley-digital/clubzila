<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AdminSettings;
use App\Models\Notifications;
use App\Models\Comments;
use App\Models\Like;
use App\Models\Updates;
use App\Models\Reports;
use App\Models\Messages;
use App\Models\Media;
use App\Models\MediaMessages;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use League\Glide\Responses\LaravelResponseFactory;
use App\Notifications\NewPost;
use League\Glide\ServerFactory;
use App\Events\NewPostEvent;
use App\Jobs\EncodeVideo;
use Carbon\Carbon;
use App\Helper;
use Image;
use Form;
use Mail;


class UpdatesController extends Controller
{
  use Traits\Functions;

  public function __construct(AdminSettings $settings, Request $request)
  {
		$this->settings = $settings::first();
		$this->request = $request;
	}

  /**
	 * Create Update / Post
	 *
	 * @return Response
	 */
  public function create()
  {
    // Video URL of Youtube, Vimeo
    $videoUrl = '';

    // Currency Position
    if ($this->settings->currency_position == 'right') {
      $currencyPosition =  2;
    } else {
      $currencyPosition =  null;
    }

    $messages = [
    'description.required' => trans('general.please_write_something'),
    '_description.required_if' => trans('general.please_write_something_2'),
    'description.min' => trans('validation.update_min_length'),
    'description.max' => trans('validation.update_max_length'),
    'price.min' => trans('general.amount_minimum'.$currencyPosition, ['symbol' => $this->settings->currency_symbol, 'code' => $this->settings->currency_code]),
    'price.max' => trans('general.amount_maximum'.$currencyPosition, ['symbol' => $this->settings->currency_symbol, 'code' => $this->settings->currency_code]),
    'promotext.required_if' => trans('general.please_write_something_promotext'),
    ];

    if (auth()->user()->verified_id != 'yes') {
      return response()->json([
          'success' => false,
          'errors' => ['error' => trans('general.error_post_not_verified')],
      ]);
    }

    // Media Files
    $fileuploader = $this->request->input('fileuploader-list-photo');
    $fileuploader = json_decode($fileuploader, TRUE);

    $input = $this->request->all();

    if (! $fileuploader && ! $this->request->hasFile('zip')) {
      $urlVideo = Helper::getFirstUrl($input['description']);
      $videoUrl = Helper::videoUrl($urlVideo) ? true : false;
      $input['_description'] = $videoUrl ? str_replace($urlVideo, '', $input['description']) : $input['description'];
      $input['_isVideoEmbed'] = $videoUrl ? 'yes' : 'no';
    }
    //<===== Locked Content
      if ($this->request->locked) {
        $locked = 'yes';
      } elseif ($this->request->price) {
        $locked = 'yes';
      } else {
        $locked = 'no';
      }
      $input['locked'] =  $locked;
    $validator = Validator::make($input, [
      'zip'         => 'mimes:zip|max:'.$this->settings->file_size_allowed.'',
      'description' => 'required|min:1|max:'.$this->settings->update_length.'',
      '_description'=> 'required_if:_isVideoEmbed,==,yes|min:1|max:'.$this->settings->update_length.'',
      'price'       => 'numeric|min:'.$this->settings->min_ppv_amount.'|max:'.$this->settings->max_ppv_amount,
      'promotext' => 'required_if:locked,==,yes|min:1',
    ], $messages);

     if ($validator->fails()) {
          return response()->json([
              'success' => false,
              'errors' => $validator->getMessageBag()->toArray(),
          ]);
      } //<-- Validator

    

      $post               = new Updates();
      $post->description  = trim(Helper::checkTextDb($this->request->description));
      $post->user_id      = auth()->user()->id;
      $post->date         = Carbon::now();
      $post->token_id     = Str::random(150);
      $post->locked       = $locked;
      $post->price        = $this->request->price;
      $post->status       = $this->settings->auto_approve_post == 'on' ? 'active' : 'pending';
      $post->promotext = trim(Helper::checkTextDb($this->request->promotext));
      $post->save();

      // Save blocked post option
      $user = auth()->user();
      $user->post_locked = $this->request->locked;
      $user->save();

      // Insert Files
      if ($fileuploader) {
        foreach ($fileuploader as $key => $media) {
          Media::whereImage($media['file'])
          ->orWhere('video', $media['file'])
          ->orWhere('music', $media['file'])
          ->update([
            'updates_id' => $post->id,
            'user_id' => auth()->user()->id,
            'status' => 'active'
          ]);
        }
      }

      // Insert Video Embed Youtube or Vimeo
      if ($videoUrl) {
        $token = Str::random(150).uniqid().now()->timestamp;

        Media::create([
          'updates_id' => $post->id,
          'user_id' => auth()->user()->id,
          'type' => 'video',
          'image' => '',
          'video' => '',
          'video_embed' => $videoUrl ? $urlVideo : '',
          'music' => '',
          'file' => '',
          'file_name' => '',
          'file_size' => '',
          'img_type' => '',
          'token' => $token,
          'status' => 'active',
          'created_at' => now()
               ]);
          } // End Insert Video Embed Youtube or Vimeo

      // Insert File Zip
      if ($this->request->hasFile('zip')) {

        $pathFiles        = config('path.files');
        $fileZip         = $this->request->file('zip');
        $extension       = $fileZip->getClientOriginalExtension();
        $fileSizeZip     = Helper::formatBytes($fileZip->getSize(), 1);
        $originalNameZip = Helper::fileNameOriginal($fileZip->getClientOriginalName());
        $file            = strtolower(auth()->user()->id.time().Str::random(20).'.'.$extension);

        $fileZip->storePubliclyAs($pathFiles, $file);
        $zipFile = $file;
        $token = Str::random(150).uniqid().now()->timestamp;

        Media::create([
          'updates_id' => $post->id,
          'user_id' => auth()->user()->id,
          'type' => 'file',
          'image' => '',
          'video' => '',
          'video_embed' => '',
          'music' => '',
          'file' => $zipFile,
          'file_name' => $originalNameZip,
          'file_size' => $fileSizeZip,
          'img_type' => '',
          'token' => $token,
          'status' => 'active',
          'created_at' => now()
               ]);
          } // End Insert File Zip

      // Get all videos of the post
      $videos = Media::whereUpdatesId($post->id)->where('video', '<>', '')->get();

      if ($videos->count() && $this->settings->video_encoding == 'on') {

        try {
          foreach ($videos as $video) {
            $this->dispatch(new EncodeVideo($video));
          }

          // Change status Pending to Encode
          Updates::whereId($post->id)->update([
              'status' => 'encode'
          ]);

          return response()->json([
            'success' => true,
            'pending' => true,
            'encode' => true
          ]);

        } catch (\Exception $e) {
          \Log::info($e->getMessage());
        }

      }// End Videos->count

      if ($this->settings->auto_approve_post == 'off') {
        return response()->json([
          'success' => true,
          'pending' => true
        ]);
      }

      // Event to listen
      event(new NewPostEvent($post));

      return response()->json([
              'success' => true,
              'data' => view('includes.updates',
                  ['updates' => Updates::whereId($post->id)->get(),
                  'singlePost' => true,
                  'ajaxRequest' => false,
                  'counterPosts' => 0,
                  'total' => 0
                  ])->render(),
              'pending' => false,
              'total' => auth()->user()->updates()->count(),
            ]);

  }//<---- End Method

  public function ajaxUpdates()
  {
    $id = $this->request->input('id');
    $skip = $this->request->input('skip');
    $total = $this->request->input('total');
    $media = $this->request->input('media');
    $mediaArray = ['photos', 'videos', 'audio', 'files'];

    $user = User::findOrFail($id);

    if (isset($media) && ! in_array($media, $mediaArray)) {
      abort(500);
    }

    $page = $this->request->input('page');

    if (isset($media)) {
      $query = $user->media();
    } else {
      $query = $user->updates()->whereFixedPost('0');
    }

    //=== Photos
    $query->when($this->request->input('media') == 'photos', function($q) {
      $q->where('media.image', '<>', '');
    });

    //=== Videos
    $query->when($this->request->input('media') == 'videos', function($q) use($user) {
      $q->where('media.video', '<>', '')->orWhere('media.video_embed', '<>', '')->where('media.user_id', $user->id);
    });

    //=== Audio
    $query->when($this->request->input('media') == 'audio', function($q) {
      $q->where('media.music', '<>', '');
    });

    //=== Files
    $query->when($this->request->input('media') == 'files', function($q) {
      $q->where('media.file', '<>', '');
    });

    $data = $query->orderBy('updates.id','desc')
    ->groupBy('updates.id')
    ->skip($skip)
    ->take($this->settings->number_posts_show)
    ->get();

    $counterPosts = ($total - $this->settings->number_posts_show - $skip);

    return view('includes.updates',
        ['updates' => $data,
        'ajaxRequest' => true,
        'counterPosts' => $counterPosts,
        'total' => $total
        ])->render();

  }//<--- End Method

  public function edit($id)
  {
    $data = auth()->user()->updates()->findOrFail($id);

    return view('users.edit-update')->withData($data);
  }

  public function postEdit()
  {
    $id  = $this->request->input('id');
    $sql = Updates::whereId($id)->whereUserId(auth()->user()->id)->firstOrFail();
    $videoUrl = '';

    // Currency Position
    if ($this->settings->currency_position == 'right') {
      $currencyPosition =  2;
    } else {
      $currencyPosition =  null;
    }

    $messages = [
    'description.required' => trans('general.please_write_something'),
    '_description.required_if' => trans('general.please_write_something_2'),
    'description.min' => trans('validation.update_min_length'),
    'description.max' => trans('validation.update_max_length'),
    'price.min' => trans('general.amount_minimum'.$currencyPosition, ['symbol' => $this->settings->currency_symbol, 'code' => $this->settings->currency_code]),
    'price.max' => trans('general.amount_maximum'.$currencyPosition, ['symbol' => $this->settings->currency_symbol, 'code' => $this->settings->currency_code]),
    'price.required_if' => trans('validation.required'),
    'promotext.required_if' => trans('general.please_write_something_promotext'),
    ];

    $input = $this->request->all();
    $mediaFiles = $sql->media()->where('video_embed', '=', '')->count();

    $getAllMedia = $sql->media()
        ->where('image', '=', '')
        ->where('video', '=', '')
        ->where('music', '=', '')
        ->where('file', '=', '')
        ->first();


      $urlVideo = Helper::getFirstUrl($input['description']);
      $videoUrl = Helper::videoUrl($urlVideo) ? true : false;

  if ($mediaFiles == 0 && isset($getAllMedia) && $getAllMedia->video_embed != '' && ! $videoUrl) {
      $input['_description'] = $videoUrl ? str_replace($urlVideo, '', $input['description']) : $input['description'];
      $input['_isVideoEmbed'] = $videoUrl ? 'yes' : 'no';
    }

    $input['is_ppv'] = $sql->price == 0.00 ? 'no' : 'yes';
    //<===== Locked Content
     if ($this->request->locked) {
      $this->request->locked = 'yes';
    } elseif ($this->request->price) {
      $this->request->locked = 'yes';
    } else {
      $this->request->locked = 'no';
    }
    $input['locked'] =  $this->request->locked;
    $validator = Validator::make($input, [
      'description'  => 'required|min:1|max:'.$this->settings->update_length.'',
      '_description' => 'required_if:_isVideoEmbed,==,yes|min:1|max:'.$this->settings->update_length.'',
      'price'       => 'required_if:is_ppv,==,yes|numeric|min:'.$this->settings->min_ppv_amount.'|max:'.$this->settings->max_ppv_amount,
    'promotext' => 'required_if:locked,==,yes|min:1',
    ], $messages);

    if ($validator->fails()) {
         return response()->json([
             'success' => false,
             'errors' => $validator->getMessageBag()->toArray(),
         ]);
     } //<-- Validator

     

      $sql->description  = trim(Helper::checkTextDb($this->request->description));
      $sql->user_id      = auth()->user()->id;
      $sql->token_id     = Str::random(150);
      $sql->locked       = $this->request->locked;
      $sql->price        = $this->request->price;
       $sql->promotext = trim(Helper::checkTextDb($this->request->promotext));
      $sql->save();

      $videoEmbed = $sql->media()->where('video_embed', '<>', '')->get();
      $isVideoEmbed = false;

      // Insert Video Embed Youtube or Vimeo
     if ($videoUrl) {

        if ($videoEmbed->count() == 0) {

         $token = Str::random(150).uniqid().now()->timestamp;

         Media::create([
           'updates_id' => $sql->id,
           'user_id' => auth()->user()->id,
           'type' => 'video',
           'image' => '',
           'video' => '',
           'video_embed' => $urlVideo,
           'music' => '',
           'file' => '',
           'file_name' => '',
           'file_size' => '',
           'img_type' => '',
           'token' => $token,
           'status' => 'active',
           'created_at' => now()
         ]);
       }
       $isVideoEmbed = $urlVideo;
     }

  		if ($videoEmbed->count() != 0) {
  			foreach ($videoEmbed as $media) {

          // Update URL the Video
          if ($media->video_embed != $urlVideo) {
            $media->video_embed = $urlVideo;
            $media->save();
          }

  				$isVideoEmbed = $media->video_embed;
  			}
  		}

      if ($videoEmbed->count() != 0 && ! $videoUrl) {
        foreach ($videoEmbed as $media) {
          $media->delete();
  				$isVideoEmbed = null;
  			}
      }

      return response()->json([
              'success' => true,
              'description' => Helper::linkText(Helper::checkText($sql->description, $isVideoEmbed)),
              'price' => $this->request->price ? Helper::amountFormatDecimal($this->request->price) : '',
              'locked' => $this->request->locked

            ]);

  }//<---- End Method

  public function delete($id)
  {
    if (! $this->request->expectsJson()) {
        abort(404);
    }

    if (auth()->user()->subscriptionsActive() && $this->settings->users_can_edit_post == 'off') {
      return response()->json([
              'success' => false,
              'message' => __('general.error_delete_post')
            ]);
    }

	  $update    = Updates::whereId($id)->whereUserId(auth()->user()->id)->firstOrFail();
    $path      = config('path.images');
    $pathVideo = config('path.videos');
    $pathMusic = config('path.music');
    $pathFile  = config('path.files');

    $files = Media::whereUpdatesId($update->id)->get();

    foreach ($files as $media) {

      if ($media->image) {
        Storage::delete($path.$media->image);
        $media->delete();
      }

      if ($media->video) {
        Storage::delete($pathVideo.$media->video);
        Storage::delete($pathVideo.$media->video_poster);
        $media->delete();
      }

      if ($media->music) {
        Storage::delete($pathMusic.$media->music);
        $media->delete();
      }

      if ($media->file) {
        Storage::delete($pathFile.$media->file);
        $media->delete();
      }

      if ($media->video_embed) {
        $media->delete();
      }
    }

      // Delete Reports
  		$reports = Reports::where('report_id', $id)->where('type','update')->get();

  		if (isset($reports)) {
  			foreach($reports as $report) {
  				$report->delete();
  			}
  		}

      // Delete Notifications
      Notifications::where('target', $id)
  			->where('type', '2')
  			->orWhere('target', $id)
  			->where('type', '3')
        ->orWhere('target', $id)
        ->where('type', '6')
        ->orWhere('target', $id)
        ->where('type', '7')
        ->orWhere('target', $id)
        ->where('type', '8')
        ->orWhere('target', $id)
        ->where('type', '9')
  			->delete();

        // Delete Likes Comments
        foreach ($update->comments()->get() as $key) {
          $key->likes()->delete();
        }

        // Delete Comments
        $update->comments()->delete();

        // Delete likes
        Like::where('updates_id', $id)->delete();

        // Delete Update
        $update->delete();

        if ($this->request->inPostDetail && $this->request->inPostDetail == 'true') {
          return response()->json([
                  'success' => true,
                  'inPostDetail' => true,
                  'url_return' => url(auth()->user()->username)
                ]);
        } else {
          return response()->json([
                  'success' => true
                ]);
        }

	}//<--- End Method

  public function report(Request $request)
  {

    $data = Reports::firstOrNew(['user_id' => auth()->user()->id, 'report_id' => $request->id]);

    $validator = Validator::make($this->request->all(), [
      'reason' => 'required|in:copyright,privacy_issue,violent_sexual',
    ]);

     if ($validator->fails()) {
          return response()->json([
              'success' => false,
              'text' => __('general.error'),
          ]);
      }

    if ($data->exists ) {
      return response()->json([
          'success' => false,
          'text' => __('general.already_sent_report'),
      ]);
    } else {

      $data->type = 'update';
      $data->reason = $request->reason;
      $data->save();

      return response()->json([
          'success' => true,
          'text' => __('general.reported_success'),
      ]);
    }
	}//<--- End Method

  public function image($id, $path)
	{
			try {

				$server = ServerFactory::create([
            'response' => new LaravelResponseFactory(app('request')),
            'source' => Storage::disk()->getDriver(),
            'cache' => Storage::disk()->getDriver(),
						'source_path_prefix' => '/uploads/updates/images/',
            'cache_path_prefix' => '.cache',
            'base_url' => '/uploads/updates/images/',
            'group_cache_in_folders' => false
        ]);

        $server->outputImage($path, $this->request->all());

			} catch (\Exception $e) {

				abort(404);
				$server->deleteCache($path);
			}
    }//<--- End Method

    public function messagesImage($id, $path)
  	{
  			try {

  				$server = ServerFactory::create([
              'response' => new LaravelResponseFactory(app('request')),
              'source' => Storage::disk()->getDriver(),
              'cache' => Storage::disk()->getDriver(),
  						'source_path_prefix' => '/uploads/messages/',
              'cache_path_prefix' => '.cache',
              'base_url' => '/uploads/messages/',
              'group_cache_in_folders' => false
          ]);

          $response = Messages::whereId($id)
              ->whereFromUserId(auth()->user()->id)
                ->orWhere('id', '=', $id)->where('to_user_id', '=', auth()->user()->id)
                 ->firstOrFail();

          $server->outputImage($path, $this->request->all());

  			} catch (\Exception $e) {

  				abort(404);
  				$server->deleteCache($path);
  			}
      }//<--- End Method

    public function pinPost(Request $request)
    {
      $findPost = Updates::whereId($request->id)->whereUserId(auth()->user()->id)->firstOrFail();
      $findCurrentPostPinned = Updates::whereUserId(auth()->user()->id)->whereFixedPost('1')->first();

      if ($findPost->fixed_post == '0') {
        $status = 'pin';
        $findPost->fixed_post = '1';
        $findPost->update();

        // Unpin old post
        if ($findCurrentPostPinned) {
          $findCurrentPostPinned->fixed_post = '0';
          $findCurrentPostPinned->update();
        }

      } else {
        $status = 'unpin';
        $findPost->fixed_post = '0';
        $findPost->update();
      }

      return response()->json([
              'success' => true,
              'status' => $status,
            ]);
    }

    // Bookmarks Ajax Pagination
    public function ajaxBookmarksUpdates()
    {
      $skip = $this->request->input('skip');
      $total = $this->request->input('total');

      $data = auth()->user()->bookmarks()->orderBy('bookmarks.id','desc')->skip($skip)->take($this->settings->number_posts_show)->get();
      $counterPosts = ($total - $this->settings->number_posts_show - $skip);

      return view('includes.updates',
          ['updates' => $data,
          'ajaxRequest' => true,
          'counterPosts' => $counterPosts,
          'total' => $total
          ])->render();

    }//<--- End Method

    public function getFileMedia($typeMedia, $fileId)
    {
      $response = Media::findOrFail($fileId);
      $checkUserSubscription = auth()->check() ? auth()->user()->checkSubscription($response->updates->user()) : null;

        switch ($typeMedia) {
          case 'video':
            $pathFile = config('path.videos').$response->video;
            $type = 'video/mp4';
            break;

          case 'audio':
            $pathFile = config('path.music').$response->music;
            $type = 'audio/mpeg';
            break;
        }

  			if (auth()->check()
        && auth()->user()->id == $response->updates->user_id

        || auth()->check()
        && $response->updates->locked == 'yes'
        && $checkUserSubscription
        && $response->updates->price != 0.00
        && $checkUserSubscription->free == 'no'

        || auth()->check()
        && $response->updates->locked == 'yes'
        && $checkUserSubscription
        && $response->updates->price == 0.00

        || auth()->check()
        && auth()->user()->payPerView()->where('updates_id', $response->updates->id)->first()

        || auth()->check()
        && auth()->user()->role == 'admin' && auth()->user()->permission == 'all'
  			|| $response->updates->locked == 'no'
  		) {
        $path = Helper::getFile($pathFile);

            try {
            header("Content-Disposition: inline; filename=\"$path\"");
            header("Content-type: $type");
            print file_get_contents($path);

        } catch (\FileNotFoundException $exception) {
            abort(404);
        }

  		} else {
  			abort(404);
  		}
    }//<--- End Method

    public function explore()
    {
      $updates = Updates::leftjoin('users', 'updates.user_id', '=', 'users.id')
          ->where('user_id', '<>', auth()->user()->id)
          ->whereLocked('no')
          ->where('updates.status', 'active')
          ->where('users.blocked_countries', 'NOT LIKE', '%'.Helper::userCountry().'%')
          ->orWhere('user_id', '<>', auth()->user()->id)
          ->where('updates.price', '<>', 0.00)
          ->where('updates.status', 'active')
          ->where('users.blocked_countries', 'NOT LIKE', '%'.Helper::userCountry().'%')
          ->orderBy('updates.id', 'desc')
          ->select('updates.*')
          ->paginate($this->settings->number_posts_show);

      $users = $this->userExplore();

      return view('index.explore', ['updates' => $updates, 'users' => $users]);
    }//<--- End Method

    // My Purchases Ajax Pagination
    public function ajaxExplore()
    {
      $skip = $this->request->input('skip');
      $total = $this->request->input('total');

      $data = Updates::leftjoin('users', 'updates.user_id', '=', 'users.id')
          ->where('user_id', '<>', auth()->user()->id)
          ->whereLocked('no')
          ->where('updates.status', 'active')
          ->where('users.blocked_countries', 'NOT LIKE', '%'.Helper::userCountry().'%')
          ->orWhere('user_id', '<>', auth()->user()->id)
          ->where('updates.price', '<>', 0.00)
          ->where('updates.status', 'active')
          ->where('users.blocked_countries', 'NOT LIKE', '%'.Helper::userCountry().'%')
          ->orderBy('updates.id', 'desc')
          ->skip($skip)
          ->take($this->settings->number_posts_show)
          ->select('updates.*')
          ->get();

      $counterPosts = ($total - $this->settings->number_posts_show - $skip);

      return view('includes.updates',
          ['updates' => $data,
          'ajaxRequest' => true,
          'counterPosts' => $counterPosts,
          'total' => $total
          ])->render();

    }//<--- End Method

    public function imageFocus($type, $path)
  	{
  			try {

          switch ($type) {
            case 'photo':
              $urlStorage = '/uploads/updates/images/';

              $realPath = Media::findOrFail($path);
              $path = $realPath->image;

              break;

            case 'video':
              $urlStorage = '/uploads/updates/videos/';
              break;

              case 'message':
                $urlStorage = '/uploads/messages/';

                $realPath = MediaMessages::findOrFail($path);

                if ($realPath->type == 'image') {
                  $path = $realPath->file;
                } elseif ($realPath->type == 'video') {
                  $path = $realPath->video_poster;
                }
                break;
          }

  				$server = ServerFactory::create([
              'response' => new LaravelResponseFactory(app('request')),
              'source' => Storage::disk()->getDriver(),
              'cache' => Storage::disk()->getDriver(),
  						'source_path_prefix' => $urlStorage,
              'cache_path_prefix' => '.cache',
              'base_url' => $urlStorage,
              'group_cache_in_folders' => false
          ]);

          $server->outputImage($path, [
  					'w' => 250,
  					'h' => 150,
  					'blur' => 85
  				]
  			);

  			} catch (\Exception $e) {

  				abort(404);
  				$server->deleteCache($path);
  			}
      }//<--- End Method


}
