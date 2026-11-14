@extends('layouts.app', ['title' => __('Chart of Accounts')])

@section('content')
<x-page-header :title="__('Chart of Accounts')" icon="sitemap" :subtitle="__('Balances in :currency', ['currency' => base_currency()])" />

@php
    // the balance of a group is the sum of everything under it
    $total = function (\App\Models\Account $account) use (&$total, $tree, $balances) {
        $sum = $balances[$account->id] ?? '0';
        foreach ($tree[$account->id] ?? [] as $child) {
            $sum = \App\Support\Dec::add($sum, $total($child));
        }
        return $sum;
    };
    $rows = [];
    $walk = function (int $parent, int $depth) use (&$walk, &$rows, $tree, $total) {
        foreach ($tree[$parent] ?? [] as $account) {
            $rows[] = [$account, $depth, \App\Accounting\Balances::natural($account->class, $total($account))];
            $walk($account->id, $depth + 1);
        }
    };
    $walk(0, 0);
@endphp

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Account') }}</th><th>{{ __('Class') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
            <tbody>
                @foreach ($rows as [$account, $depth, $balance])
                    <tr class="{{ $account->is_group ? 'fw-semibold' : '' }}">
                        <td dir="ltr" class="text-muted small">{{ $account->code }}</td>
                        <td style="padding-inline-start: {{ 12 + $depth * 22 }}px">
                            @if ($account->is_group)
                                {{ __($account->name) }}
                            @else
                                <a class="text-decoration-none" href="{{ route('accounts.show', ['id' => $account->id]) }}">{{ $account->name }}</a>
                                @if ($account->is_archived) <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Archived') }}</span>@endif
                            @endif
                        </td>
                        <td>{{ __(ucfirst($account->class)) }}</td>
                        <td class="text-end" dir="ltr">{{ \App\Support\Dec::isZero($balance) ? '' : money($balance) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
