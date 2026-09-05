<div class="hidden shrink-0 justify-evenly gap-3 border-b px-4 py-4 lg:flex">
    <x-button
        class="active gap-2.5 p-2 text-base font-semibold opacity-50 [&.active]:opacity-100"
        variant="link"
        size="none"
        ::class="{ 'active': contactInfo.activeTab === 'details' }"
        @click.prevent="contactInfo.activeTab = 'details'"
    >
        <svg
            width="20"
            height="21"
            viewBox="0 0 20 21"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M4 18.55V18.5C4 17.4391 4.42143 16.4217 5.17157 15.6716C5.92172 14.9214 6.93913 14.5 8 14.5H12C13.0609 14.5 14.0783 14.9214 14.8284 15.6716C15.5786 16.4217 16 17.4391 16 18.5V18.55M10 11.5C10.7956 11.5 11.5587 11.1839 12.1213 10.6213C12.6839 10.0587 13 9.29565 13 8.5C13 7.70435 12.6839 6.94129 12.1213 6.37868C11.5587 5.81607 10.7956 5.5 10 5.5C9.20435 5.5 8.44129 5.81607 7.87868 6.37868C7.31607 6.94129 7 7.70435 7 8.5C7 9.29565 7.31607 10.0587 7.87868 10.6213C8.44129 11.1839 9.20435 11.5 10 11.5ZM10 1.5C17.2 1.5 19 3.3 19 10.5C19 17.7 17.2 19.5 10 19.5C2.8 19.5 1 17.7 1 10.5C1 3.3 2.8 1.5 10 1.5Z"
            />
        </svg>
        {{ __('Details') }}
    </x-button>
</div>
