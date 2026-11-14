<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\MoneyController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/* ------------------------------------------------------------------ sign-in and registration */

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
});

Route::middleware('auth:web')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // opening a company (after registration, or "Add a company" in the switcher)
    Route::get('/start', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/start', [OnboardingController::class, 'store'])->name('onboarding.store');

    /* -------------------------------------------------------------- inside a company */

    Route::middleware('company')->group(function () {
        Route::post('/company/switch', [CompanyController::class, 'switch'])->name('company.switch');

        // reachable without an active subscription: this is where a pending or expired company lands
        Route::get('/billing', [BillingController::class, 'show'])->name('billing.show');
        Route::post('/billing/pay', [BillingController::class, 'pay'])->middleware('throttle:10,1')->name('billing.pay');
        Route::get('/billing/receipt/{id}', [BillingController::class, 'receipt'])->whereNumber('id')->name('billing.receipt');

        Route::middleware('subscribed')->group(function () {
            Route::get('/dashboard', DashboardController::class)->name('dashboard');

            // Accounts
            Route::middleware('can.do:accounts.view')->group(function () {
                Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
                Route::get('/accounts/chart', [AccountController::class, 'chart'])->name('accounts.chart');
                Route::get('/accounts/{id}', [AccountController::class, 'show'])->whereNumber('id')->name('accounts.show');
            });
            Route::middleware('can.do:accounts.manage')->group(function () {
                Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
                Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
                Route::get('/accounts/{id}/edit', [AccountController::class, 'edit'])->whereNumber('id')->name('accounts.edit');
                Route::put('/accounts/{id}', [AccountController::class, 'update'])->whereNumber('id')->name('accounts.update');
                Route::post('/accounts/{id}/archive', [AccountController::class, 'archive'])->whereNumber('id')->name('accounts.archive');
            });

            // Money In (/money/in) and Money Out (/money/out)
            Route::prefix('/money/{direction}')->where(['direction' => 'in|out'])->group(function () {
                Route::middleware('can.do:money.view')->group(function () {
                    Route::get('/', [MoneyController::class, 'index'])->name('money.index');
                    Route::get('/{id}', [MoneyController::class, 'show'])->whereNumber('id')->name('money.show');
                    Route::get('/{id}/attachment', [MoneyController::class, 'attachment'])->whereNumber('id')->name('money.attachment');
                });
                Route::middleware('can.do:money.create')->group(function () {
                    Route::get('/create', [MoneyController::class, 'create'])->name('money.create');
                    Route::post('/', [MoneyController::class, 'store'])->name('money.store');
                });
                Route::post('/{id}/void', [MoneyController::class, 'void'])->whereNumber('id')->middleware('can.do:money.void')->name('money.void');
            });

            // Transfers & Exchange
            Route::get('/transfers', [TransferController::class, 'index'])->middleware('can.do:money.view')->name('transfers.index');
            Route::middleware('can.do:transfers.create')->group(function () {
                Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create');
                Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
            });

            // General journal
            Route::middleware('can.do:journals.view')->group(function () {
                Route::get('/journal', [JournalController::class, 'index'])->name('journals.index');
                Route::get('/journal/{id}', [JournalController::class, 'show'])->whereNumber('id')->name('journals.show');
            });

            // Reports
            Route::middleware('can.do:reports.view')->group(function () {
                Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
                Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');
                Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
                Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
            });

            // Settings
            Route::middleware('can.do:settings.manage')->group(function () {
                Route::get('/settings/modules', [Settings\ModuleController::class, 'edit'])->name('settings.modules');
                Route::put('/settings/modules', [Settings\ModuleController::class, 'update'])->name('settings.modules.update');
                Route::get('/settings/rates', [Settings\RateController::class, 'index'])->name('settings.rates');
                Route::post('/settings/rates', [Settings\RateController::class, 'store'])->name('settings.rates.store');
            });
        });
    });
});

/* ------------------------------------------------------------------ system administrators (/admin) */

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [Admin\LoginController::class, 'show'])->name('login');
        Route::post('/login', [Admin\LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [Admin\LoginController::class, 'destroy'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/{id}/approve', [Admin\PaymentController::class, 'approve'])->whereNumber('id')->name('payments.approve');
        Route::post('/payments/{id}/reject', [Admin\PaymentController::class, 'reject'])->whereNumber('id')->name('payments.reject');
        Route::get('/payments/{id}/receipt', [Admin\PaymentController::class, 'receipt'])->whereNumber('id')->name('payments.receipt');
    });
});
