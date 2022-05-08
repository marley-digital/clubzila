<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="_token" content="{!! csrf_token() !!}"/>
    <title>{{trans('general.invoice')}} #{{str_pad($data->id, 4, "0", STR_PAD_LEFT)}}</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    @include('includes.css_admin')

    <!-- AdminLTE Skins. Choose a skin from the css/skins
        folder instead of downloading all of them to reduce the load. -->
    <link rel="shortcut icon" href="{{ url('public/img', $settings->favicon) }}" />

    <link href='https://fonts.googleapis.com/css?family=Montserrat:700' rel='stylesheet' type='text/css'>

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>

  <body class="skin-purple-light sidebar-mini bg-whitesmoke">
    <div class="wrapper">
  <!-- Main content -->
  <section class="invoice">
    <!-- title row -->
    <div class="row">
      <div class="col-xs-12">
        <h2 class="page-header">
          <img src="{{ url('public/img', $settings->logo_2)}}" width="110"><br>
          {{$settings->company}}
          <small class="pull-right">{{trans('admin.date')}}: {{Helper::formatDate($data->created_at)}}</small>
        </h2>
      </div>
      <!-- /.col -->
    </div>
    <!-- info row -->
    <div class="row invoice-info">
      <div class="col-sm-4 invoice-col">
        {{trans('general.from')}}
        <address>
          <strong>{{$settings->company}}</strong><br>
          {{$settings->address}} <br>
          {{$settings->city}} {{$settings->zip}}<br>
          {{$settings->country}}<br>
          {{trans('auth.email')}}: {{$settings->email_admin}}<br>
          @if ($settings->vat)
            {{trans('general.vat')}}: {{$settings->vat}}
          @endif
        </address>
      </div>
      <!-- /.col -->
      <div class="col-sm-4 invoice-col">
        {{trans('general.to')}}
        <address>
          <strong>{{$data->user()->name}} {{$data->user()->company != '' ? '- '.$data->user()->company : null }}</strong><br>
          {{$data->user()->address}}<br>
          {{$data->user()->city}}, {{$data->user()->zip}}<br>
          {{$data->user()->country()->country_name}} <br>
          {{trans('auth.email')}}: {{$data->user()->email}}
        </address>
      </div>
      <!-- /.col -->
      <div class="col-sm-4 invoice-col">
        <b>{{trans('general.invoice')}} #{{str_pad($data->id, 4, "0", STR_PAD_LEFT)}}</b><br>
        <b>{{trans('general.payment_due')}}</b> {{Helper::formatDate($data->created_at)}}<br>
      </div>
      <!-- /.col -->
    </div>
    <!-- /.row -->

    <!-- Table row -->
    <div class="row">
      <div class="col-xs-12 table-responsive">
        <table class="table table-striped">
          <thead>
          <tr>
            <th>{{trans('general.qty')}}</th>
            <th>{{trans('general.description')}}</th>
            <th>{{trans('general.subtotal')}}</th>
          </tr>
          </thead>
          <tbody>
          <tr>
            <td>1</td>

            @if ($data->type == 'subscription')
              <td>{{trans('general.subscription_desc_buy').' @'.$data->subscribed()->username}}</td>
            @elseif ($data->type == 'ppv')
              <td>{{trans('general.ppv').' @'.$data->subscribed()->username}}</td>
            @else
              <td>{{trans('general.single_payment').' ('.trans('general.tip').') @'.$data->subscribed()->username}}</td>
            @endif

            <td>{{Helper::amountFormatDecimal($data->amount)}} {{ $settings->currency_code }}</td>
          </tr>
          </tbody>
        </table>
      </div>
      <!-- /.col -->
    </div>
    <!-- /.row -->

    <div class="row">
      <!-- /.col -->
      <div class="col-xs-6"></div>
      <!-- /.col -->
      <div class="col-xs-6">
        <div class="table-responsive">
          <table class="table">
            <tr>
              <th class="w-50">{{trans('general.subtotal')}}:</th>
              <td>{{Helper::amountFormatDecimal($data->amount)}} {{ $settings->currency_code }}</td>
            </tr>
            <tr class="h4">
              <th>{{trans('general.total')}}:</th>
              <td><strong>{{Helper::amountFormatDecimal($data->amount)}} {{ $settings->currency_code }}</strong></td>
            </tr>
          </table>
        </div>
      </div>
      <!-- /.col -->
    </div>
    <!-- /.row -->
    <div class="row no-print">
        <div class="col-xs-12">
          <a href="javascript:void(0);" onclick="window.print();" class="btn btn-default"><i class="fa fa-print"></i> {{trans('general.print')}}</a>
        </div>
      </div>
  </section>
  <!-- /.content -->
</div>
<!-- ./wrapper -->

  </body>
</html>
