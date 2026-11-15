@extends('layouts.app', ['title' => __('Import Products')])

@section('content')
<x-page-header :title="__('Import Products')" icon="file-arrow-up" :subtitle="__('Bring your product list in from an Excel sheet saved as CSV.')" :crumbs="[[__('All Products'), route('products.index', ['type' => 'product'])], [__('Import')]]">
    <a class="btn btn-outline-soft" href="{{ route('products.import.template') }}"><x-icon name="file-arrow-down" class="me-1" /> {{ __('Example file') }}</a>
</x-page-header>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="file">{{ __('File (CSV)') }} <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="file" name="file" accept=".csv,.txt" required>
                    <div class="form-text">{{ __('In Excel: File > Save As > CSV UTF-8. At most :max products in one file.', ['max' => number_format(\App\Inventory\ProductImport::MAX_ROWS)]) }}</div>
                </div>
                @if ($stock)
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-7">
                            <label class="form-label" for="warehouse_id">{{ __('Opening stock goes to') }}</label>
                            <select class="form-select" id="warehouse_id" name="warehouse_id" data-plain>
                                <option value="">{{ __('Do not enter stock') }}</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id') === $warehouse->id)>{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-5">
                            <label class="form-label" for="date">{{ __('Date') }}</label>
                            <x-date name="date" id="date" :value="old('date', today_iso())" />
                        </div>
                    </div>
                @endif
                <button type="submit" class="btn btn-primary"><x-icon name="file-arrow-up" class="me-1" /> {{ __('Import') }}</button>
                <a class="btn btn-outline-soft" href="{{ route('products.index', ['type' => 'product']) }}">{{ __('app.actions.cancel') }}</a>
            </div>
        </form>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('How the file is read') }}</h5></div>
            <div class="card-body small">
                <p>{{ __('The first row holds the column names. Only "Name" is needed; the others are optional:') }}</p>
                <p dir="ltr" class="font-monospace">Name, Code, Barcode, Category, Unit, Selling price, Purchase price, Tax %, Reorder level, Quantity</p>
                <ul class="mb-0 ps-3">
                    <li>{{ __('A row whose code (or, without a code, whose name) already exists updates that product.') }}</li>
                    <li>{{ __('Categories and units that do not exist yet are created.') }}</li>
                    <li>{{ __('Quantity is opening stock for new products only, valued at the purchase price. Importing the same file again does not add stock twice.') }}</li>
                    <li>{{ __('If one row is wrong, nothing is saved and the row is named.') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
