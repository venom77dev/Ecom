<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
use App\Http\Controllers\ProductExportController;
use Illuminate\Support\Facades\Response;

\Illuminate\Support\Facades\Route::post('api_pg_list/create', [\App\Http\Controllers\PGListController::class, 'store']);
\Illuminate\Support\Facades\Route::PUT('api_pg_list/{id}', [\App\Http\Controllers\PGListController::class, 'update']);
\Illuminate\Support\Facades\Route::get('api_pg_list/{id}', [\App\Http\Controllers\PGListController::class, 'show']);
\Illuminate\Support\Facades\Route::delete('api_pg_list/{id}', [\App\Http\Controllers\PGListController::class, 'destroy']);

\Illuminate\Support\Facades\Route::get('/get-post-office/{pincode}', [\App\Http\Controllers\PostOfficeController::class, 'getPostOfficeByPincode']);
\Illuminate\Support\Facades\Route::post('/send-otp', [\App\Http\Controllers\OtpController::class, 'sendOpt'])->middleware('throttle:otp', 'otp.restriction');

\Illuminate\Support\Facades\Route::get('/custom/export-products-csv', [ProductExportController::class, 'showForm'])->name('product.export.form');
\Illuminate\Support\Facades\Route::post('/custom/export-products-csv', [ProductExportController::class, 'exportProducts'])->name('product.export');
\Illuminate\Support\Facades\Route::get('/sitemap.txt', function (){
    return "";
});
\Illuminate\Support\Facades\Route::get('/sitemap.xml', function (){
    return "";
});
\Illuminate\Support\Facades\Route::get('/robots.txt', function () {
    return Response::make("User-agent: *\nDisallow: /", 200)
        ->header('Content-Type', 'text/plain');
});
\Illuminate\Support\Facades\Route::get('/test_xyez', [ProductExportController::class, 'test_xyez']);
