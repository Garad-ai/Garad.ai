@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Discounts and Offers'))
@section('titlebar_subtitle', __('Create and manage discount offers and promotional banners for potential customers.'))

@section('titlebar_actions')
    <x-button
        href="{{ route('dashboard.admin.discount-manager.banner') }}"
        variant="ghost"
        hoverVariant="primary"
    >
        {{ __('Add Promo Banner') }}
    </x-button>
    <x-button
        href="{{ route('dashboard.admin.discount-manager.discount') }}"
        variant="primary"
    >
        <x-tabler-plus class="size-5" />
        {{ __('Add Conditional Discount') }}
    </x-button>
@endsection

@section('content')
    <div class="py-10">
        @include('discount-manager::components.discount-table', ['discounts' => $discounts])

        @if ($app_is_not_demo)
            <div class="mt-1 flex items-center justify-end border-t pt-4">
                <div class="m-0 ms-auto p-0">{{ $discounts->links() }}</div>
            </div>
        @endif
    </div>
@endsection
