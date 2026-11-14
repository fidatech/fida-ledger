@extends('layouts.app', ['title' => __('Modules')])

@section('content')
<x-page-header :title="__('Modules')" icon="puzzle-piece" :subtitle="__('Version: :plan', ['plan' => $company->plan->name])" />

<div class="card">
    <div class="card-body">
        <p class="small text-muted">{{ __('Accounting, accounts, Money In, Money Out, transfers and the financial reports are always on.') }}</p>

        @if (! $entitled)
            <div class="alert alert-info mb-0">{{ __('The Business modules (products, purchasing, inventory, sales, point of sale, fixed assets and payroll) are part of the Business version. Contact support to upgrade.') }}</div>
        @else
            <form method="POST" action="{{ route('settings.modules.update') }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    @foreach ($modules as $key => $module)
                        @continue(! in_array($key, $entitled, true))
                        <div class="col-12 col-md-6">
                            <div class="card h-100 mb-0"><div class="card-body d-flex gap-3 align-items-start">
                                <span class="sc-icon icon-soft primary flex-shrink-0" style="width:40px;height:40px"><x-icon :name="$module['icon']" /></span>
                                <div class="flex-grow-1">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" name="modules[]" id="module-{{ $key }}" value="{{ $key }}" @checked(in_array($key, $active, true))>
                                        <label class="form-check-label fw-semibold" for="module-{{ $key }}">{{ __($module['name']) }}</label>
                                    </div>
                                    <div class="small text-muted">{{ __($module['description']) }}</div>
                                    @if ($module['requires'])
                                        <div class="small text-muted mt-1">{{ __('Needs') }}: {{ implode(', ', array_map(fn ($need) => __($modules[$need]['name']), $module['requires'])) }}</div>
                                    @endif
                                </div>
                            </div></div>
                        </div>
                    @endforeach
                </div>
                <p class="small text-muted mt-3">{{ __('Switching a module off hides its screens. Its records and journal entries are kept and come back when you switch it on again. There is no charge for switching modules of your version.') }}</p>
                <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
