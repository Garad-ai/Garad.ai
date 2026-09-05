<div x-data="{ disabled: '{{ $defaultAgent?->id }}' === null }">
	<x-dropdown.dropdown
		class="lqd-chat-category-dropdown static"
		class:dropdown-dropdown="end-2 start-2 max-h-[calc(100vh-270px)] overflow-y-auto overscroll-contain rounded-b-xl rounded-t-none shadow-[0_4px_20px_rgba(0,0,0,0.07)] sm:max-h-[calc(var(--chats-container-height,500px)-30px)] lg:end-4 lg:start-4"
		class:dropdown="w-full"
		triggerType="click"
		:teleport="false"
		x-init="$el.addEventListener('click', e => { if(disabled) e.stopImmediatePropagation() })"
	>
		<x-slot:trigger
			class="gap-0.5 before:content-none hover:no-underline lg:gap-4"
		>
        <span
			class="lqd-chat-category-avatar inline-flex size-11 items-center justify-center overflow-hidden overflow-ellipsis whitespace-nowrap rounded-full text-2xs font-medium text-foreground/65 transition-all group-hover:-translate-y-0.5"
			style="background: {{ optional($defaultAgent)->brand_color ?? '#EFEFEF' }};"
		>
            @if ($defaultAgent?->slug === 'ai-chat-bot')
				<img
					class="lqd-chat-avatar-img size-full object-cover object-center"
					src="{{ custom_theme_url('/assets/img/chat-default.jpg') }}"
					alt="{{ __($defaultAgent->name ?? '') }}"
				>
			@elseif ($defaultAgent?->image)
				<img
					class="lqd-chat-avatar-img size-full object-cover object-center"
					src="{{ custom_theme_url($defaultAgent->image, true) }}"
					alt="{{ __($defaultAgent->name) }}"
				>
			@else
				<span class="block w-full overflow-hidden overflow-ellipsis whitespace-nowrap text-center">
                    {{ __($defaultAgent->short_name ?? Str::limit($defaultAgent->name ?? '', 2, '')) }}
                </span>
			@endif
        </span>

			<span class="lqd-chat-category-info m-0 flex flex-col gap-1 text-xs">
            <span class="lqd-chat-category-name flex items-center justify-center gap-1 rounded-full bg-foreground/5 px-2 py-1 font-semibold leading-tight max-sm:size-6 max-sm:p-0">
                <span class="max-sm:hidden">
                    {{ $defaultAgent->name ?? __('Select Agent') }}
                </span>

                {{-- Only show the dropdown arrow if not disabled --}}
                <template x-if="!disabled">
                    <x-tabler-chevron-down class="size-4 transition-transform group-[&.lqd-is-active]/dropdown:rotate-180" />
                </template>
            </span>

            @if ($defaultAgent?->tone)
					<span class="lqd-chat-category-role m-0 block text-2xs text-heading-foreground/60 max-sm:hidden">
                    {{ __('Tone:') }} {{ __($defaultAgent->tone) }}
                </span>
				@endif
        </span>
		</x-slot:trigger>

		<x-slot:dropdown><template x-if="!disabled">
				@auth
					<div
						class="flex flex-col gap-3 px-4 py-4 sm:px-7"
						x-data="{ searchString: '' }"
						x-trap="open"
					>
						<x-forms.input
							class="lqd-dropdown-dropdown-search rounded-full border-clay bg-clay ps-10"
							container-class="mb-2"
							type="search"
							placeholder="{{ __('Search for chatbots') }}"
							x-model="searchString"
						>
							<x-tabler-search class="absolute start-3 top-1/2 size-5 -translate-y-1/2" />
							<svg
								class="absolute end-3 top-1/2 -translate-y-1/2"
								width="15"
								height="11"
								viewBox="0 0 15 11"
								fill="currentColor"
								xmlns="http://www.w3.org/2000/svg"
							>
								<path d="M5.83333 10.5V8.83333H9.16667V10.5H5.83333ZM2.5 6.33333V4.66667H12.5V6.33333H2.5ZM0 2.16667V0.5H15V2.16667H0Z" />
							</svg>
						</x-forms.input>

						@foreach ($agents ?? [] as $availableAgent)
							<div
								class="relative flex items-center gap-3 rounded-xl border px-5 py-3 transition-all hover:scale-[1.02] hover:shadow-lg hover:shadow-black/5"
								data-search-text="{{ e($availableAgent->name . ' ' . ($availableAgent->branding_description ?? '')) }}"
								x-show="searchString === '' || $el.getAttribute('data-search-text').toLowerCase().includes(searchString.toLowerCase())"
							>
								<div
									class="lqd-chat-item-avatar inline-flex size-11 shrink-0 items-center justify-center overflow-hidden overflow-ellipsis whitespace-nowrap rounded-full border border-solid border-white/90 text-lg font-semibold text-black/65 shadow-[0_1px_2px_rgba(0,0,0,0.07)] transition-shadow group-hover:shadow-xl dark:border-current"
									style="background: {{ $availableAgent->brand_color ?? '#EFEFEF' }};"
								>
{{--									@if ($availableAgent->image)--}}
{{--										<img--}}
{{--											class="lqd-chat-avatar-img size-full rounded-full object-cover object-center"--}}
{{--											src="{{ $availableAgent->image }}"--}}
{{--											loading="lazy"--}}
{{--											alt="{{ __($availableAgent->name) }}"--}}
{{--										>--}}
{{--									@else--}}
										<span class="block w-full overflow-hidden overflow-ellipsis whitespace-nowrap text-center">
                                        {{ __($availableAgent->short_name ?? Str::limit($availableAgent->name, 2, '')) }}
                                    </span>
{{--									@endif--}}
								</div>

								<div>
									<h4 class="m-0">{{ $availableAgent->name }}</h4>
									<p class="m-0 text-2xs">{{ Str::limit($availableAgent->branding_description, 80) }}</p>
								</div>
								<a
									class="absolute inset-0"
									href="{{ route('dashboard.user.social-media.agent.chat.index', $availableAgent->id) }}"
								></a>
							</div>
						@endforeach
					</div>
				@else
					<div class="flex h-full flex-col items-center justify-center text-center">
						<svg class="mx-auto mb-4" width="111" height="111" viewBox="0 0 111 111" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M55.5 111C86.1518 111 111 86.1518 111 55.5C111 24.8482 86.1518 0 55.5 0C24.8482 0 0 24.8482 0 55.5C0 86.1518 24.8482 111 55.5 111Z" fill="hsl(var(--heading-foreground))" fill-opacity="0.1" />
							<path d="M88.7992 111H22.1992V39.22C25.3383 39.2165 28.3478 37.9679 30.5675 35.7483C32.7871 33.5286 34.0357 30.5191 34.0392 27.38H76.9592C76.9559 28.935 77.261 30.4753 77.8568 31.9116C78.4527 33.3479 79.3275 34.6519 80.4306 35.7479C81.5266 36.8513 82.8306 37.7264 84.267 38.3224C85.7035 38.9184 87.244 39.2235 88.7992 39.22V111Z" fill="hsl(var(--background))" />
							<path d="M65.1202 79.92H45.8802C44.6541 79.92 43.6602 80.914 43.6602 82.14C43.6602 83.3661 44.6541 84.36 45.8802 84.36H65.1202C66.3462 84.36 67.3402 83.3661 67.3402 82.14C67.3402 80.914 66.3462 79.92 65.1202 79.92Z" fill="hsl(var(--heading-foreground))" fill-opacity="0.1" />
							<path d="M65.1202 48.84H45.8802C44.6541 48.84 43.6602 49.8339 43.6602 51.06C43.6602 52.286 44.6541 53.28 45.8802 53.28H65.1202C66.3462 53.28 67.3402 52.286 67.3402 51.06C67.3402 49.8339 66.3462 48.84 65.1202 48.84Z" fill="hsl(var(--heading-foreground))" fill-opacity="0.1" />
							<path d="M71.78 88.8H39.22C37.9939 88.8 37 89.794 37 91.02C37 92.2461 37.9939 93.24 39.22 93.24H71.78C73.0061 93.24 74 92.2461 74 91.02C74 89.794 73.0061 88.8 71.78 88.8Z" fill="hsl(var(--heading-foreground))" fill-opacity="0.1" />
							<path d="M71.78 57.72H39.22C37.9939 57.72 37 58.7139 37 59.94C37 61.166 37.9939 62.16 39.22 62.16H71.78C73.0061 62.16 74 61.166 74 59.94C74 58.7139 73.0061 57.72 71.78 57.72Z" fill="hsl(var(--heading-foreground))" fill-opacity="0.1" />
						</svg>
						<h3 class="mx-auto lg:w-9/12">
                        <span class="opacity-40">
                            {{ __('Temporary Chat') }}
                        </span>
							<br>
							{{ __('Login to access your chatbots.') }}
						</h3>
					</div>
				@endauth
			</template>
		</x-slot:dropdown>
	</x-dropdown.dropdown>
</div>
