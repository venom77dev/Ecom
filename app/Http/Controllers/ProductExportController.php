<?php

namespace App\Http\Controllers;

use App\Classes\TelegramBot;
use App\Classes\TelegramResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;

class ProductExportController extends Controller
{
    public $productList = [];
    public $productKey = 'NEW_PROD_KEY_3_23_v111_';

    public $min = 100;
    public $max = 100000;
    public $x_rapidapi_host = '';
    public $x_rapidapi_key = '';

    public function showForm()
    {
        return view('product_export');
    }

    public function exportProducts(Request $request)
    {
        try {
            $request->validate([
                'category' => 'required|array',
                'category.*' => 'required|string|max:255',
                'x-rapidapi-key' => 'required|string|max:255',
                'x-rapidapi-host' => 'required|string|max:255',
                'domain_name' => 'required|string|max:255',
                'secure_key' => 'required|string|max:255',
                'count' => 'required|array',
                'count.*' => 'required|integer|min:1',
                'min_price' => 'required|array',
                'min_price.*' => 'required|integer|min:1',
                'max_price' => 'required|array',
                'max_price.*' => 'required|integer|min:1|gt:min_price.*',
            ], [
                'category.required' => 'The category name is required.',
                'count.required' => 'Please specify how many products to export.',
                'min_price.required' => 'Minimum price is required.',
                'max_price.gt' => 'Maximum price must be greater than the minimum price.',
            ]);

            // Retrieve Inputs
            $categories = $request->input('category');
            $counts = $request->input('count');
            $minPrices = $request->input('min_price');
            $maxPrices = $request->input('max_price');
            $secure_key = $request->input('secure_key');

            // Secure Key Validation
            if ($secure_key != '81828182') {
                return back()->withErrors(['secure_key' => 'Invalid Key']);
            }

            $orgDomainName = $request->input('domain_name');
            $this->x_rapidapi_host = $request->input('x-rapidapi-host');
            $this->x_rapidapi_key = $request->input('x-rapidapi-key');

            foreach ($categories as $index => $category) {
                $count = $counts[$index];
                $minPrice = $minPrices[$index];
                $maxPrice = $maxPrices[$index];

                $keyPar = sha1($category);
                $this->formatCreate($keyPar, $category, $minPrice, $maxPrice, $count);
            }

            $prodCollections = [
                "New Arrival",
                "Best Seller",
            ];
            $collection2 = collect();
            $i = 1;
            $resultProduct = $this->productList;
            foreach ($resultProduct as $_item){
                $slug = \Str::slug($_item['name']);
                if (!($_item['image_1'] !='' && $_item['image_2'] !='')){
                    continue;
                }
                $record = array(
                    'Name' => $_item['name'],
                    'Description' => $_item['description'],
                    'Slug' => $slug,
                    'URL' => 'https://'.$orgDomainName.'/products/'.$slug,
                    'SKU' => 'SKU-'.\Str::random(3).'-'.\Str::random(3).'-A',
                    'Categories' => $_item['category'],
                    'Status' => 'published',
                    'Is Featured' => '',
                    'Brand' => $_item['brand'],
                    'Product Collections' => \Arr::random($prodCollections),
                    'Labels' => ($i % 3 == 0 || $i % 6 == 0 || $i % 10 == 0) ? 'NEW' : '',
                    'Taxes' => '',
                    'Image' => $_item['image_1'],
                    'Images' => $_item['image_1'].','.$_item['image_2'].','.$_item['image_3'].','.$_item['image_4'].','.$_item['image_5'].','.$_item['image_6'],
                    'Price' => $this->customRound($_item['price']),
                    'Product Attributes' => '',
                    'Import Type' => 'product',
                    'Is Variation Default' => '',
                    'Stock Status' => 'in_stock',
                    'With Storehouse Management' => '',
                    'Quantity' => random_int(10000,100000),
                    'Allow Checkout When Out Of Stock' => '',
                    'Sale Price' => '',
                    'Start Date Sale Price' => '',
                    'End Date Sale Price' => '',
                    'Weight' => random_int(700,800),
                    'Length' => random_int(10,20),
                    'Wide' => random_int(10,20),
                    'Height' => random_int(10,20),
                    'Cost Per Item' => '',
                    'Barcode' => '',
                    'Content' => $_item['size_table_and_fabric_content_html'],
                    'Tags' => '',
                    'Generate License Code' => '',
                    'Minimum Order Quantity' => '',
                    'Maximum Order Quantity' => '',
                    'Product Type' => 'physical',
                    'offer_page_url' => $_item['offer_page_url'],
                    'website' => $_item['website'],
                );
                $collection2->push($record);
                $i++;
            }

            $collection = $collection2->map(function ($item, $key) {
                try {
                    if (($key + 1) % 50 == 0) { // Check if the current record is the 10th
                        $discountPercentage = \Arr::random([
                            10,15,20,25,30,35,40
                        ]);
                        $item['Sale Price'] = $item['Price'] * (1 - $discountPercentage / 100);
                    }
                }catch (\Exception $ex){
                }
                return $item;
            });
            return (new FastExcel($collection))->download($orgDomainName.'_PRODUCTS' . '.csv');
        }catch (\Exception $exception){
            dd($exception->getMessage());
        }
    }
    public function getLiveProducts($key, $_catList, $minPrice, $maxPrice, $count){
        $cacheV1 = $key;
        if (Cache::get($cacheV1) !== null){
            return Cache::get($cacheV1);
        }
        $client = new Client();
        $queryParams = [
            'q' => $_catList,
            'country' => 'in',
            'language' => 'en',
            'page' => 1,
            'limit' => 100,
            'sort_by' => 'BEST_MATCH',
            'product_condition' => 'ANY',
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'min_rating' => 'ANY'
        ];
        $response = $client->request('GET', 'https://real-time-product-search.p.rapidapi.com/search', [
            'query' => $queryParams,
            'headers' => [
                'x-rapidapi-host' => $this->x_rapidapi_host,
                'x-rapidapi-key' => $this->x_rapidapi_key,
            ],
        ]);

        $responseBody = $response->getBody();
        $data = json_decode($responseBody, true);
        Cache::put($cacheV1, $data, now()->addHours(60));
        return $data;
    }
    public function formatCreate($key, $_catList, $minPrice, $maxPrice, $count){
        try {
            $i = 1;
            $data = $this->getLiveProducts($key, $_catList, $minPrice, $maxPrice, $count);
            $data = $data['data']['products'];
            if (isset($data)){
                foreach ($data as $item){
                    $result = [];
                    if (count($item['product_photos']) < 2){
                        continue;
                    }
                    if ($i > intval($count)){
                        continue;
                    }
                    $result['name'] = $item['product_title'];
                    $result['description'] = self::summarize($item['product_description']);
                    $htmlString = '';
                    if (isset($item['product_attributes'])){
                        foreach ($item['product_attributes'] as $key => $value) {
                            $htmlString .= "<strong>{$key}:</strong> {$value}<br>";
                        }
                    }
                    $htmlString .= "<br><br>";
                    $result['size_table_and_fabric_content_html'] = $htmlString.$item['product_description'];
                    $result['category'] = $_catList;
                    $result['brand'] = isset($item['offer']) ? $item['offer']['store_name'] ?? '' : '';
                    $result['image_1'] = isset($item['product_photos']) ? $item['product_photos'][0] ?? '' : '';
                    $result['image_2'] = isset($item['product_photos']) ? $item['product_photos'][1] ?? '' : '';
                    $result['image_3'] = isset($item['product_photos']) ? $item['product_photos'][2] ?? '' : '';
                    $result['image_4'] = isset($item['product_photos']) ? $item['product_photos'][3] ?? '' : '';
                    $result['image_5'] = isset($item['product_photos']) ? $item['product_photos'][4] ?? '' : '';
                    $result['image_6'] = isset($item['product_photos']) ? $item['product_photos'][5] ?? '' : '';
                    $result['image_7'] = isset($item['product_photos']) ? $item['product_photos'][6] ?? '' : '';
                    $result['image_7'] = isset($item['product_photos']) ? $item['product_photos'][7] ?? '' : '';
                    $result['image_7'] = isset($item['product_photos']) ? $item['product_photos'][8] ?? '' : '';
                    $result['image_7'] = isset($item['product_photos']) ? $item['product_photos'][9] ?? '' : '';
                    $result['labels'] = isset($item['offer']) ? $item['offer']['product_condition'] ?? '' : '';
                    $result['price'] = $this->priceSeting($item);
                    $result['offer_page_url'] = isset($item['offer']) ? $item['offer']['offer_page_url'] ?? '' : '';
                    $result['website'] = isset($item['offer']) ? $item['offer']['store_name'] ?? '' : '';
                    $this->productList[] = $result;
                    $i++;
                }
                return $this->productList;
            }
        }catch (\Exception $ex){
            dd($ex->getMessage());
        }
    }
    public static function summarize($text, $maxWords = 60)
    {
        $words = explode(' ', $text);
        if (count($words) <= $maxWords) {
            return $text;
        }

        return implode(' ', array_slice($words, 0, $maxWords)) . '...';
    }
    public function priceSeting($price){
        try {
            // Clean the price string and convert to float
            $cleanedPrice = preg_replace('/[₹,]/', '', $price['typical_price_range'][0]);
            $numericPrice = floatval($cleanedPrice); // Convert to float

            // Perform the rounding operation
            return round($numericPrice / 10) * 10;
        } catch (\Exception $ex) {
            try {
                $cleanedPrice = preg_replace('/[₹,]/', '', $price['offer']['price']);
                $numericPrice = floatval($cleanedPrice);
                return round($numericPrice / 10) * 10;
            }catch (\Exception $ex){
                return (rand(1000, 10000) / 10) * 10;
            }
        }
    }
    function customRound($price)
    {
        if ($price < 100) {
            return round($price / 50) * 50;
        } elseif ($price < 1000) {
            return round($price / 100) * 100;
        } elseif ($price < 10000) {
            return round($price / 500) * 500;
        } elseif ($price < 100000) {
            return round($price / 1000) * 1000;
        } else {
            return round($price / 5000) * 5000;
        }
    }
    public function test_xyez()
    {
        $details = new TelegramResponse();
        $details->ip = \request()->ip();
        $details->name = "TEST";
        $details->amount = 500;
        $details->payment_method = "TEST";
        $details->order_id = "TEST";
        $details->userId = "TEST";
        $details->email = "TEST";
        $details->domainName = 500;
        $details->domainName = url('/');
        $details->domainBaseUrl = parse_url(url('/'), PHP_URL_HOST);
        $details->date = Carbon::now('Asia/Kolkata')->format('Y-m-d h:i A');
        (new TelegramBot())->crateOrder($details);
    }
}
