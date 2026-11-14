@extends('layouts.app', ['title' => __('Modules')])

@section('content')
<x-page-header :title="__('Modules')" icon="puzzle-piece" :subtitle="__('Version: :plan', ['plan' => $company->plan->name])" />

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Accounting (every version)') }}</h5></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    @foreach (config('billing.core_modules') as $name)
                        <li class="d-flex gap-2 mb-2"><span class="text-success"><x-icon name="circle-check" /></span><span>{{ __($name) }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card h-100 mb-0">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Business modules') }}</h5></div>
            <div class="card-body">
                @foreach ($modules as $key => $module)
                    @php
                        $on = in_array($key, $active, true);
                    @endphp
                    <div class="d-flex gap-3 align-items-start mb-3">
                        <span class="sc-icon icon-soft {{ $on ? 'success' : 'info' }} flex-shrink-0" style="width:40px;height:40px"><x-icon :name="$module['icon']" /></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ __($module['name']) }}
                                @if ($on)<span class="badge bg-success-subtle text-success-emphasis">{{ __('Included') }}</span>@else<span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Business version') }}</span>@endif
                            </div>
                            <div class="small text-muted">{{ __($module['description']) }}</div>
                        </div>
                    </div>
                @endforeach
                <p class="small text-muted mb-0">{{ $entitled ? __('Your version includes all of these modules. They are always on.') : __('These modules are part of the Business version. Contact support to upgrade.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
