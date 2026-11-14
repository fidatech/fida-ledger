<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Assets;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\Catalog;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\MoneyController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Payroll;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

// {type} in /products/{type} is "product" or "service"
Route::pattern('type', 'product|service');
// {doc} in /sales/{doc} is "quotation" or "order"
Route::pattern('doc', 'quotation|order');

Route::redirect('/', '/dashboard');

/* ------------------------------------------------------------------ sign-in and registration */

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:6,10')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'store'])->name('password.store');
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

            // Invoicing (every plan)
            Route::middleware('can.do:invoices.view')->group(function () {
                Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
                Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->whereNumber('id')->name('invoices.show');
            });
            Route::middleware('can.do:invoices.manage')->group(function () {
                Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
                Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
                Route::get('/invoices/{id}/edit', [InvoiceController::class, 'edit'])->whereNumber('id')->name('invoices.edit');
                Route::put('/invoices/{id}', [InvoiceController::class, 'update'])->whereNumber('id')->name('invoices.update');
                Route::post('/invoices/{id}/issue', [InvoiceController::class, 'issue'])->whereNumber('id')->name('invoices.issue');
                Route::post('/invoices/{id}/void', [InvoiceController::class, 'void'])->whereNumber('id')->name('invoices.void');
            });

            // Products & Services (Business module "products"): the list for those who may see it, changes for those who may manage
            Route::middleware('module:products')->group(function () {
                $screens = [
                    ['/products/{type}', Catalog\ProductController::class, 'products'],
                    ['/product-categories', Catalog\CategoryController::class, 'product-categories'],
                    ['/units', Catalog\UnitController::class, 'units'],
                ];
                foreach ($screens as [$uri, $controller, $name]) {
                    Route::get($uri, [$controller, 'index'])->middleware('can.do:products.view')->name($name.'.index');
                    Route::middleware('can.do:products.manage')->group(function () use ($uri, $controller, $name) {
                        Route::get($uri.'/create', [$controller, 'create'])->name($name.'.create');
                        Route::post($uri, [$controller, 'store'])->name($name.'.store');
                        Route::get($uri.'/{id}/edit', [$controller, 'edit'])->whereNumber('id')->name($name.'.edit');
                        Route::put($uri.'/{id}', [$controller, 'update'])->whereNumber('id')->name($name.'.update');
                        Route::delete($uri.'/{id}', [$controller, 'destroy'])->whereNumber('id')->name($name.'.destroy');
                    });
                }
            });

            // Inventory (Business module)
            Route::middleware('module:inventory')->group(function () {
                Route::middleware('can.do:inventory.view')->group(function () {
                    Route::get('/inventory', [Inventory\InventoryController::class, 'overview'])->name('inventory.overview');
                    Route::get('/inventory/movements', [Inventory\InventoryController::class, 'movements'])->name('inventory.movements');
                    Route::get('/warehouses', [Inventory\WarehouseController::class, 'index'])->name('warehouses.index');
                });
                Route::middleware('can.do:inventory.manage')->group(function () {
                    Route::get('/inventory/change', [Inventory\InventoryController::class, 'change'])->name('inventory.change');
                    Route::post('/inventory/change', [Inventory\InventoryController::class, 'store'])->name('inventory.store');
                    Route::get('/warehouses/create', [Inventory\WarehouseController::class, 'create'])->name('warehouses.create');
                    Route::post('/warehouses', [Inventory\WarehouseController::class, 'store'])->name('warehouses.store');
                    Route::get('/warehouses/{id}/edit', [Inventory\WarehouseController::class, 'edit'])->whereNumber('id')->name('warehouses.edit');
                    Route::put('/warehouses/{id}', [Inventory\WarehouseController::class, 'update'])->whereNumber('id')->name('warehouses.update');
                    Route::delete('/warehouses/{id}', [Inventory\WarehouseController::class, 'destroy'])->whereNumber('id')->name('warehouses.destroy');
                });
            });

            // Purchasing (Business module)
            Route::middleware('module:purchasing')->group(function () {
                Route::middleware('can.do:purchases.view')->group(function () {
                    Route::get('/purchases', [Inventory\PurchaseController::class, 'index'])->name('purchases.index');
                    Route::get('/purchases/{id}', [Inventory\PurchaseController::class, 'show'])->whereNumber('id')->name('purchases.show');
                    // the controller checks purchases.approve for "approve" and purchases.manage for the other steps
                    Route::post('/purchases/{id}/{step}', [Inventory\PurchaseController::class, 'act'])->whereNumber('id')->whereIn('step', ['approve', 'cancel', 'receive', 'bill', 'return'])->name('purchases.act');
                });
                Route::middleware('can.do:purchases.manage')->group(function () {
                    Route::get('/purchases/create', [Inventory\PurchaseController::class, 'create'])->name('purchases.create');
                    Route::post('/purchases', [Inventory\PurchaseController::class, 'store'])->name('purchases.store');
                    Route::get('/purchases/{id}/edit', [Inventory\PurchaseController::class, 'edit'])->whereNumber('id')->name('purchases.edit');
                    Route::put('/purchases/{id}', [Inventory\PurchaseController::class, 'update'])->whereNumber('id')->name('purchases.update');
                });
            });

            // Sales Management (Business module)
            Route::middleware('module:sales')->prefix('/sales/{doc}')->group(function () {
                Route::middleware('can.do:sales.view')->group(function () {
                    Route::get('/', [Inventory\SalesController::class, 'index'])->name('sales.index');
                    Route::get('/{id}', [Inventory\SalesController::class, 'show'])->whereNumber('id')->name('sales.show');
                });
                Route::middleware('can.do:sales.manage')->group(function () {
                    Route::get('/create', [Inventory\SalesController::class, 'create'])->name('sales.create');
                    Route::post('/', [Inventory\SalesController::class, 'store'])->name('sales.store');
                    Route::get('/{id}/edit', [Inventory\SalesController::class, 'edit'])->whereNumber('id')->name('sales.edit');
                    Route::put('/{id}', [Inventory\SalesController::class, 'update'])->whereNumber('id')->name('sales.update');
                    Route::post('/{id}/{step}', [Inventory\SalesController::class, 'act'])->whereNumber('id')->whereIn('step', ['confirm', 'cancel', 'deliver', 'invoice', 'return'])->name('sales.act');
                });
            });

            // Point of Sale (Business module)
            Route::middleware('module:pos')->prefix('/pos')->group(function () {
                Route::middleware('can.do:pos.use')->group(function () {
                    Route::get('/', [Inventory\PosController::class, 'index'])->name('pos.index');
                    Route::post('/registers/{id}/open', [Inventory\PosController::class, 'open'])->whereNumber('id')->name('pos.open');
                    Route::get('/sessions', [Inventory\PosController::class, 'sessions'])->name('pos.sessions');
                    Route::get('/sessions/{id}', [Inventory\PosController::class, 'session'])->whereNumber('id')->name('pos.session');
                    Route::get('/sessions/{id}/sell', [Inventory\PosController::class, 'sell'])->whereNumber('id')->name('pos.sell');
                    Route::post('/sessions/{id}/sell', [Inventory\PosController::class, 'store'])->whereNumber('id')->name('pos.store');
                    Route::get('/sales', [Inventory\PosController::class, 'sales'])->name('pos.sales');
                    Route::get('/sales/{id}', [Inventory\PosController::class, 'sale'])->whereNumber('id')->name('pos.sale');
                });
                Route::middleware('can.do:pos.close')->group(function () {
                    Route::post('/sessions/{id}/close', [Inventory\PosController::class, 'close'])->whereNumber('id')->name('pos.close');
                    Route::post('/sales/{id}/refund', [Inventory\PosController::class, 'refund'])->whereNumber('id')->name('pos.refund');
                });
                Route::middleware('can.do:settings.manage')->group(function () {
                    Route::get('/registers', [Inventory\RegisterController::class, 'index'])->name('pos.registers.index');
                    Route::get('/registers/create', [Inventory\RegisterController::class, 'create'])->name('pos.registers.create');
                    Route::post('/registers', [Inventory\RegisterController::class, 'store'])->name('pos.registers.store');
                    Route::get('/registers/{id}/edit', [Inventory\RegisterController::class, 'edit'])->whereNumber('id')->name('pos.registers.edit');
                    Route::put('/registers/{id}', [Inventory\RegisterController::class, 'update'])->whereNumber('id')->name('pos.registers.update');
                    Route::delete('/registers/{id}', [Inventory\RegisterController::class, 'destroy'])->whereNumber('id')->name('pos.registers.destroy');
                });
            });

            // Fixed Assets (Business module)
            Route::middleware('module:fixed_assets')->group(function () {
                Route::middleware('can.do:assets.view')->group(function () {
                    Route::get('/assets', [Assets\AssetController::class, 'index'])->name('assets.index');
                    Route::get('/assets/{id}', [Assets\AssetController::class, 'show'])->whereNumber('id')->name('assets.show');
                });
                Route::middleware('can.do:assets.manage')->group(function () {
                    Route::get('/assets/create', [Assets\AssetController::class, 'create'])->name('assets.create');
                    Route::post('/assets', [Assets\AssetController::class, 'store'])->name('assets.store');
                    Route::post('/assets/depreciate', [Assets\AssetController::class, 'depreciate'])->name('assets.depreciate');
                    Route::post('/assets/{id}/dispose', [Assets\AssetController::class, 'dispose'])->whereNumber('id')->name('assets.dispose');
                    Route::post('/assets/{id}/event', [Assets\AssetController::class, 'event'])->whereNumber('id')->name('assets.event');
                });
                Route::get('/asset-categories', [Assets\CategoryController::class, 'index'])->middleware('can.do:assets.view')->name('asset-categories.index');
                Route::middleware('can.do:assets.manage')->group(function () {
                    Route::get('/asset-categories/create', [Assets\CategoryController::class, 'create'])->name('asset-categories.create');
                    Route::post('/asset-categories', [Assets\CategoryController::class, 'store'])->name('asset-categories.store');
                    Route::get('/asset-categories/{id}/edit', [Assets\CategoryController::class, 'edit'])->whereNumber('id')->name('asset-categories.edit');
                    Route::put('/asset-categories/{id}', [Assets\CategoryController::class, 'update'])->whereNumber('id')->name('asset-categories.update');
                    Route::delete('/asset-categories/{id}', [Assets\CategoryController::class, 'destroy'])->whereNumber('id')->name('asset-categories.destroy');
                });
            });

            // Payroll (Business module): every screen needs a payroll permission, salaries are private
            Route::middleware('module:payroll')->group(function () {
                Route::middleware('can.do:payroll.view')->group(function () {
                    Route::get('/payroll', [Payroll\PayrollController::class, 'index'])->name('payroll.index');
                    Route::get('/payroll/{id}', [Payroll\PayrollController::class, 'show'])->whereNumber('id')->name('payroll.show');
                    Route::get('/payroll/payslips/{id}', [Payroll\PayrollController::class, 'payslip'])->whereNumber('id')->name('payroll.payslip');
                    Route::get('/payroll/advances', [Payroll\PayrollController::class, 'advances'])->name('payroll.advances');
                });
                Route::middleware('can.do:payroll.manage')->group(function () {
                    Route::post('/payroll', [Payroll\PayrollController::class, 'prepare'])->name('payroll.prepare');
                    Route::post('/payroll/advances', [Payroll\PayrollController::class, 'advance'])->name('payroll.advance');
                    Route::post('/payroll/{id}/{step}', [Payroll\PayrollController::class, 'act'])->whereNumber('id')->whereIn('step', ['save', 'approve', 'pay', 'discard'])->name('payroll.act');
                });
                Route::get('/employees', [Payroll\EmployeeController::class, 'index'])->middleware('can.do:payroll.view')->name('employees.index');
                Route::middleware('can.do:payroll.manage')->group(function () {
                    Route::get('/employees/create', [Payroll\EmployeeController::class, 'create'])->name('employees.create');
                    Route::post('/employees', [Payroll\EmployeeController::class, 'store'])->name('employees.store');
                    Route::get('/employees/{id}/edit', [Payroll\EmployeeController::class, 'edit'])->whereNumber('id')->name('employees.edit');
                    Route::put('/employees/{id}', [Payroll\EmployeeController::class, 'update'])->whereNumber('id')->name('employees.update');
                    Route::delete('/employees/{id}', [Payroll\EmployeeController::class, 'destroy'])->whereNumber('id')->name('employees.destroy');
                });
                Route::get('/departments', [Payroll\DepartmentController::class, 'index'])->middleware('can.do:payroll.view')->name('departments.index');
                Route::middleware('can.do:payroll.manage')->group(function () {
                    Route::get('/departments/create', [Payroll\DepartmentController::class, 'create'])->name('departments.create');
                    Route::post('/departments', [Payroll\DepartmentController::class, 'store'])->name('departments.store');
                    Route::get('/departments/{id}/edit', [Payroll\DepartmentController::class, 'edit'])->whereNumber('id')->name('departments.edit');
                    Route::put('/departments/{id}', [Payroll\DepartmentController::class, 'update'])->whereNumber('id')->name('departments.update');
                    Route::delete('/departments/{id}', [Payroll\DepartmentController::class, 'destroy'])->whereNumber('id')->name('departments.destroy');
                });
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
                Route::get('/settings/rates', [Settings\RateController::class, 'index'])->name('settings.rates');
                Route::post('/settings/rates', [Settings\RateController::class, 'store'])->name('settings.rates.store');
                Route::get('/settings/company', [Settings\CompanyController::class, 'edit'])->name('settings.company');
                Route::put('/settings/company', [Settings\CompanyController::class, 'update'])->name('settings.company.update');
                Route::get('/settings/users', [Settings\MemberController::class, 'index'])->name('settings.users');
                Route::post('/settings/users', [Settings\MemberController::class, 'store'])->name('settings.users.store');
                Route::put('/settings/users/{id}', [Settings\MemberController::class, 'update'])->whereNumber('id')->name('settings.users.update');
                Route::get('/settings/currencies', [Settings\CurrencyController::class, 'index'])->name('settings.currencies');
                Route::post('/settings/currencies', [Settings\CurrencyController::class, 'store'])->name('settings.currencies.store');
                Route::post('/settings/currencies/{id}/toggle', [Settings\CurrencyController::class, 'toggle'])->whereNumber('id')->name('settings.currencies.toggle');
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
        Route::get('/companies', [Admin\CompanyController::class, 'index'])->name('companies.index');
        Route::post('/companies/{id}/suspend', [Admin\CompanyController::class, 'suspend'])->whereNumber('id')->name('companies.suspend');
        Route::get('/plans', [Admin\PlanController::class, 'index'])->name('plans.index');
        Route::put('/plans/{id}', [Admin\PlanController::class, 'update'])->whereNumber('id')->name('plans.update');
    });
});
