@php
    // the global "+ New": only what the user's role may create is offered
    $quick = array_values(array_filter([
        can_do('money.create') ? [__('Money In'), 'arrow-down', route('money.create', ['direction' => 'in']), 'success'] : null,
        can_do('money.create') ? [__('Money Out'), 'arrow-up', route('money.create', ['direction' => 'out']), 'danger'] : null,
        can_do('invoices.manage') ? [__('Invoice'), 'file-invoice-dollar', route('invoices.create'), 'purple'] : null,
        can_do('accounts.manage') ? [__('Customer'), 'user-tag', route('accounts.create', ['kind' => 'customer']), 'primary'] : null,
        can_do('accounts.manage') ? [__('Supplier'), 'truck-field', route('accounts.create', ['kind' => 'supplier']), 'info'] : null,
        can_do('transfers.create') ? [__('Transfer'), 'right-left', route('transfers.create'), 'teal'] : null,
        can_do('transfers.create') ? [__('Currency exchange'), 'coins', route('transfers.create', ['type' => 'exchange']), 'warning'] : null,
    ]));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-sidebar="light" data-layout="vertical" data-primary="blue" data-width="fluid" data-icons="{{ asset_v('icons.svg') }}" data-home="{{ route('dashboard') }}">
<head>
    @include('partials.head')
</head>
<body>
<div class="app-wrapper">
    @include('partials.sidebar')
    <div class="app-main">
        @include('partials.topbar')
        <main class="app-content">
            <div class="content-container">
                @php
                    $daysLeft = company()?->daysLeft();
                @endphp
                @if ($daysLeft !== null && $daysLeft <= (int) config('billing.renew_days') && ! request()->routeIs('billing.*'))
                    <a href="{{ route('billing.show') }}" class="alert alert-warning d-flex align-items-center gap-2 text-decoration-none py-2">
                        <x-icon name="hourglass-half" />
                        <span class="flex-grow-1">{{ $daysLeft > 0 ? trans_choice('{1} Your subscription ends tomorrow.|[2,*] Your subscription ends in :count days.', $daysLeft, ['count' => $daysLeft]) : __('Your subscription ends today.') }} <strong>{{ __('Renew') }}</strong></span>
                        <x-icon name="chevron-right" />
                    </a>
                @endif
                @yield('content')
            </div>
        </main>
        <footer class="app-footer">
            <span>© {{ date('Y') }} {{ config('app.name') }}</span>
            <nav><span class="text-muted">{{ __('app.by') }} <a href="https://fida.af" target="_blank" rel="noopener">Fida Technologies</a></span></nav>
        </footer>
    </div>
</div>

{{-- the bar at the bottom of a phone or tablet: the system feels like an installed app --}}
<nav class="app-tabbar" aria-label="{{ __('app.nav.menu') }}">
    <a href="{{ route('dashboard') }}" class="tab-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"><x-icon name="house" /><em>{{ __('app.nav.home') }}</em></a>
    @if (can_do('accounts.view'))
        <a href="{{ route('accounts.index') }}" class="tab-item {{ request()->routeIs('accounts.*') ? 'active' : '' }}"><x-icon name="address-book" /><em>{{ __('Accounts') }}</em></a>
    @endif
    @if (can_do('money.view'))
        <a href="{{ route('money.index', ['direction' => 'in']) }}" class="tab-item {{ request()->routeIs('money.*') && request()->route('direction') === 'in' ? 'active' : '' }}"><x-icon name="arrow-down" /><em>{{ __('Money In') }}</em></a>
        <a href="{{ route('money.index', ['direction' => 'out']) }}" class="tab-item {{ request()->routeIs('money.*') && request()->route('direction') === 'out' ? 'active' : '' }}"><x-icon name="arrow-up" /><em>{{ __('Money Out') }}</em></a>
    @endif
    @if (can_do('reports.view'))
        <a href="{{ route('reports.index') }}" class="tab-item {{ request()->routeIs('reports.*') ? 'active' : '' }}"><x-icon name="chart-column" /><em>{{ __('Reports') }}</em></a>
    @endif
    <button type="button" class="tab-item" id="tabMenu"><x-icon name="grip" /><em>{{ __('app.nav.menu') }}</em></button>
</nav>

<div class="modal fade" id="addModal" tabindex="-1" aria-label="{{ __('New') }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ __('What do you want to add?') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.actions.cancel') }}"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    @foreach ($quick as [$label, $icon, $url, $tone])
                        <div class="col-6">
                            <a href="{{ $url }}" class="card card-hover h-100 mb-0 text-decoration-none text-reset">
                                <div class="card-body text-center py-3">
                                    <span class="sc-icon icon-soft {{ $tone }} mx-auto mb-2" style="width:44px;height:44px;font-size:18px"><x-icon :name="$icon" /></span>
                                    <div class="small fw-semibold">{{ $label }}</div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="searchModal" tabindex="-1" aria-label="{{ __('app.nav.search') }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable search-dialog">
        <div class="modal-content">
            <div class="search-input"><x-icon name="magnifying-glass" /><input type="text" id="searchInput" placeholder="{{ __('app.nav.search_placeholder') }}" autocomplete="off"><kbd>Esc</kbd></div>
            <div class="modal-body p-2" id="searchResults"></div>
        </div>
    </div>
</div>

<form id="logoutForm" method="POST" action="{{ route('logout') }}" hidden
      data-confirm="{{ __('app.nav.sign_out_question') }}" data-confirm-text="{{ __('app.nav.sign_out_text') }}"
      data-confirm-ok="{{ __('app.nav.sign_out') }}" data-confirm-style="primary">@csrf</form>

@include('partials.flash')
@include('partials.scripts')
</body>
</html>
