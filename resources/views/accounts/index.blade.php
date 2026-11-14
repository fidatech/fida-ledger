@extends('layouts.app', ['title' => $kind ? __($kinds[$kind]['plural']) : __('All Accounts')])

@section('content')
<x-page-header :title="$kind ? __($kinds[$kind]['plural']) : __('All Accounts')" :icon="$kind ? $kinds[$kind]['icon'] : 'address-book'">
    @if (can_do('accounts.manage'))
        <a class="btn btn-primary" href="{{ route('accounts.create', ['kind' => $kind ?: 'customer']) }}"><x-icon name="plus" class="me-1" /> {{ $kind ? __('Add :name', ['name' => __($kinds[$kind]['label'])]) : __('Add account') }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md">
                <input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search by name, code or phone…') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col-8 col-md-auto">
                <select class="form-select" name="kind" aria-label="{{ __('Kind of account') }}" onchange="this.form.submit()" data-plain>
                    <option value="">{{ __('All kinds') }}</option>
                    @foreach ($kinds as $value => $definition)
                        <option value="{{ $value }}" @selected($kind === $value)>{{ __($definition['plural']) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button>
            </div>
        </form>

        @if ($accounts->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="address-book" /></div>
                <h3>{{ $term !== '' ? __('Nothing found') : __('Nothing here yet') }}</h3>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Phone') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($accounts as $account)
                        @php
                            $held = array_map(fn ($amount) => \App\Accounting\Balances::natural($account->class, $amount), $balances[$account->id]['currencies'] ?? []);
                        @endphp
                        <tr>
                            <td dir="ltr" class="text-muted small">{{ $account->code }}</td>
                            <td><a class="text-decoration-none fw-semibold" href="{{ route('accounts.show', ['id' => $account->id]) }}">{{ $account->name }}</a>@if ($account->is_archived) <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Archived') }}</span>@endif</td>
                            <td>{{ $account->kindLabel() }}@if ($account->currency) · {{ $account->currency }}@endif</td>
                            <td dir="ltr">{{ $account->phone }}</td>
                            <td class="text-end fw-semibold" dir="ltr">{{ sums($held) }}</td>
                            <td class="text-end text-nowrap no-print">
                                <div class="act-group">
                                    <x-act kind="view" :href="route('accounts.show', ['id' => $account->id])" :label="__('Statement')" />
                                    @if (can_do('accounts.manage'))
                                        <x-act kind="edit" :href="route('accounts.edit', ['id' => $account->id])" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $accounts->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
