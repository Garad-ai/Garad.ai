@extends('panel.layout.app')
@section('title', __('Subscription Payment'))

@section('titlebar_actions', '')

@section('additional_css')
    <style>
        #bank-form {
            width: 100%;
            align-self: center;
            box-shadow: 0px 0px 0px 0.5px rgba(50, 50, 93, 0.055), 0px 2px 5px 0px rgba(50, 50, 93, 0.068), 0px 1px 1.5px 0px rgba(0, 0, 0, 0.021);
            border-radius: 7px;
            padding: 40px;
        }
        .hidden {
            display: none;
        }
    </style>
@endsection

@section('content')
    <!-- Page body -->
    <div class="page-body pt-6">
        <div class="container-xl">
            <div class="row row-cards">
                <div class="col-sm-8 col-lg-8">
                    <form id="checkoutForm"  >
                        @csrf
                        <div class="section">
                            <x-button  class="btn btn-info w-full" type="a"  target="_blank" href="{{ data_get($data, 'url') }}">
                                <span id="button-text">{{ __('Pay') }} {!! displayCurr(currency()->symbol, $plan->price, tax_included: $plan->price_tax_included) !!} {{ __('with') }} cryptomus </span>
                            </x-button>
                        </div>
                    </form>
                    <p>{{ __('By purchasing you confirm our') }} <a href="{{ url('/') . '/terms' }}">{{ __('Terms and Conditions') }}</a> </p>
                </div>
                <div class="col-sm-4 col-lg-4">
                    @include('panel.user.finance.partials.plan_card')
                </div>
            </div>
        </div>
    </div>
@endsection

