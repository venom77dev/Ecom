@extends('plugins/ecommerce::orders.master')

@section('title', __('Checkout'))

@section('content')
    @if (Cart::instance('cart')->isNotEmpty())
        @if (is_plugin_active('payment') && $orderAmount)
            @include('plugins/payment::partials.header')
        @endif

        <x-core::form
            :url="route('public.checkout.process', $token)"
            id="checkout-form"
            class="checkout-form payment-checkout-form"
            :data-update-url="route('public.ajax.checkout.update')"
        >
            <input id="checkout-token" name="checkout-token" type="hidden" value="{{ $token }}">

            <div class="row" id="main-checkout-product-info">
                <div class="order-1 order-md-2 col-lg-5 col-md-6">
                    <div class="d-block d-sm-none">
                        @include('plugins/ecommerce::orders.partials.logo')
                    </div>
                    <div class="position-relative" id="cart-item">
                        @include('plugins/ecommerce::orders.partials.amount')
                    </div>

                    <div class="mt-3 mb-5">
                        @include(EcommerceHelper::viewPath('discounts.partials.form'), compact('discounts'))
                    </div>
                </div>

                <div class="col-lg-7 col-md-6">
                    <div class="d-none d-sm-block">
                        @include('plugins/ecommerce::orders.partials.logo')
                    </div>

                    <div class="form-checkout">
                        {!! apply_filters('ecommerce_checkout_form_before', null, $products) !!}

                        @if ($isShowAddressForm)
                            <div class="mb-4">
                                <h5 class="checkout-payment-title">{{ __('Shipping information') }}</h5>

                                <input
                                    id="save-shipping-information-url"
                                    type="hidden"
                                    value="{{ route('public.checkout.save-information', $token) }}"
                                >

                                @include(
                                    'plugins/ecommerce::orders.partials.address-form',
                                    compact('sessionCheckoutData')
                                )
                            </div>

                            {!! apply_filters('ecommerce_checkout_form_after_shipping_address_form', null, $products) !!}
                        @endif

                        @if (EcommerceHelper::isBillingAddressEnabled())
                            <div class="mb-4">
                                <h5 class="checkout-payment-title">{{ __('Billing information') }}</h5>

                                @include(
                                    'plugins/ecommerce::orders.partials.billing-address-form',
                                    compact('sessionCheckoutData')
                                )
                            </div>

                            {!! apply_filters('ecommerce_checkout_form_after_billing_address_form', null, $products) !!}
                        @endif

                        @if (! is_plugin_active('marketplace'))
                            @if (Arr::get($sessionCheckoutData, 'is_available_shipping', true))
                                <div class="shipping-method-wrapper mb-4 d-none">
                                    <h5 class="checkout-payment-title">{{ __('Shipping method') }}</h5>
                                    <div class="shipping-info-loading loading-spinner" style="display: none;"></div>

                                    <div data-bb-toggle="checkout-shipping-methods-area">
                                        @include('plugins/ecommerce::orders.partials.shipping-methods')
                                    </div>
                                </div>

                                {!! apply_filters('ecommerce_checkout_form_after_shipping_address_form', null, $products) !!}
                            @endif
                        @endif

                        {!! apply_filters('ecommerce_checkout_form_before_payment_form', null, $products) !!}

                        <input
                            name="amount"
                            type="hidden"
                            value="{{ format_price($orderAmount, null, true) }}"
                            id="pg_amount"
                        >

                        <div data-bb-toggle="checkout-payment-methods-area">
                            @include('plugins/ecommerce::orders.partials.payment-methods')
                        </div>

                        {!! apply_filters('ecommerce_checkout_form_after_payment_form', null, $products) !!}

                        <div @class(['form-group mb-3', 'has-error' => $errors->has('description')])>
                            <label class="form-label" for="description">{{ __('Order notes') }}</label>
                            <textarea
                                class="form-control"
                                id="description"
                                name="description"
                                rows="3"
                                placeholder="{{ __('Notes about your order, e.g. special notes for delivery.') }}"
                            >{{ old('description') }}</textarea>
                            {!! Form::error('description', $errors) !!}
                        </div>

                        @if (EcommerceHelper::getMinimumOrderAmount() > $rawTotal)
                            <div role="alert" class="alert alert-warning">
                                {{ __('Minimum order amount is :amount, you need to buy more :more to place an order!', ['amount' => format_price(EcommerceHelper::getMinimumOrderAmount()), 'more' => format_price(EcommerceHelper::getMinimumOrderAmount() - Cart::instance('cart')->rawSubTotal())]) }}
                            </div>
                        @endif

                        @if (EcommerceHelper::isDisplayTaxFieldsAtCheckoutPage())
                            @include(
                                'plugins/ecommerce::orders.partials.tax-information',
                                compact('sessionCheckoutData')
                            )

                            {!! apply_filters('ecommerce_checkout_form_after_tax_information_form', null, $products) !!}
                        @endif

                        @if($privacyPolicyUrl = theme_option('ecommerce_term_and_privacy_policy_url'))
                            <div class="form-check ps-0 mb-3">
                                <input
                                    id="agree_terms_and_policy"
                                    name="agree_terms_and_policy"
                                    type="checkbox"
                                    value="1"
                                    @checked (old('agree_terms_and_policy', true))
                                >
                                <label class="form-check-label" for="agree_terms_and_policy">
                                    {!! BaseHelper::clean(__(
                                        'I agree to the :link',
                                        ['link' => Html::link($privacyPolicyUrl, __('Terms and Privacy Policy'), attributes: ['class' => 'text-decoration-underline', 'target' => '_blank'])]
                                    )) !!}
                                </label>
                            </div>
                        @endif

                        {!! apply_filters('ecommerce_checkout_form_after', null, $products) !!}

                        <div class="row align-items-center g-3 mb-5 position-relative">
                            <div class="loading-spinner"  id="checkout_pg_loader_raz" style="display: none;"></div>
                            <div class="order-2 order-md-1 col-md-6 text-center text-md-start mb-4 mb-md-0">
                                <a class="d-flex align-items-center gap-1" href="{{ route('public.cart') }}">
                                    <x-core::icon name="ti ti-arrow-narrow-left" />
                                    <span class="d-inline-block back-to-cart">{{ __('Back to cart') }}</span>
                                </a>

                                {!! apply_filters('ecommerce_checkout_form_after_back_to_cart_link', null, $products) !!}
                            </div>
                            @php
                                $agent = new \Jenssegers\Agent\Agent();
                                $isMobile = $agent->isMobile();
                            @endphp
                            <div class="order-1 order-md-2 col-md-6">
                                <input
                                    type="hidden"
                                    name="ref_order_id"
                                    id="order_id_el"
                                    value=""
                                >
                                <input
                                    type="hidden"
                                    name="ref_pg_name"
                                    id="ref_pg_name_el"
                                    value=""
                                >
                                <input
                                    type="hidden"
                                    name="razorpay_payment_id"
                                    id="razorpay_payment_id"
                                    value=""
                                >
                                <input
                                    type="hidden"
                                    name="razorpay_order_id"
                                    id="razorpay_order_id"
                                    value=""
                                >
                                <input
                                    type="hidden"
                                    name="razorpay_signature"
                                    id="razorpay_signature"
                                    value=""
                                >
                                @if(!$isMobile)
                                    <button
                                        class="btn payment-checkout-btn-step float-end"
                                        type="button"
                                        data-processing-text="{{ __('We Are Checking Your Payment...') }}"
                                        onclick="getQr()"
                                    >
                                        {{ __('Checkout') }}
                                    </button>
                                @else
                                    <button
                                        class="btn payment-checkout-btn-step float-end"
                                        type="button"
                                        onclick="getLink()"
                                        data-processing-text="{{ __('Processing. Please wait...') }}"
                                        id="getLinkBtn"
                                    >
                                        <span id="loadingIcon" class="spinner-border spinner-border-sm me-2" role="status" style="display: none;"></span>
                                        Pay Now
                                    </button>
                                    <button
                                        class="btn payment-checkout-btn payment-checkout-btn-step mb-2 float-end"
                                        data-processing-text="{{ __('We Are Checking Your Payment...') }}"
                                        data-error-header="{{ __('Error') }}"
                                        type="submit"
                                        id="check_payment_status_el"
                                        style="display: none;"
                                    >
                                        Check Payment Status
                                    </button>
                                @endif
                            </div>


                            <div class="modal fade" id="dynamic_qr_generated" tabindex="-1" aria-labelledby="dynamic_qr_generated" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered position-relative">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title fs-6" id="pg_qr_title">
                                                Wanting For QR Code Generating....
                                            </h4>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="checkout">
                                                <div class="loading-spinner" style="display: none;" id="checkout_pg_loader"></div>
                                                <div class="cp">
                                                    <div class="cp1">
                                                        <div class="cp2">
                                                            <div class="row">
                                                                <div class="col-6">
                                                                    <div class="am">
                                                                        <h3>Amount</h3>
                                                                        <p>₹ <span id="pg_amount_text">0.00</span></p>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6">
                                                                    <div class="am text-end">

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="cp4">
                                                        <div class="cp4pad text-center">
                                                            <h4 class="cardheadtitle">Scan QR To Pay</h4>
                                                            <div class="payqr" id="qrCode">
                                                            </div>
                                                        </div>
                                                        <div class="cp5">
                                                            <div class="text-center">
                                                                <div>
                                                                    <button
                                                                        class="btn payment-checkout-btn payment-checkout-btn-step mb-2"
                                                                        data-processing-text="{{ __('We Are Checking Your Payment...') }}"
                                                                        data-error-header="{{ __('Error') }}"
                                                                        type="submit"
                                                                    >
                                                                        Check Payment Status
                                                                    </button>
                                                                </div>

                                                            </div>
                                                            <div class="text-center">
                                                                <p class="fs14 pb-3">Please use your UPI Apps to scan the QR code in order to complete the payment. After Payment Done Please Click Button To Verify Transaction.</p>
                                                            </div>
                                                            <div class="d-flex align-items-center justify-content-around">
                                                                <div class="fticonmain">
                                                                    <div class="fticon"><img src="{{asset('custom/img/secure.svg')}}" /></div>
                                                                    <div class="ftcontent">
                                                                        <h5>Secure</h5>
                                                                        <p>Checkout</p>
                                                                    </div>
                                                                </div>
                                                                <div class="fticonmain">
                                                                    <div class="fticon"><img src="{{asset('custom/img/satisfaction.svg')}}" /></div>
                                                                    <div class="ftcontent">
                                                                        <h5>Satisfaction</h5>
                                                                        <p>Guarantee</p>
                                                                    </div>
                                                                </div>
                                                                <div class="fticonmain">
                                                                    <div class="fticon"><img src="{{asset('custom/img/privacy.svg')}}" /></div>
                                                                    <div class="ftcontent">
                                                                        <h5>Privacy</h5>
                                                                        <p>Protected</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="dynamic_redirect_link" tabindex="-1" aria-labelledby="dynamic_redirect_link" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered position-relative">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title fs-6" id="pg_qr_title_4">
                                                Wanting For Payment Link....
                                            </h4>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="checkout">
                                                <div class="loading-spinner" style="display: none;" id="checkout_pg_loader_1"></div>
                                                <div class="cp">
                                                    <div class="cp1">
                                                        <div class="cp2">
                                                            <div class="row">
                                                                <div class="col-6">
                                                                    <div class="am">
                                                                        <h3>Amount</h3>
                                                                        <p>₹ <span id="pg_amount_text_2">0.00</span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="cp4">
                                                        <div class="payqr text-center" id="qrCode_1">
                                                        </div>
                                                        <div class="cp5">
                                                            <div class="text-center">
                                                                <div>
                                                                    <button
                                                                        class="btn payment-checkout-btn payment-checkout-btn-step mb-2"
                                                                        data-processing-text="{{ __('We Are Checking Your Payment...') }}"
                                                                        data-error-header="{{ __('Error') }}"
                                                                        type="submit"
                                                                        id="redirect_btn"
                                                                    >
                                                                        Process Payment
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal fade" id="razorpay_waiting" tabindex="-1" aria-labelledby="razorpay_waiting" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered position-relative">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title fs-6" id="pg_qr_title">
                                                Wanting For Redirect....
                                            </h4>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="checkout">
                                                <div class="loading-spinner" style="display: none;" id="checkout_pg_loader"></div>
                                                <div class="cp">

                                                    <div class="message-container">
                                                        <h1>Your Transaction is In Process</h1>
                                                        <p>Please do not refresh or back button.</p>
                                                        <div class="loader"></div>
                                                        <p>You will be automatically redirected shortly...</p>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-core::form>

        @if (is_plugin_active('payment'))
            @include('plugins/payment::partials.footer')
        @endif
    @else
        <div class="container">
            <div class="alert alert-warning my-5">
                <span>{!! __('No products in cart. :link!', ['link' => Html::link(BaseHelper::getHomepageUrl(), __('Back to shopping'))]) !!}</span>
            </div>
        </div>
    @endif
@stop
@push('footer')
    <script type="text/javascript" src="{{ asset('vendor/core/core/js-validation/js/js-validation.js') }}"></script>

    {!! JsValidator::formRequest(
        Botble\Ecommerce\Http\Requests\SaveCheckoutInformationRequest::class,
        '#checkout-form',
    ) !!}
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        let currentUrl = window.location.href;
        let urlObject = new URL(currentUrl);
        let params = new URLSearchParams(urlObject.search);
        let pg = getQueryParam(params,'pg');
        let slug = getQueryParam(params,'slug');
        if (pg && pg == 'Razorpay' && slug == 'redirect') {
            $('#razorpay_waiting').modal("show");
            let pgNameToSelect = 'Razorpay';
            $('input[name="payment_method"][data-pg_name="' + pgNameToSelect + '"]').prop('checked', true);
            $('#ref_pg_name_el').val(pg);
            $('#razorpay_payment_id').val(getQueryParam(params,'razorpay_payment_id'));
            $('#razorpay_order_id').val(getQueryParam(params,'razorpay_order_id'));
            $('#razorpay_signature').val(getQueryParam(params,'razorpay_signature'));
            $('#checkout-form').trigger('submit');
        }
    </script>
    @include('zip_code_fill')
@endpush

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script type="text/javascript">
    function getQueryParam(params, slug) {
        if (params.has(slug)) {
            return params.get(slug);
        } else {
            return null; // Or return a default value, like an empty string or undefined
        }
    }
    function getQr(){
        if($('input[name="payment_method"]:checked').data('pg_name') == 'COD'){
            $('#checkout-form').trigger('submit');
            return;
        }
        if ($("#address_name").is(":visible")) {
            let isValid = checkValidation();
            if(!isValid){
                $('#checkout-form').trigger('submit');
                return;
            }
        }
        let PGName = $('input[name="payment_method"]:checked').data('pg_name');
        if (PGName == "Razorpay"){
            razModel();
            return;
        }
        $('#dynamic_qr_generated').modal('show');
        let data = {
            amount: $('#pg_amount').val(),
            pg: $('input[name="payment_method"]:checked').data('pg_name')
        };
        $('#checkout_pg_loader').show();
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        $('#ref_pg_name_el').val($('input[name="payment_method"]:checked').data('pg_name'));
        axios.post('{{route('starpaisa.payment.qr')}}', data)
            .then(function (response) {
                $('#checkout_pg_loader').hide();
                if(response.data && response.data.status === true){
                    $('#order_id_el').val(response.data.data.order_id);
                    const qrCodeBase64 = response.data.data.qr_code;
                    document.getElementById('qrCode').innerHTML = `${qrCodeBase64}"`;
                    $('#pg_amount_text').html($('#pg_amount').val());
                    $('#pg_qr_title').html('Scan QR To Pay');
                }
            })
            .catch(function () {
                $('#pg_amount_text').html($('#pg_amount').val());
                $('#checkout_pg_loader').hide();
                document.getElementById('qrCode').innerHTML = `<span class="text-danger fw-bold">Failed To Generate QR Code!!</span>`;
            });
    }
    function getLink(){
        if($('input[name="payment_method"]:checked').data('pg_name') == 'COD'){
            $('#checkout-form').trigger('submit');
            return;
        }
        if ($("#address_name").is(":visible")) {
            let isValid = checkValidation();
            if(!isValid){
                $('#checkout-form').trigger('submit');
                return;
            }
        }
        let data = {
            amount: $('#pg_amount').val(),
            pg: $('input[name="payment_method"]:checked').data('pg_name')
        };
        let PGName = $('input[name="payment_method"]:checked').data('pg_name');
        if (PGName == "Razorpay"){
            razModel();
            return;
        }
        let $btn = $('#getLinkBtn');
        let $cBtn = $('#check_payment_status_el');
        let $loadingIcon = $('#loadingIcon');
        $loadingIcon.show();
        $btn.prop('disabled', true);
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        $('#ref_pg_name_el').val($('input[name="payment_method"]:checked').data('pg_name'));
        axios.post('{{route('starpaisa.payment.link')}}', data)
            .then(function (response) {
                $btn.hide();
                $cBtn.show();
                if(response.data && response.data.status === true){
                    $('#order_id_el').val(response.data.data.order_id);
                    window.location.href = response.data.data.link;
                }
            })
            .catch(function () {

            }).finally(function () {
            $btn.prop('disabled', false);
            $loadingIcon.hide();
        });
    }
    function checkValidation(){
        let allFilled = true;
        $(".check_validation_el").each(function() {
            if ($(this).val() == "" || $(this).hasClass('is-invalid')) {
                allFilled =  false;
            }
        });
        return allFilled;
    }
    function razModel(){
        let $cBtn = $('#checkout_pg_loader_raz');
        $cBtn.show();
        let data = {
            current_url: window.location.href,
            amount: $('#pg_amount').val(),
            pg: $('input[name="payment_method"]:checked').data('pg_name')
        };
        axios.post('{{route('starpaisa.payment.raz')}}', data)
            .then(function (response) {
                $cBtn.hide();
                if(response.data && response.data.status === true){
                    const resData = response.data.data;
                    var options = {
                        key: resData.key,
                        amount: resData.amount,
                        currency: resData.currency,
                        name: resData.name,
                        description: resData.description,
                        image: resData.image,
                        order_id: resData.order_id,
                        theme:
                            {
                                "color": resData.color_code
                            },
                        callback_url: resData.callback_url
                    };
                    var rzp = new Razorpay(options);
                    rzp.open();
                }
            })
            .catch(function () {
                $cBtn.hide();
            });
    }
</script>
@push('header')
    <style>

        .checkout {
            background: #fff;
            box-shadow: 0px 4px 12px 0px rgba(0, 0, 0, 0.03);
        }
        .width100 {
            width: 100% !important;
        }
        .cp1 {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .cp2 {
            background: #ffffff;
            border-radius: 10px;
            padding: 15px;
        }
        .am h3 {
            font-size: 12px;
            font-weight: normal;
            margin-bottom: 5px;
        }
        .am p {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 0;
        }
        .cp4 {
            background: #f8f8f8;
        }
        .cp4pad {
            padding: 15px;
        }
        .cp4 .cardheadtitle {
            font-size: 14px;
        }
        .payqr img {
            width: 125px;
        }
        .cp5 {
            background: #ffffff;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 15px;
        }
        .cp4 .cardheadtitle {
            font-size: 12px;
        }
        .payicon {
            margin-bottom: 7px;
        }
        .payicon img {
            height: 30px;
            width: 100%;
        }
        .text-center {
            text-align: -webkit-center !important;
        }
        .cp5 p {
            color: #000;
            opacity: 0.9;
            max-width: 360px;
            margin: auto;
        }
        .fs14 {
            font-size: 12px;
        }
        .w-45 {
            width: 48%;
        }
        .btn1 {
            border-radius: 10px;
            background: #1077ff;
            height: 40px;
            padding: 6px 12px;
            justify-content: center;
            align-items: center;
            border: 0;
            outline: none;
            cursor: pointer;
            overflow: hidden;
            color: #fff;
            text-align: center;
            font-feature-settings: "clig" off, "liga" off;
            text-overflow: ellipsis;
            font-family: "DM Sans", sans-serif;
            font-size: 14px;
            font-style: normal;
            font-weight: 700;
            line-height: normal;
        }
        .btn2 {
            border-radius: 10px;
            background: #fff;
            height: 40px;
            padding: 6px 12px;
            justify-content: center;
            align-items: center;
            border: 1px solid #1077ff;
            outline: none;
            cursor: pointer;
            overflow: hidden;
            color: #1077ff;
            text-align: center;
            font-feature-settings: "clig" off, "liga" off;
            text-overflow: ellipsis;
            font-family: "DM Sans", sans-serif;
            font-size: 14px;
            font-style: normal;
            font-weight: 700;
            line-height: normal;
        }
        .fticonmain {
            display: flex;
            align-items: center;
            margin-top: 25px;
        }
        .fticon img {
            width: 20px;
            height: 20px;
        }
        .ftcontent {
            margin-left: 7px;
        }
        .ftcontent h5 {
            font-size: 12px;
        }
        .ftcontent p,
        .ftcontent h5 {
            margin-bottom: 0;
            color: #9c9c9c;
        }
        .ftcontent p {
            font-size: 10px;
        }
        .pm {
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding-bottom: 15px;
        }
        .pmitem.active {
            border: 1px solid #1077ff;
        }
        .pmitem {
            background: white;
            border-radius: 5px;
            border: 1px solid #ddd;
            cursor: pointer;
            box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 12px;
        }
        .pmitem img {
            max-width: 100%;
            height: 40px;
            vertical-align: middle;
        }
        .message-container {
            text-align: center;
            padding: 20px;
            border: 1px solid #ddd;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .message-container h1 {
            color: #333;
            font-size: 24px;
        }
        .message-container p {
            color: #666;
            font-size: 18px;
        }
        .loader {
            border: 6px solid #f3f3f3;
            border-top: 6px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        input[readonly] {
            background-color: #e9ecef; /* Similar to disabled background */
            cursor: not-allowed;       /* Change the cursor to indicate it's not editable */
            opacity: 1;                /* Ensure full opacity */
        }

        input[readonly]:focus {
            outline: none;             /* Remove focus outline */
        }
    </style>
@endpush
