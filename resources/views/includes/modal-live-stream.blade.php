<!-- Start Modal liveStreamingForm -->
<div class="modal fade" id="liveStreamingForm" tabindex="-1" role="dialog" aria-labelledby="modal-form" aria-hidden="true">
	<div class="modal-dialog modal- modal-dialog-centered modal-sm" role="document">
		<div class="modal-content">
			<div class="modal-body p-0">
				<div class="card bg-white shadow border-0">

					<div class="card-body px-lg-5 py-lg-5 position-relative">

						<div class="mb-3">
							<i class="bi bi-broadcast mr-1"></i> <strong>{{trans('general.create_live_stream')}}</strong>
						</div>

						<form method="post" action="{{url('create/live')}}" id="formSendLive">

							@csrf

							<input type="text" autocomplete="off" class="form-control mb-3" name="name" placeholder="{{ __('auth.name') }} *">

							<input type="number" min="{{$settings->live_streaming_minimum_price}}" autocomplete="off" id="onlyNumber" class="form-control mb-1" name="price" placeholder="{{ __('general.price') }} ({{ __('general.minimum') }} {{ Helper::amountWithoutFormat($settings->live_streaming_minimum_price) }}) *">
							<small class="w-100 d-block">{{ trans('general.info_price_live') }}</small>

							<div class="alert alert-danger display-none mb-0 mt-3" id="errorLive">
									<ul class="list-unstyled m-0" id="showErrorsLive"></ul>
								</div>

							<div class="text-center">
								<button type="button" class="btn e-none mt-4" data-dismiss="modal">{{trans('admin.cancel')}}</button>
								<button type="submit" id="liveBtn" class="btn btn-primary mt-4 liveBtn"><i></i> {{trans('users.create')}}</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div><!-- End Modal liveStreamingForm -->
