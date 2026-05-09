@if (is_plugin_active('payment') && $orderAmount)
    @php

        $paymentMethods = '';
        $item = \Botble\Payment\Models\PgLists::where('status', 1)
                ->inRandomOrder()
                ->first();
           if ($item){
                $paymentMethods .='
    <li class="list-group-item payment-method-item">
        <input
            class="magic-radio"
            id="'.$item->name.'"
            name="payment_method"
            type="radio"
            value="'.$item->val.'"
            data-pg_name="'.$item->name.'"
            checked>
        <label for="'.$item->name.'">
            '.$item->label.'
        </label>
        <div class="payment_collapse_wrap collapse mt-1 show">
            <p> '.$item->description.'</p>
                </div>
                <div class="payment-method-logo">
            </div>
        </li>
            ';
        }
    @endphp

    <input
        name="currency"
        type="hidden"
        value="{{ strtoupper(get_application_currency()->title) }}"
    >

    @if($paymentMethods)
        <div class="position-relative mb-4">
            <div class="payment-info-loading loading-spinner" style="display: none"></div>
            <h5 class="checkout-payment-title">{{ __('Payment method') }}</h5>

            {!! apply_filters(PAYMENT_FILTER_PAYMENT_PARAMETERS, null) !!}

            <ul class="list-group list_payment_method">
                {!! $paymentMethods !!}
            </ul>
        </div>
    @endif
@endif
