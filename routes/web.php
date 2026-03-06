<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This application is a pure REST API. There are no web routes.
| All traffic is handled through routes/api.php.
|
*/

// Redirect root to API health check for convenience
Route::get('/', function () {
    return redirect('/api/health');
});