@php
    $filters = ['Keywords', 'Headers', 'Links', 'Images'];
@endphp
<style>
    @-webkit-keyframes loadingPlaceholders {
        0% {
            background-color: lightgray;
        }

        50% {
            background-color: #e5e5e5;
        }

        100% {
            background-color: lightgray;
        }
    }

    @keyframes loadingPlaceholders {
        0% {
            background-color: lightgray;
        }

        50% {
            background-color: #e5e5e5;
        }

        100% {
            background-color: lightgray;
        }
    }

    .placeholder {
        -webkit-animation: loadingPlaceholders 1.5s ease-in infinite;
        animation: loadingPlaceholders 1.5s ease-in infinite;
        background-color: #e5e5e5;
        width: 25%;
        margin: 2px;
        height: 1.5em;
    }

    .contenteditable-container {
        position: relative;
        width: 100%;
        /* Adjust as needed */
    }

    .contenteditable-placeholder::before {
        content: attr(data-placeholder);
        position: absolute;
        color: #b2b0b0;
        pointer-events: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .contenteditable-placeholder:empty::before {
        display: block;
    }

    .contenteditable-placeholder:not(:empty)::before {
        display: none;
    }

    #content_scan {
        height: 600px;
        /* Ensure div height to match the placeholder height */
        overflow-y: auto;
        /* Padding inside the contenteditable div */
        box-sizing: border-box;
        /* Include padding in the element's total width and height */
    }
</style>
<div class="lqd-plagiarism-wrap flex flex-wrap justify-between gap-y-5">
    <div class="w-full lg:w-1/3">
        <h3 class="flex items-center justify-center gap-2 text-center">
            <x-tabler-refresh class="size-5 refresh-icon hidden animate-spin group-[&.lqd-is-busy]:block" />
            {{ __('SEO Report') }}
        </h3>
        <div class="relative">
            <p class="total_percent absolute left-1/2 top-[calc(50%-5px)] m-0 -translate-x-1/2 text-center text-heading-foreground">
                <span class="text-[23px] font-bold">0</span>%
            </p>
            <div
                class="relative rounded-lg [&_.apexcharts-legend-text]:!m-0 [&_.apexcharts-legend-text]:!pe-2 [&_.apexcharts-legend-text]:ps-2 [&_.apexcharts-legend-text]:!text-heading-foreground"
                id="chart-credit"
            ></div>
        </div>
        <div class="form-group my-2 flex">
            <select
                class="form-control w-1/3 rounded-l-lg border border-r-0"
                id="type"
            >
                <option selected="selected">{{ __('Blog/Article') }}</option>
                <option>{{ __('Product Description') }}</option>
            </select>
            <input
                class="form-control w-2/3 rounded-r-lg border"
                id="keyword"
                type="text"
                placeholder="{{ __('Enter Keyword/Topic') }}"
				value="{{ $app_is_demo ? 'Food culture examples' : '' }}"
            />

        </div>
        <h4
            class="mb-4 mt-5 @if($app_is_not_demo) hidden @endif "
            id="suggText"
        >
            {{ __('Suggested Keywords/Topics') }}:
        </h4>
        <div
            class="mb-5 flex w-full flex-wrap gap-3 text-xs empty:hidden"
            id="suggested_keywords"
        >
			@if($app_is_demo)
				<div class="keyword bg-secondary p-1 rounded-xl cursor-pointer" style="font-size: 0.8rem;">Relationship between food and culture examples</div><div class="keyword bg-secondary p-1 rounded-xl cursor-pointer" style="font-size: 0.8rem;">Relationship between food and culture PDF</div><div class="keyword bg-secondary p-1 rounded-xl cursor-pointer" style="font-size: 0.8rem;">Importance of food culture identity</div><div class="keyword bg-secondary p-1 rounded-xl cursor-pointer" style="font-size: 0.8rem;">Cultural food traditions</div><div class="keyword bg-secondary p-1 rounded-xl cursor-pointer" style="font-size: 0.8rem;">Food culture examples</div><div class="keyword bg-secondary p-1 rounded-xl cursor-pointer" style="font-size: 0.8rem;">What is food culture</div><div class="keyword bg-secondary p-1 rounded-xl cursor-pointer" style="font-size: 0.8rem;">The importance of food in culture</div><div class="keyword bg-secondary p-1 rounded-xl cursor-pointer" style="font-size: 0.8rem;">Food, Culture and Society</div>
			@endif
		</div>

        <div class="mb-2 flex justify-end gap-1">
			@if($app_is_demo)
				<x-button
					class="mt-2 w-full"
					id="analys_btn"
					type="button"
					onclick="return toastr.error('This feature is disabled in Demo version.')"
					size="lg"
				>
					{{ __('Start Analysis') }}
				</x-button>
				<x-button
					class="mt-2 hidden"
					id="reanalys_btn"
					type="button"
					onclick="return toastr.error('This feature is disabled in Demo version.')"
				>
					{{ __('Re Analysis') }}
				</x-button>
			@else
				<x-button
					class="mt-2 w-full"
					id="analys_btn"
					type="button"
					onclick="return sendScanRequest()"
					size="lg"
				>
					{{ __('Start Analysis') }}
				</x-button>
				<x-button
					class="mt-2 hidden"
					id="reanalys_btn"
					type="button"
					onclick="return sendScanRequest()"
				>
					{{ __('Re Analysis') }}
				</x-button>
			@endif

            @if (setting('serper_seo_tool_improve', 0) == 1)
                <x-button
                    class="mt-2 hidden"
                    id="improve-seo-btn"
                    @click="improveSeo"
                    variant="primary"
                >
                    {{ __('Improve SEO') }}
                </x-button>
            @endif
        </div>

        <div
            class="hidden"
            id="seo_report"
        >
            <ul class="flex w-full justify-between gap-3 rounded-lg bg-foreground/10 p-1 text-xs font-medium">
                @foreach ($filters as $filter)
                    <li>
                        <button
                            @class([
                                'px-3 py-3 leading-tight  transition-all hover:bg-background/80 [&.lqd-is-active]:bg-background [&.lqd-is-active]:shadow-[0_2px_12px_hsl(0_0%_0%/10%)]',
                                'lqd-is-active' => $loop->first,
                            ])
                            :class="{ 'lqd-is-active': false }"
                        >
                            @lang($filter)
                            <br>
                            <span class= 'count_{{ $filter }} lqd-change-indicator mt-1 inline-flex items-center rounded-md px-1.5 py-0.5 text-3xs text-xs leading-snug'>
                                <span class="numbers"></span>
                                <x-tabler-chevron-down
                                    class="size-3 down ms-1 hidden"
                                    stroke-width="1.5"
                                />
                                <x-tabler-chevron-up
                                    class="size-3 up ms-1 hidden"
                                    stroke-width="1.5"
                                />
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>

            <x-card
                class="group m-4"
                size="none"
                variant="none"
            >
                <p>{{ __('Competitor Keywords') }}:</p>
                <span class="content_competitorList"></span>

                <p>{{ __('Long Tail Keywords') }}:</p>
                <span class="content_longTailList"></span>
            </x-card>
        </div>

        <div class="lqd-plagiarism-results scan_results flex w-full flex-col gap-5">
            <div class="select_area w-full">
                <div
                    class="flex w-full flex-wrap gap-3"
                    id="select_keywords"
                >
                </div>
            </div>
        </div>

    </div>

    <div class="w-full lg:w-2/3 lg:ps-14">
        <x-card size="xs">
            <h4 class="my-0">
                {{ __('Add Content') }}

                <small
                    class="ms-3 font-normal"
                    id="content_length"
                >
					{{ $app_is_demo ? 2108 : 0 }}/5000
				</small>
            </h4>
        </x-card>
        <div class="contenteditable-container mt-2">
            <div
                class="contenteditable-placeholder h-[600px] rounded-lg border p-4"
                id="content_scan"
                data-placeholder="{{ __('Content Here...') }}"
                contenteditable="true"
            >@if($app_is_demo)
					Food culture is a rich tapestry that embodies history, tradition, identity, and community. It is more than just sustenance; food culture is a profound expression of humanity itself. Every cuisine has its unique stories and practices, offering insights into the lives and values of the people who create and consume it.<br><br>
					At its core, food culture reflects geography and history. For example, Japanese cuisine, renowned for its simplicity and aesthetic presentation, mirrors Japan's island geography, abundant seafood resources, and Buddhist influences promoting minimalism. Similarly, the spice-rich, flavorful dishes of Indian cuisine tell tales of ancient trade routes, diverse regional climates, and vibrant cultural interactions.<br><br>
					Food rituals and etiquette also form crucial components of food culture. French gastronomy emphasizes meticulous meal courses, precise etiquette, and the social aspect of dining, while Ethiopian dining culture involves communal eating, where dishes are shared from a single plate, symbolizing unity and togetherness.<br><br>
					Food culture is not static; it continuously evolves, influenced by globalization, migration, and innovation. The global popularity of dishes like sushi, tacos, or pasta demonstrates how culinary practices transcend their geographic boundaries, becoming beloved staples worldwide. This cultural exchange enriches culinary diversity, promoting appreciation and understanding among different societies.<br><br>
					Moreover, food culture significantly impacts sustainability. Traditional culinary practices often embody sustainable principles—using local, seasonal ingredients and minimizing waste. Reviving and preserving these practices can play a vital role in global sustainability efforts.<br><br>
					Ultimately, understanding food culture allows us to connect more deeply with one another. It fosters empathy, preserves heritage, and encourages respect for diversity. Whether it's through sharing meals, exploring new cuisines, or reviving traditional recipes, engaging with food culture enriches our lives and strengthens our connections to our past, our environment, and each other.<br><br>
            @endif</div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const contentScan = document.getElementById('content_scan');
                let typingTimer; // Timer identifier
                const doneTypingInterval = 1000; // Time in ms (2 seconds)

                const checkPlaceholder = () => {
                    if (contentScan.innerHTML.trim() === '') {
                        contentScan.classList.add('contenteditable-placeholder');
                    } else {
                        contentScan.classList.remove('contenteditable-placeholder');
                    }
                };

                const doneTyping = () => {
                    let suggested_keywords = document.getElementById('suggested_keywords');
                    suggested_keywords.innerHTML = '';
                    for (let i = 0; i < 5; i++) {
                        let placeholder = document.createElement('div');
                        placeholder.classList.add('placeholder');
                        suggested_keywords.appendChild(placeholder);
                    }

                    checkPlaceholder(); // Ensure the placeholder check is performed
                    handleContentSubmit(); // Call your function here
                };

                contentScan.addEventListener('input', () => {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(doneTyping, doneTypingInterval);
                });

                // Initial check to set placeholder if div is empty
                checkPlaceholder();
            });
			function handleContentSubmit() {
				// get only the text, not HTML
				let content = document.getElementById('content_scan').innerText.trim();
				let keyword = "";

				if (content === '') {
					return;
				}

				// search for the first heading tag inside the element (if you still want it)
				let headingElement = document.querySelector('#content_scan h1, #content_scan h2, #content_scan h3, #content_scan h4, #content_scan h5, #content_scan h6');
				if (headingElement) {
					keyword = headingElement.innerText; // use text only
				} else {
					keyword = content.substring(0, 100);
				}

				$.ajax({
					url: '{{ route('dashboard.user.seo.suggestKeywords') }}',
					type: 'POST',
					data: {
						keyword: keyword,
					},
					success: function(response) {
						var placeholders = document.getElementsByClassName('placeholder');
						for (var i = 0; i < placeholders.length; i++) {
							placeholders[i].classList.add('hidden');
						}

						let result = response.result;
						let suggested_keywords = document.getElementById('suggested_keywords');
						suggested_keywords.innerHTML = '';
						if (result.length > 0) {
							document.getElementById('suggText').classList.remove('hidden');
							for (let i = 0; i < result.length; i++) {
								let keyword = result[i];
								let keywordDiv = document.createElement('div');
								keywordDiv.innerText = keyword; // text only, no HTML injection
								keywordDiv.classList.add('keyword', 'bg-secondary', 'p-1', 'rounded-xl', 'cursor-pointer');
								keywordDiv.style.fontSize = '0.8rem';

								keywordDiv.onclick = function() {
									document.getElementById('keyword').value = keyword;
								};
								suggested_keywords.appendChild(keywordDiv);
							}
						}
					},
					error: function(error) {
						alert('Failed to submit content');
					},
				});
			}
		</script>
        <div
            class="tinymce hidden h-[600px] overflow-y-scroll rounded-xl border"
            id="content_result"
            name="content_result"
        ></div>
    </div>

</div>
