<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OneController; // (N) to use in Route::

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    //return view('welcome');
    return view('one');
    //echo "hello (N)";
});

Route::get('/one/list/{dateDay?}', [OneController::class, 'list']); //eg: /one/list/2023-02-12
Route::get('/one/book/day/{dateDay}/hourBegin/{hour_begin}', [OneController::class, 'book']); //eg: /one/book/day/2023-02-13/hourBegin/09:00 ... to do an appointment in the given day and hourBegin
