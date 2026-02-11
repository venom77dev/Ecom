<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PostOfficeController extends Controller
{
    public function getPostOfficeByPincode($pincode)
    {
        // File path
        $filePath = storage_path('pincodes_of_India.csv');


        // Open the file and read its contents
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Read CSV file header
            $header = fgetcsv($handle);
            // Iterate through each row
            while (($row = fgetcsv($handle)) !== false) {
                // Check if the pincode matches
                if (trim($row[1]) == $pincode) {
                    // Map the row to the header
                    $postOfficeData = array_combine($header, $row);
                    return response()->json($postOfficeData);
                }
            }

            fclose($handle);
        }

        // If not found, return error response
        return response()->json(['message' => 'Post office not found.'], 404);
    }
}
