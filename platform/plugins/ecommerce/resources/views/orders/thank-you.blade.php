@extends('plugins/ecommerce::orders.master')

@section('title', __('Order successfully. Order number :id', ['id' => $order->code]))

@section('content')
    <div class="row">
        <div class="col-lg-7 col-md-6 col-12">
            @include('plugins/ecommerce::orders.partials.logo')

            <div class="thank-you">
                @if($order->payment->status != 'completed')
                    <x-core::icon name="ti ti-clock" />
                @else
                    <x-core::icon name="ti ti-circle-check-filled" />
                @endif
                <div class="d-inline-block">
                    @if($order->payment->status != 'completed')
                        <h3 class="thank-you-sentence">
                            {{ __('Your order is Pending') }}
                        </h3>
                        <p>{{ __('Thank you for showing interest in our product!') }}</p>
                    @else
                        <h3 class="thank-you-sentence">
                            {{ __('Your order is successfully placed') }}
                        </h3>
                        <p>{{ __('Thank you for purchasing our products!') }}</p>
                    @endif
                </div>
            </div>

            @include('plugins/ecommerce::orders.thank-you.customer-info', compact('order'))

            <a class="btn payment-checkout-btn" href="{{ BaseHelper::getHomepageUrl() }}">
                {{ __('Continue shopping') }}
            </a>
        </div>
        <div class="col-lg-5 col-md-6 d-none d-md-block mt-5 mt-md-0 mb-5">

            @include('plugins/ecommerce::orders.thank-you.order-info')

            <hr>

            @include('plugins/ecommerce::orders.thank-you.total-info', ['order' => $order])
        </div>
    </div>
@stop
