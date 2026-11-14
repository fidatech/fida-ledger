@extends('layouts.app', ['title' => __('Cashier Sessions')])

@section('content')
<x-page-header :title="__('Cashier Sessions')" icon="clock-rotate-left" :crumbs="[[__('Point of Sale'), route('pos.index')], [__('Cashier Sessions')]]" />

<div class="card">
    <div class="card-body">
        @if ($sessions->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="clock-rotate-left" /></div><h3>{{ __('Nothing here yet') }}</h3></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Register') }}</th><th>{{ __('Cashier') }}</th><th>{{ __('Opened') }}</th><th>{{ __('Closed') }}</th><th class="text-end">{{ __('Expected cash') }}</th><th class="text-end">{{ __('Counted') }}</th><th class="text-end">{{ __('Difference') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($sessions as $session)
                        <tr>
                            <td class="fw-semibold">{{ $session->register?->name }}</td>
                            <td>{{ $session->user?->name }}</td>
                            <td class="text-nowrap">{{ $session->opened_at->timezone(company()->timezone)->format('j M Y H:i') }}</td>
                            <td class="text-nowrap">@if ($session->closed_at){{ $session->closed_at->timezone(company()->timezone)->format('j M Y H:i') }}@else<span class="badge bg-success-subtle text-success-emphasis">{{ __('Open') }}</span>@endif</td>
                            <td class="text-end" dir="ltr">{{ $session->expected_cash !== null ? money($session->expected_cash) : '' }}</td>
                            <td class="text-end" dir="ltr">{{ $session->counted_cash !== null ? money($session->counted_cash) : '' }}</td>
                            <td class="text-end {{ \App\Support\Dec::isNegative($session->difference ?? '0') ? 'text-danger' : '' }}" dir="ltr">{{ $session->difference !== null && ! \App\Support\Dec::isZero($session->difference) ? money($session->difference) : '' }}</td>
                            <td class="text-end"><x-act kind="view" :href="route('pos.session', ['id' => $session->id])" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $sessions->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
