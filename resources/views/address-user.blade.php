<script>

    document.addEventListener("DOMContentLoaded", function() {
        // Hide the label if input is not empty

        document.getElementById('address_zip_code').addEventListener("focusout", function() {
            // Get the pincode value from the input
            var pincode = this.value.trim();

            // Proceed if pincode is not empty
            if (pincode !== '') {
                // Make an API call to the Laravel backend
                fetch(`/get-post-office/${pincode}`)
                    .then(response => response.json())
                    .then(data => {
                        let cityElement = document.getElementById('city');
                        let stateElement = document.getElementById('state');

                        if (cityElement && stateElement && data['State']) { // Check if elements exist
                            cityElement.value = data['City Name'];
                            stateElement.value = data['State'];
                        }else {
                            cityElement.value = '';
                            stateElement.value = '';
                        }
                        // document.getElementById('zip_process').style.display = 'none';

                        // if (cityElement && cityElement.value.trim() !== '') {
                        //     document.getElementById('address_city_l').style.display = 'none';
                        // }
                        // if (stateElement && stateElement.value.trim() !== '') {
                        //     document.getElementById('address_state_l').style.display = 'none';
                        // }
                    })
                    .catch(error => {
                        let cityElement = document.getElementById('city');
                        let stateElement = document.getElementById('state');

                        if (cityElement) cityElement.value = '';
                        if (stateElement) stateElement.value = '';

                    });
            }
        });
    });

</script>
<style>
    input[readonly] {
        background-color: #e9ecef; /* Similar to disabled background */
        cursor: not-allowed;       /* Change the cursor to indicate it's not editable */
        opacity: 1;                /* Ensure full opacity */
    }

    input[readonly]:focus {
        outline: none;             /* Remove focus outline */
    }
</style>
