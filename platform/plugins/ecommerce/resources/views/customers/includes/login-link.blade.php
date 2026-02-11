<div class="mt-3 text-center">
    {{ __('Already have an account?') }}<a href="{{ route('customer.login') }}" class="ms-1 text-decoration-underline">{{ __('Login') }}</a>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // JavaScript to dynamically add button next to input and add d-flex class
    window.onload = function() {
        // Get the input field by its ID
        var phoneInput = document.getElementById("reg_phone_number");

        // Get the nearest .position-relative container (the parent div)
        var positionRelativeDiv = phoneInput.closest('.position-relative');

        // Add the 'd-flex' class to the parent div
        if (positionRelativeDiv) {
            positionRelativeDiv.classList.add('d-flex');
        }

        // Create a new button element
        var button = document.createElement("button");
        button.type = "button"; // Set button type to button
        button.innerHTML = "Get Otp"; // Set button text
        button.className = "btn btn-primary ms-2"; // Add Bootstrap classes for styling and margin
        button.setAttribute('id', 'get_otp_button');

        // Insert the button after the input field
        phoneInput.parentNode.appendChild(button);


        button.addEventListener('click', function() {
            var phoneNumber = phoneInput.value; // Get the phone number value

            // Validate phone number
            if (phoneNumber.trim() === "") {

                return;
            }

            // Disable button and show loading state
            button.disabled = true;
            button.innerHTML = "Sending...";

            // Send OTP via AJAX
            $.ajax({
                url: '/send-otp', // The Laravel API endpoint
                type: 'POST',
                data: {
                    mobile: phoneNumber
                },
                success: function() {
                    startResendTimer(button, 60);
                },
                error: function() {
                    button.disabled = false;
                    button.innerHTML = "Get Otp";
                }
            });
        });

        // Function to handle 60-second countdown timer
        function startResendTimer(button, seconds) {
            var countdown = seconds;
            var interval = setInterval(function() {
                if (countdown === 0) {
                    clearInterval(interval);
                    button.disabled = false;
                    button.innerHTML = "Resend OTP"; // Enable the button after countdown
                } else {
                    button.innerHTML = "Resend OTP in " + countdown + "s";
                    countdown--;
                }
            }, 1000);
        }
    };
</script>
<style>
    #reg_phone_number-error{
        position: absolute;
        top: 50px;
    }
</style>
