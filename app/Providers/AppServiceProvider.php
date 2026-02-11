<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        Validator::extend('validate_pincode', function ($attribute, $value, $parameters, $validator) {
            // Path to the CSV file
            $filePath = storage_path('pincodes_of_India.csv');

            // Open the CSV file
            if (($handle = fopen($filePath, 'r')) !== false) {
                // Skip the header row
                fgetcsv($handle);

                // Loop through each row
                while (($row = fgetcsv($handle)) !== false) {
                    if (trim($row[1]) == $value) {
                        fclose($handle);
                        return true;
                    }
                }

                fclose($handle);
            }
            return false;
        });

        // Add a custom error message for the rule
        Validator::replacer('validate_pincode', function ($message, $attribute, $rule, $parameters) {
            return 'The provided pincode is invalid.';
        });
    }
}
