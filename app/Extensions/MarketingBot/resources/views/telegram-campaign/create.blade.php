@extends('panel.layout.app', ['disable_titlebar' => false, 'disable_tblr' => true])
@section('title', $title)

@section('content')
    <div
        class="flex flex-wrap justify-between gap-8 py-5 lg:flex-nowrap"
        x-data="campaignData"
    >
        <div class="lqd-chatbot-edit-window-options w-full lg:w-5/12 lg:shrink-0 lg:py-12 lg:ps-8">
            <h2 class="mb-4 pb-0">@lang($title)</h2>
            <p class="mb-6 text-xs/5 opacity-60 lg:max-w-[360px]">
                @lang('Create a new Whatsapp campaign and choose whether to send it instantly or schedule it for later to maximize engagement.')
            </p>
            <form
                class="flex flex-col gap-5"
                method="post"
                action="{{ $action }}"
                enctype="multipart/form-data"
            >
                @csrf
                @method($method)
                <x-forms.input
                    id="name"
                    :tooltip="trans('Enter a short, clear name for your campaign. This helps you identify it later.')"
                    size="lg"
                    label="{{ __('Campaign Title') }}"
                    name="name"
                    required
                    value="{{ old('name', $item?->name) }}"
                />
                <x-forms.input
                    class:label="text-heading-foreground"
                    :tooltip="trans('Write the message you want to send to your audience.')"
                    label="{{ __('Content') }}"
                    placeholder="{{ __('Type the message you want to send to contacts') }}"
                    name="content"
                    size="lg"
                    type="textarea"
                    rows="8"
                    x-model="content"
                >
                    <x-slot:label-extra>

                        <x-modal title="{{ __('Campaign content generate') }}">
                            <x-slot:trigger
                                class="text-2xs font-semibold text-primary"
                                variant="link"
                            >
                                <x-button
                                    class="text-2xs"
                                    type="button"
                                    variant="link"
                                >
                                    <span class="me-1 inline-grid place-items-center">
                                        <svg
                                            class="col-start-1 col-end-1 row-start-1 row-end-1"
                                            :class="{ hidden: generatingContent }"
                                            width="17"
                                            height="17"
                                            viewBox="0 0 17 17"
                                            fill="none"
                                            xmlns="http://www.w3.org/2000/svg"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                clip-rule="evenodd"
                                                d="M16.5085 6.34955L15.113 6.63248C14.4408 6.7689 13.8236 7.10033 13.3386 7.58536C12.8536 8.0704 12.5221 8.68757 12.3857 9.35981L12.1028 10.7552C12.0748 10.8948 11.9994 11.0203 11.8893 11.1105C11.7792 11.2007 11.6412 11.25 11.4989 11.25C11.3566 11.25 11.2187 11.2007 11.1086 11.1105C10.9985 11.0203 10.923 10.8948 10.895 10.7552L10.6121 9.35981C10.4758 8.68751 10.1444 8.07027 9.65938 7.58522C9.17432 7.10016 8.55709 6.76878 7.8848 6.63248L6.48937 6.34955C6.35011 6.32107 6.22495 6.24537 6.13507 6.13525C6.04519 6.02513 5.99609 5.88733 5.99609 5.74519C5.99609 5.60304 6.04519 5.46526 6.13507 5.35514C6.22495 5.24502 6.35011 5.16932 6.48937 5.14084L7.8848 4.8579C8.55709 4.7216 9.17432 4.39022 9.65938 3.90516C10.1444 3.42011 10.4758 2.80288 10.6121 2.13058L10.895 0.73517C10.923 0.595627 10.9985 0.470081 11.1086 0.379882C11.2187 0.289682 11.3566 0.240395 11.4989 0.240395C11.6412 0.240395 11.7792 0.289682 11.8893 0.379882C11.9994 0.470081 12.0748 0.595627 12.1028 0.73517L12.3857 2.13058C12.5221 2.80283 12.8536 3.41999 13.3386 3.90503C13.8236 4.39007 14.4408 4.72148 15.113 4.8579L16.5085 5.14084C16.6477 5.16932 16.7729 5.24502 16.8627 5.35514C16.9526 5.46526 17.0017 5.60304 17.0017 5.74519C17.0017 5.88733 16.9526 6.02513 16.8627 6.13525C16.7729 6.24537 16.6477 6.32107 16.5085 6.34955ZM6.30231 13.4219L5.92312 13.4989C5.45558 13.5937 5.02634 13.8242 4.689 14.1616C4.35167 14.4989 4.12118 14.9281 4.02633 15.3957L3.94934 15.7749C3.92805 15.881 3.87064 15.9766 3.78687 16.0452C3.70309 16.1139 3.59813 16.1514 3.48982 16.1514C3.38151 16.1514 3.27654 16.1139 3.19277 16.0452C3.10899 15.9766 3.05157 15.881 3.03029 15.7749L2.9533 15.3957C2.85844 14.9281 2.62796 14.4989 2.29062 14.1616C1.95328 13.8242 1.52404 13.5937 1.0565 13.4989L0.677333 13.4219C0.571137 13.4006 0.475582 13.3432 0.406935 13.2594C0.338287 13.1756 0.300781 13.0707 0.300781 12.9624C0.300781 12.854 0.338287 12.7491 0.406935 12.6653C0.475582 12.5815 0.571137 12.5241 0.677333 12.5028L1.0565 12.4258C1.52404 12.331 1.95328 12.1005 2.29062 11.7632C2.62796 11.4258 2.85844 10.9966 2.9533 10.5291L3.03029 10.1499C3.05157 10.0437 3.10899 9.94813 3.19277 9.87948C3.27654 9.81083 3.38151 9.77334 3.48982 9.77334C3.59813 9.77334 3.70309 9.81083 3.78687 9.87948C3.87064 9.94813 3.92805 10.0437 3.94934 10.1499L4.02633 10.5291C4.12118 10.9966 4.35167 11.4258 4.689 11.7632C5.02634 12.1005 5.45558 12.331 5.92312 12.4258L6.30231 12.5028C6.4085 12.5241 6.50404 12.5815 6.57269 12.6653C6.64134 12.7491 6.67884 12.854 6.67884 12.9624C6.67884 13.0707 6.64134 13.1756 6.57269 13.2594C6.50404 13.3432 6.4085 13.4006 6.30231 13.4219Z"
                                                fill="url(#paint0_linear_8906_3722)"
                                            />
                                            <defs>
                                                <linearGradient
                                                    id="paint0_linear_8906_3722"
                                                    x1="17.0017"
                                                    y1="8.19589"
                                                    x2="0.137511"
                                                    y2="6.25241"
                                                    gradientUnits="userSpaceOnUse"
                                                >
                                                    <stop stop-color="#8D65E9" />
                                                    <stop
                                                        offset="0.483"
                                                        stop-color="#5391E4"
                                                    />
                                                    <stop
                                                        offset="1"
                                                        stop-color="#6BCD94"
                                                    />
                                                </linearGradient>
                                            </defs>
                                        </svg>
                                        <x-tabler-refresh
                                            class="col-start-1 col-end-1 row-start-1 row-end-1 hidden size-4 animate-spin"
                                            x-show="generatingContent"
                                        />
                                    </span>
                                    @lang('Enhance with AI')
                                </x-button>
                            </x-slot:trigger>
                            <x-slot:modal>
                                <form class="flex flex-col gap-6">
                                    <x-forms.input
                                        id="prompt"
                                        x-model="prompt"
                                        size="lg"
                                        label="{{ __('Prompt') }}"
                                        name="prompt"
                                        required
                                    />
                                    <div class="mt-4 flex justify-between border-t pt-3">
                                        <div>
                                            <x-button
                                                type="button"
                                                variant="primary"
                                                @click.prevent="generateContent()"
                                            >
                                                {{ __('Generate Content') }}
                                            </x-button>

                                        </div>
                                        <x-button
                                            @click.prevent="modalOpen = false"
                                            variant="outline"
                                        >
                                            {{ __('Cancel') }}
                                        </x-button>
                                    </div>
                                </form>
                            </x-slot:modal>
                        </x-modal>
                    </x-slot:label-extra>
                    {{ old('content', $item?->content) }}
                </x-forms.input>
                <x-forms.input
                    class:label="text-heading-foreground"
                    type="select"
                    multiple=""
                    size="lg"
                    name="contacts[]"
                    label="{{ __('Select contacts') }}"
                >
                    @foreach ($contacts as $contact)
                        <option
                            {{ in_array((string) $contact->id, old('contacts', $selectedContact), true) ? 'selected' : '' }}
                            value="{{ $contact->id }}"
                        >
                            {{ data_get($contact, 'name') }}
                        </option>
                    @endforeach
                </x-forms.input>
                <x-forms.input
                    class:container="mb-2"
                    id="is_scheduled"
                    :tooltip="trans(
                        'This is optional. Choose a date and time to automatically send this campaign to your audience on a specific time. Keep it disabled if you want to send the campaign now.',
                    )"
                    type="checkbox"
                    name="is_scheduled"
                    :checked="(bool) $item?->scheduled_at"
                    label="{{ __('Schedule Campaign') }}"
                    switcher
                    x-model="isScheduled"
                />
                <div
                    x-show="isScheduled"
                    x-transition
                >
                    <x-forms.input
                        id="scheduled_at"
                        type="datetime-local"
                        size="lg"
                        label="{{ __('Schedule Date') }}"
                        name="scheduled_at"
                        value="{{ old('scheduled_at', $item?->scheduled_at) }}"
                    />
                </div>
                @if ($app_is_demo)
                    <x-button
                        class="w-full text-2xs font-semibold"
                        variant="secondary"
                        type="button"
                        onclick="return toastr.info('This feature is disabled in Demo version.');"
                    >
                        <span x-show="isScheduled">
                            @lang('Schedule Campaign')
                        </span>
                        <span x-show="!isScheduled">
                            @lang('Send Campaign')
                        </span>
                        <span
                            class="inline-grid size-7 place-items-center rounded-full bg-background text-heading-foreground shadow-xl"
                            aria-hidden="true"
                        >
                            <x-tabler-chevron-right class="size-4" />
                        </span>
                    </x-button>
                @else
                    <x-button
                        class="w-full text-2xs font-semibold"
                        @click="postNow"
                        variant="secondary"
                        type="submit"
                    >
                        <span x-show="isScheduled">
                            @lang('Schedule Campaign')
                        </span>
                        <span x-show="!isScheduled">
                            @lang('Send Campaign')
                        </span>
                        <span
                            class="inline-grid size-7 place-items-center rounded-full bg-background text-heading-foreground shadow-xl"
                            aria-hidden="true"
                        >
                            <x-tabler-chevron-right class="size-4" />
                        </span>
                    </x-button>
                @endif
            </form>
        </div>

        <div class="hidden w-full lg:grid lg:grow lg:py-16">
            <div class="col-start-1 col-end-1 row-start-1 row-end-1 flex w-full items-center justify-center rounded-3xl bg-heading-foreground/5 p-5 transition-all lg:py-10">

                <div class="mx-auto mt-10 min-w-80 max-w-80 overflow-hidden rounded-2xl border border-gray-100 bg-[#f1f2f6] shadow-lg">
                    <div class="border-b border-gray-300 bg-white px-4 py-4">
                        <p class="m-0 text-sm font-semibold text-black">Phone Number</p>
                    </div>
                    <div
                        class="relative min-h-[500px] bg-cover bg-center p-4"
                        style="background-image: url('{{ asset('vendor/marketing-bot/images/telegram-background.png') }}');"
                    >
                        <div class="flex justify-start px-4 py-2">
                            <div class="relative min-w-[90%] max-w-[90%]">
                                <!-- Kuyruk -->
                                <div
                                    class="absolute -left-2 bottom-2 h-0 w-0 border-b-[10px] border-r-[10px] border-t-[10px] border-b-transparent border-r-white border-t-transparent">
                                </div>
                                <!-- Balon -->
                                <div class="rounded-2xl bg-white px-4 py-2 text-[15px] leading-tight text-gray-800 shadow">
                                    <p
                                        class="break-words"
                                        x-text="content || 'This is what your campaign will look like.'"
                                    >This is what your campaign will look like.</p>
                                    <span class="mt-1 block text-right text-[12px] text-gray-400">09:30 AM</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script')
    <script>
        function campaignData() {
            return {
                content: `{!! old('content', $item?->content) !!}`,
                prompt: '',
                generatingContent: false,
                modalOpen: false,
                isScheduled: {!! $item?->scheduled_at ? 'true' : 'false' !!},
                generateContent() {

                    let route = '{{ route('dashboard.user.marketing-bot.generate.content') }}';

                    this.generatingContent = true;

                    $.ajax({
                        url: route,
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            prompt: this.prompt,
                        },
                        success: (response) => {
                            if (response.status === 'success') {

                                this.content = response.content;
                                toastr.success(response.message);
                            } else {
                                toastr.error(response.message);
                            }
                        },
                        error: (xhr) => {
                            toastr.error(xhr.responseJSON.message || '{{ trans('An error occurred while generating content.') }}');
                        },
                        complete: () => {
                            this.generatingContent = false;
                        }
                    });
                },
            }
        }
    </script>
@endpush
