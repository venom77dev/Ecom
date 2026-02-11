@php
    use Botble\Payment\Enums\PaymentMethodEnum;
    use Botble\Payment\Models\Payment;
@endphp

@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')

    <div class="my-5 d-block d-md-flex">
        <div class="col-12 col-md-9">
            @include('custom_pg_table')
        </div>
    </div>

@endsection

@push('footer')
    <x-core::modal.action
        id="confirm-disable-payment-method-modal"
        :title="trans('plugins/payment::payment.deactivate_payment_method')"
        :description="trans('plugins/payment::payment.deactivate_payment_method_description')"
        :submit-button-attrs="['id' => 'confirm-disable-payment-method-button']"
        :submit-button-label="trans('plugins/payment::payment.agree')"
    />
@endpush
