@extends('layouts.app')

@section('title'){{trans('general.notifications')}} -@endsection

@section('content')
<section class="section section-sm">
    <div class="container">
      <div class="row justify-content-center text-center mb-sm">
        <div class="col-lg-8 py-5">
          <h2 class="mb-0 font-montserrat">
            <i class="far fa-bell mr-2"></i> {{trans('general.notifications')}}

            <small class="font-tiny">
              <a href="javascript:;" class="btn-notify" data-toggle="modal" data-target="#notifications"><i class="fa fa-cog mr-2"></i></a>

          @if (count($notifications) != 0)
              {!! Form::open([
    						'method' => 'POST',
    						'url' => "notifications/delete",
    						'class' => 'd-inline'
    					]) !!}

    					{!! Form::button('<i class="fa fa-trash-alt"></i>', ['class' => 'btn btn-lg  align-baseline p-0 e-none btn-link actionDeleteNotify']) !!}
    					{!! Form::close() !!}
            @endif
            </small>
          </h2>
          <p class="lead text-muted mt-0">{{trans('general.notifications_subtitle')}}</p>
        </div>
      </div>
      <div class="row">

        @include('includes.cards-settings')

        <div class="col-md-6 col-lg-9 mb-5 mb-lg-0">

          @if (session('status'))
                  <div class="alert alert-success">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                			<span aria-hidden="true">×</span>
                			</button>

                    {{ session('status') }}
                  </div>
                @endif

        <?php

        	foreach ($notifications as $key) {

            $postUrl = url($key->usernameAuthor.'/'.'post', $key->id);
            $notyNormal = true;

        		switch ($key->type) {
        			case 1:
        				$action          = trans('users.has_subscribed');
        				$linkDestination = false;
        				break;
        			case 2:
        				$action          = trans('users.like_you');
        				$linkDestination = $postUrl;
                $text_link       = Str::limit($key->description, 50, '...');
        				break;
        			case 3:
        				$action          = trans('users.comment_you');
        				$linkDestination = $postUrl;
                $text_link       = Str::limit($key->description, 50, '...');
        				break;

              case 4:
        				$action          = trans('general.liked_your_comment');
        				$linkDestination = $postUrl;
                $text_link       = Str::limit($key->description, 50, '...');
        				break;

              case 5:
        				$action          = trans('general.he_sent_you_tip');
        				$linkDestination = url('my/payments/received');
                $text_link       = trans('general.tip');
        				break;

            case 6:
              $action          = trans('general.has_bought_your_message');
              $linkDestination = url('messages', $key->userId);
              $text_link       = Str::limit($key->message, 50, '...');
              break;

              case 7:
        				$action          = trans('general.has_bought_your_content');
        				$linkDestination = $postUrl;
                $text_link       = Str::limit($key->description, 50, '...');
        				break;

              case 8:
        				$action          = trans('general.has_approved_your_post');
        				$linkDestination = $postUrl;
                $text_link       = Str::limit($key->description, 50, '...');
                $iconNotify      = 'bi bi-check2-circle';
                $notyNormal      = false;
        				break;

              case 9:
                $action          = trans('general.video_processed_successfully_post');
                $linkDestination = $postUrl;
                $text_link       = Str::limit($key->description, 50, '...');
                $iconNotify      = 'bi bi-play-circle';
                $notyNormal      = false;
                break;

              case 10:
                $action          = trans('general.video_processed_successfully_message');
                $linkDestination = url('messages', $key->userDestination);
                $text_link       = Str::limit($key->message, 50, '...');
                $iconNotify       = 'bi bi-play-circle';
                $notyNormal      = false;
                break;

              case 11:
                $action          = trans('general.referrals_made');
                $linkDestination = url('my/referrals');
                $text_link       = trans('general.transaction');
                $iconNotify      = 'bi bi-person-plus';
                $notyNormal = false;
                break;

              case 12:
        				$action          = trans('general.payment_received_subscription_renewal');
        				$linkDestination = url('my/payments/received');
                $text_link       = trans('general.go_payments_received');
        				break;

              case 13:
        				$action          = trans('general.has_changed_subscription_paid');
        				$linkDestination = url($key->username);
                $text_link       = trans('general.subscribe_now');
        				break;

              case 14:
        				$action          = trans('general.is_streaming_live');
        				$linkDestination = url('live', $key->username);
                $text_link       = trans('general.go_live_stream');
        				break;
        		}

        ?>

        <div class="card mb-3 card-updates">
        	<div class="card-body">
        	<div class="media">

            @if ($notyNormal)
              <span class="rounded-circle mr-3">
          			<a href="{{url($key->username)}}">
          				<img src="{{Helper::getFile(config('path.avatar').$key->avatar)}}" class="rounded-circle" width="60" height="60">
          				</a>
          		</span>

            @else

              <span class="rounded-circle mr-3">
                <span class="icon-notify">
                  <i class="{{ $iconNotify }}"></i>
                </span>
            </span>
            @endif

        		<div class="media-body">
        				<h6 class="mb-0 font-montserrat text-notify">

                @if ($notyNormal)
        				<a href="{{url($key->username)}}">
        					{{$key->hide_name == 'yes' ? $key->username : $key->name}}
        				</a>
              @endif

                {{$action}}

                @if ($linkDestination != false)
                  <a href="{{url($linkDestination)}}">{{$text_link}}</a>
                @endif
              </h6>

        				<small class="timeAgo text-muted" data="{{date('c', strtotime($key->created_at))}}"></small>
        		</div><!-- media body -->
        	</div><!-- media -->
        </div><!-- card body -->
        </div>

    <?php } //foreach ?>

    @if (count($notifications) == 0)

      <div class="my-5 text-center">
        <span class="btn-block mb-3">
          <i class="far fa-bell-slash ico-no-result"></i>
        </span>
      <h4 class="font-weight-light">{{trans('general.no_notifications')}}</h4>
      </div>
    @endif

@if($notifications->hasPages())
    {{ $notifications->onEachSide(0)->links() }}
  @endif

    </div><!-- end col-md-6 -->

      </div>
    </div>
  </section>

  <div class="modal fade" id="notifications" tabindex="-1" role="dialog" aria-labelledby="modal-form" aria-hidden="true">
    <div class="modal-dialog modal- modal-dialog-centered modal-sm" role="document">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div class="card bg-white shadow border-0">

            <div class="card-body px-lg-5 py-lg-5">

              <div class="mb-3">
                <h6 class="position-relative">{{trans('general.receive_notifications_when')}}
                  <small data-dismiss="modal" class="btn-cancel-msg"><i class="bi bi-x-lg"></i></small>
                </h6>
              </div>

              <form method="POST" action="{{ url('notifications/settings') }}" id="form">

                @csrf

                @if (auth()->user()->verified_id == 'yes')
                <div class="custom-control custom-switch">
                  <input type="checkbox" class="custom-control-input" name="notify_new_subscriber" value="yes" @if (auth()->user()->notify_new_subscriber == 'yes') checked @endif id="customSwitch1">
                  <label class="custom-control-label switch" for="customSwitch1">{{ trans('general.someone_subscribed_content') }}</label>
                </div>

                <div class="custom-control custom-switch">
                  <input type="checkbox" class="custom-control-input" name="notify_liked_post" value="yes" @if (auth()->user()->notify_liked_post == 'yes') checked @endif id="customSwitch2">
                  <label class="custom-control-label switch" for="customSwitch2">{{ trans('general.someone_liked_post') }}</label>
                </div>

                <div class="custom-control custom-switch">
                  <input type="checkbox" class="custom-control-input" name="notify_commented_post" value="yes" @if (auth()->user()->notify_commented_post == 'yes') checked @endif id="customSwitch3">
                  <label class="custom-control-label switch" for="customSwitch3">{{ trans('general.someone_commented_post') }}</label>
                </div>

                <div class="custom-control custom-switch">
                  <input type="checkbox" class="custom-control-input" name="notify_new_tip" value="yes" @if (auth()->user()->notify_new_tip == 'yes') checked @endif id="customSwitch5">
                  <label class="custom-control-label switch" for="customSwitch5">{{ trans('general.someone_sent_tip') }}</label>
                </div>

                <div class="custom-control custom-switch">
                  <input type="checkbox" class="custom-control-input" name="notify_new_ppv" value="yes" @if (auth()->user()->notify_new_ppv == 'yes') checked @endif id="customSwitch9">
                  <label class="custom-control-label switch" for="customSwitch9">{{ trans('general.someone_bought_my_content') }}</label>
                </div>
              @endif

              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" name="notify_liked_comment" value="yes" @if (auth()->user()->notify_liked_comment == 'yes') checked @endif id="customSwitch10">
                <label class="custom-control-label switch" for="customSwitch10">{{ trans('general.someone_liked_comment') }}</label>
              </div>

                <div class="mt-3">
                  <h6 class="position-relative">{{trans('general.email_notification')}}
                  </h6>
                </div>

                @if (auth()->user()->verified_id == 'yes')
                  <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="email_new_subscriber" value="yes" @if (auth()->user()->email_new_subscriber == 'yes') checked @endif id="customSwitch4">
                    <label class="custom-control-label switch" for="customSwitch4">{{ trans('general.someone_subscribed_content') }}</label>
                  </div>

                  <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="email_new_tip" value="yes" @if (auth()->user()->email_new_tip == 'yes') checked @endif id="customSwitch7">
                    <label class="custom-control-label switch" for="customSwitch7">{{ trans('general.someone_sent_tip') }}</label>
                  </div>

                  <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" name="email_new_ppv" value="yes" @if (auth()->user()->email_new_ppv == 'yes') checked @endif id="customSwitch8">
                    <label class="custom-control-label switch" for="customSwitch8">{{ trans('general.someone_bought_my_content') }}</label>
                  </div>
                @endif


                <div class="custom-control custom-switch">
                  <input type="checkbox" class="custom-control-input" name="notify_email_new_post" value="yes" @if (auth()->user()->notify_email_new_post == 'yes') checked @endif id="customSwitch6">
                  <label class="custom-control-label switch" for="customSwitch6">{{ trans('general.new_post_creators_subscribed') }}</label>
                </div>

                <button type="submit" id="save" data-msg-success="{{ trans('admin.success_update') }}" class="btn btn-primary btn-sm mt-3 w-100" data-msg="{{trans('admin.save')}}">
                  {{trans('admin.save')}}
                </button>

            </form>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div><!-- End Modal new Message -->
@endsection
