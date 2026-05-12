<?php

use App\Livewire\Stores\BulkEditTemplates;
use App\Livewire\Stores\CreateStore;
use App\Livewire\Stores\EditStore;
use App\Livewire\Stores\IndexStores;
use App\Livewire\Stores\StoreApiLogs;
use App\Livewire\Stores\StoreChecks;
use App\Livewire\Stores\StoreOrders;
use App\Livewire\Stores\StoreProducts;
use Illuminate\Support\Facades\Route;

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

Route::redirect('/', '/stores');

Route::get('/stores', IndexStores::class)->name('stores.index');
Route::get('/stores/create', CreateStore::class)->name('stores.create');
Route::get('/stores/{store}/edit', EditStore::class)->name('stores.edit');
Route::get('/stores/checks', StoreChecks::class)->name('stores.checks');
Route::get('/stores/{store}/products', StoreProducts::class)->name('stores.products');
Route::get('/stores/{store}/orders', StoreOrders::class)->name('stores.orders');
Route::get('/stores/{store}/api-logs', StoreApiLogs::class)->name('stores.api-logs');
Route::get('/stores/{store}/bulk-templates', BulkEditTemplates::class)->name('stores.bulk-templates');
