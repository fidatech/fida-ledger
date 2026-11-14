@extends('layouts.admin', ['title' => __('Subscription Plans')])

@section('content')
<x-page-header :title="__('Subscription Plans')" icon="layer-group" :subtitle="__('The setup fee is charged once per company; the annual fee every year. New prices apply to payments sent from now on.')" />

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
<div class="row g-3">
    @foreach ($plans as $plan)
        <div class="col-12 col-lg-6">
            <form method="POST" action="{{ route('admin.plans.update', ['id' => $plan->id]) }}" class="card h-100 mb-0">
                @csrf
                @method('PUT')
                <div class="card-header"><h5 class="card-title mb-0">{{ $plan->name }} <span class="small text-muted" dir="ltr">({{ $plan->code }})</span></h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="name-{{ $plan->id }}">{{ __('Name') }}</label>
                            <input type="text" class="form-control" id="name-{{ $plan->id }}" name="name" value="{{ $plan->name }}" maxlength="100" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description-{{ $plan->id }}">{{ __('Description') }}</label>
                            <textarea class="form-control" id="description-{{ $plan->id }}" name="description" rows="2" maxlength="500">{{ $plan->description }}</textarea>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="setup-{{ $plan->id }}">{{ __('Setup fee') }} ({{ $plan->currency }})</label>
                            <input type="text" inputmode="decimal" class="form-control" id="setup-{{ $plan->id }}" name="setup_fee" value="{{ $plan->setup_fee }}" dir="ltr" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="annual-{{ $plan->id }}">{{ __('Annual fee') }} ({{ $plan->currency }})</label>
                            <input type="text" inputmode="decimal" class="form-control" id="annual-{{ $plan->id }}" name="annual_fee" value="{{ $plan->annual_fee }}" dir="ltr" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="users-{{ $plan->id }}">{{ __('User limit') }}</label>
                            <input type="number" class="form-control" id="users-{{ $plan->id }}" name="max_users" value="{{ $plan->max_users }}" min="1" dir="ltr" placeholder="{{ __('No limit') }}">
                        </div>
                    </div>
                </div>
                @if (auth('admin')->user()->isSuper())
                    <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
                @endif
            </form>
        </div>
    @endforeach
</div>
@endsection
