<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/qrcodes/meals/{filename}', function ($filename) {
    $path = storage_path('app/public/qrcodes/meals/' . $filename);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});

Route::get('/qrcodes/events/{filename}', function ($filename) {
    $path = storage_path('app/public/qrcodes/events/1/' . $filename);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});

Route::get('/staff_passports/{filename}', function ($filename) {
    $path = storage_path('app/public/staff_passports/' . $filename);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});


Route::get('/attendee_photos/{filename}', function ($filename) {
    $path = storage_path('app/public/attendee_photos/' . $filename);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
});

