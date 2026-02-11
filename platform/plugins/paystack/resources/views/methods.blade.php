@if (get_payment_setting('status', PAYSTACK_PAYMENT_METHOD_NAME) == 1)
    <x-plugins-payment::payment-method
        :name="PAYSTACK_PAYMENT_METHOD_NAME"
        paymentName="Paystack"
    >

    </x-plugins-payment::payment-method>
@endif
