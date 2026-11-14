@extends('layouts.admin', ['title' => __('Module Entitlements')])

@section('content')
@php
    $super = auth('admin')->user()->isSuper();
@endphp
<x-page-header :title="__('Module Entitlements')" icon="puzzle-piece" :subtitle="__('Which version includes each Business module. Companies do not choose modules: they get what their version includes.')" />

@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('admin.modules.update') }}" class="card" data-confirm="{{ __('Save and apply to every company?') }}" data-confirm-text="{{ __('A module taken out of a version disappears from its companies at once. Their records are kept.') }}" data-confirm-ok="{{ __('Save') }}" data-confirm-style="primary">
    @csrf
    @method('PUT')
    <div class="card-body">
        <table class="table align-middle w-100 mb-0">
            <thead><tr><th>{{ __('Module') }}</th><th>{{ __('Needs') }}</th><th class="text-center">{{ __('Offered') }}</th>@foreach ($plans as $plan)<th class="text-center">{{ $plan->name }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($definitions as $key => $module)
                    @php
                        $row = $rows[$key] ?? null;
                    @endphp
                    <tr>
                        <td><span class="fw-semibold">{{ __($module['name']) }}</span><span class="d-block small text-muted">{{ __($module['description']) }}</span></td>
                        <td class="small">{{ implode(', ', array_map(fn ($need) => __($definitions[$need]['name']), $module['requires'])) }}</td>
                        <td class="text-center"><input class="form-check-input" type="checkbox" name="available[{{ $key }}]" value="1" @checked($row?->is_available) @disabled(! $super) aria-label="{{ __('Offered') }}"></td>
                        @foreach ($plans as $plan)
                            <td class="text-center"><input class="form-check-input" type="checkbox" name="plans[{{ $key }}][]" value="{{ $plan->code }}" @checked(in_array($plan->code, $row?->plans ?? [], true)) @disabled(! $super) aria-label="{{ $plan->name }}"></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="small text-muted mt-3 mb-0">{{ __('Core accounting (Money In and Out, accounts, transfers, invoicing, reports) is in every version and is not listed here.') }}</p>
    </div>
    @if ($super)<div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary">{{ __('Save and apply') }}</button></div>@endif
</form>
@endsection
