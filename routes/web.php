<?php

use App\Livewire\Login;
use App\Livewire\Stores\BulkEditTemplates;
use App\Livewire\Stores\CheckDefinitions;
use App\Livewire\Stores\StoreCheckHistory;
use App\Livewire\Stores\CreateStore;
use App\Livewire\Stores\EditStore;
use App\Livewire\Stores\IndexStores;
use App\Livewire\Stores\StoreApiLogs;
use App\Livewire\Stores\StoreDiagnostics;
use App\Livewire\Stores\StoreOrders;
use App\Livewire\Stores\StoreOverview;
use App\Livewire\Stores\StoreProducts;
use App\Livewire\Stores\StoreTools;
use Illuminate\Support\Facades\Auth;
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

Route::get('/login', Login::class)->middleware('guest')->name('login');
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'can:operate'])->group(function () {

    Route::get('/stores', IndexStores::class)->name('stores.index');
    Route::get('/stores/create', CreateStore::class)->middleware('can:configure')->name('stores.create');
    Route::get('/stores/{store}/edit', EditStore::class)->middleware('can:configure')->name('stores.edit');
    Route::get('/stores/checks', CheckDefinitions::class)->name('stores.checks');
    Route::redirect('/stores/check-definitions', '/stores/checks')->name('stores.check-definitions');
    Route::get('/stores/{store}/products', StoreProducts::class)->name('stores.products');
    Route::get('/stores/{store}/orders', StoreOrders::class)->name('stores.orders');
    Route::get('/stores/{store}/api-logs', StoreApiLogs::class)->name('stores.api-logs');
    Route::get('/stores/{store}/bulk-templates', BulkEditTemplates::class)->middleware('can:manage')->name('stores.bulk-templates');
    Route::get('/stores/{store}/overview', StoreOverview::class)->name('stores.overview');
    Route::get('/stores/{store}/check-history', StoreCheckHistory::class)->name('stores.check-history');
    Route::get('/stores/{store}/tools', StoreTools::class)->name('stores.tools');
    Route::get('/stores/{store}/diagnostics', StoreDiagnostics::class)->name('stores.diagnostics');
});
