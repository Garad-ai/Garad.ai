@extends('panel.layout.settings', [
    'disable_tblr' => true,
])

@section('title', __('Promo Banner'))
@section('titlebar_actions', '')
@section('settings')
    <div x-data="bannerData">
        <form
            action="{{ route('dashboard.admin.discount-manager.banner-save', $bannerInfo?->id) }}"
            method="POST"
            enctype="multipart/form-data"
        >
            @csrf
            <h2 class="mb-4 pb-0">@lang('Promo Banners')</h2>
            <p class="mb-6 text-xs/5 opacity-60 lg:max-w-[360px]">
                @lang('Set up and personalize a static promo banner on your landing page to help attract potential subscribers.')
            </p>
            <div class="flex w-full items-center justify-center gap-3 rounded-sm border py-2 text-blue-600">
                <x-tabler-info-circle-filled class="size-4" />
                <span>
                    @lang('The banner will appear on the landing page.')
                </span>
            </div>

            <div class="flex flex-col gap-4">
                <x-form-step
                    class="my-4"
                    step="1"
                    label="{{ __('Promotion Banner') }}"
                />

                <div class="flex w-full items-center justify-between">
                    <span class="text-2xs font-medium leading-none text-label">@lang('Status')</span>
                    <x-forms.input
                        class="bg-foreground/30 checked:bg-primary"
                        id="active"
                        name="active"
                        type="checkbox"
                        switcher
                        :checked="$bannerInfo?->active == 1"
                    >
                    </x-forms.input>
                </div>

                <x-forms.input
                    id="title"
                    label="{{ __('Banner Title') }}"
                    tooltip="{{ __('Short, attention-grabbing headline displayed on the banner.') }}"
                    size="lg"
                    name="title"
                    @input="Alpine.store('promoBannerData').title=$event.target.value"
                    value="{{ $bannerInfo?->title }}"
                    placeholder="{{ __('Banner Title') }}"
                    required
                />

                <x-forms.input
                    id="description"
                    label="{{ __('Banner Description') }}"
                    tooltip="{{ __('Brief supporting text that explains the offer or message.') }}"
                    size="lg"
                    name="description"
                    @input="Alpine.store('promoBannerData').description=$event.target.value"
                    placeholder="{{ __('Banner Description') }}"
                    required
                    type="textarea"
                    rows="5"
                >{{ $bannerInfo?->description }}</x-forms.input>

                <x-forms.input
                    id="icon"
                    label="{{ __('Icon') }}"
                    tooltip="{{ __('Upload a small image or logo to visually enhance the banner.') }}"
                    size="lg"
                    name="icon"
                    placeholder="{{ __('Icon') }}"
                    type="file"
                    accept="image/*"
                    @change="handleIconUpload($event)"
                />

				<x-forms.input
					id="link"
					name="link"
					size="lg"
					label="{{ __('Banner Link') }}"
					tooltip="{{ __('Optional URL that users will be directed to when they click the banner.') }}"
					placeholder="{{ __('e.g. /dashboard/user/payment (Plans Page)') }}"
					value="{{ $bannerInfo?->link }}"
					@input="Alpine.store('promoBannerData').link = $event.target.value"
				/>

				<x-forms.input
                    id="text_color"
                    label="{{ __('Text Color') }}"
                    tooltip="{{ __('Select the color for banner text to ensure readability and brand alignment.') }}"
                    size="lg"
                    name="text_color"
                    value="{{ $bannerInfo?->text_color }}"
                    placeholder="{{ __('Text Color') }}"
                    @input="$store.promoBannerData.textColor = $event.target.value"
                    required
                    type="color"
                />

                <x-forms.input
                    id="background_color"
                    label="{{ __('Background Color') }}"
                    tooltip="{{ __('Choose a background color that complements the text and theme.') }}"
                    size="lg"
                    name="background_color"
                    @input="$store.promoBannerData.backgroundColor = $event.target.value"
                    value="{{ $bannerInfo?->background_color }}"
                    placeholder="{{ __('Background Color') }}"
                    required
                    type="color"
                />

                <div class="flex w-full items-center justify-between">
                    <span class="text-2xs font-medium leading-none text-label">@lang('Enable Limited Offer Countdown')</span>
                    <x-forms.input
                        class="bg-foreground/30 checked:bg-primary"
                        id="enable_countdown"
                        type="checkbox"
                        name="enable_countdown"
                        switcher
                        @change="handleCountdownToggle($event)"
                        :checked="$bannerInfo?->enable_countdown == 1"
                    >
                    </x-forms.input>
                </div>

                <x-forms.input
                    id="end_date"
                    label="{{ __('End Date') }}"
                    tooltip="{{ __('Set an end date for the promotion; a countdown timer will display if used.') }}"
                    size="lg"
                    ::min="formatDateTimeWithSeconds(minCountDownDate)"
                    name="end_date"
                    type="datetime-local"
                    step="1"
                    value="{{ $bannerInfo?->end_date }}"
                    @input="Alpine.store('promoBannerData').setEndDate($event.target.value)"
                    placeholder="{{ __('End Date') }}"
                    required
                />
            </div>

            <x-button
                class="mt-6 w-full"
				onclick="{{ $app_is_demo ? 'return toastr.info(\'This feature is disabled in Demo version.\')' : '' }}"
				type="{{ $app_is_demo ? 'button' : 'submit' }}"
            >
                @lang('Save Promotion')
                <span class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground">
                    <x-tabler-chevron-right class="size-4" />
                </span>
            </x-button>
        </form>

        @include('discount-manager::components.promo-banner', ['bannerInfo' => $bannerInfo ?? null, 'preview' => true])
    </div>
@endsection

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bannerData', () => ({
                minCountDownDate: new Date(),
                countdownEnabled: false,
                formatDateTimeWithSeconds(date) {
                    if (typeof date === 'string') {
                        return date;
                    }
                    const pad = n => String(n).padStart(2, '0');
                    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
                },
                handleCountdownToggle(event) {
                    this.countdownEnabled = event.target.checked;
                    if (this.$store.promoBannerData) {
                        this.$store.promoBannerData.toggleCountdown(this.countdownEnabled);
                    }
                },
                handleIconUpload(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.$store.promoBannerData.iconPath = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                }
            }));
        });
    </script>
@endpush
