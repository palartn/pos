<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// حتى لا يظهر خطأ "Route [login] not defined" لأي middleware من نوع auth
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// إيصال الفاتورة (للعرض والطباعة)
Route::middleware('auth')
    ->get('/receipts/{sale}', [ReceiptController::class, 'show'])
    ->name('receipts.show');
