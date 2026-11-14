@php
    $user = auth()->user();
    $current = company();
@endphp
<header class="app-topbar" id="appTopbar">
    <button class="btn-icon btn-ghost topbar-toggle" id="sidebarToggle" type="button" aria-label="{{ __('app.nav.toggle_sidebar') }}"><x-icon name="bars-staggered" /></button>
    <button class="appbar-back" type="button" id="appBack" aria-label="{{ __('app.nav.back') }}"><x-icon name="chevron-left" /></button>
    <div class="appbar-title" id="appbarTitle">{{ $title ?? config('app.name') }}</div>

    <button class="topbar-search" type="button" data-bs-toggle="modal" data-bs-target="#searchModal">
        <x-icon name="magnifying-glass" /><span>{{ __('app.nav.search_placeholder') }}</span><span class="kbd-group"><kbd>Ctrl</kbd><kbd>K</kbd></span>
    </button>

    <div class="topbar-right">
        @if ($current?->isUsable())
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal"><x-icon name="plus" class="me-1" /> {{ __('New') }}</button>
        @endif

        {{-- the company switcher: there is no "choose a company" page --}}
        <div class="dropdown">
            <button class="topbar-icon" data-bs-toggle="dropdown" type="button" aria-expanded="false" title="{{ $current?->name }}" aria-label="{{ __('Company') }}"><x-icon name="building" /></button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:260px;max-width:92vw">
                <li><h6 class="dropdown-header">{{ __('Companies') }}</h6></li>
                @foreach ($myCompanies ?? [] as $option)
                    <li>
                        <form method="POST" action="{{ route('company.switch') }}">
                            @csrf
                            <input type="hidden" name="company_id" value="{{ $option->id }}">
                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2 {{ $option->id === $current?->id ? 'active' : '' }}">
                                <span class="text-truncate flex-grow-1">{{ $option->name }}</span>
                                <span class="small opacity-75">{{ $option->plan?->name }}</span>
                            </button>
                        </form>
                    </li>
                @endforeach
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('onboarding.create') }}"><x-icon name="plus" /> {{ __('Add a company') }}</a></li>
                @if ($current?->isUsable() && can_do('settings.manage'))
                    <li><a class="dropdown-item" href="{{ route('settings.modules') }}"><x-icon name="gear" /> {{ __('Company settings') }}</a></li>
                @endif
                <li><a class="dropdown-item" href="{{ route('billing.show') }}"><x-icon name="credit-card" /> {{ __('Subscription & Billing') }}</a></li>
            </ul>
        </div>

        <button class="topbar-icon" type="button" data-theme-toggle title="{{ __('app.nav.toggle_theme') }}"><x-icon name="moon" /></button>

        <div class="dropdown">
            <button class="topbar-user" data-bs-toggle="dropdown" type="button" aria-expanded="false">
                <span class="avatar avatar-sm avatar-soft-primary">{{ $user->initials() }}</span>
                <span class="topbar-user-meta"><strong>{{ $user->name }}</strong><small>{{ $current?->name }}</small></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end user-menu">
                <li class="user-menu-head"><span class="avatar avatar-md avatar-soft-primary">{{ $user->initials() }}</span><div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item text-danger" type="submit" form="logoutForm"><x-icon name="right-from-bracket" /> {{ __('app.nav.sign_out') }}</button></li>
            </ul>
        </div>
    </div>
</header>
