<div
    class="lqd-cs-gallery fixed inset-0 overflow-y-auto pt-[--header-h] transition-all"
    x-cloak
    x-show="currentView === 'gallery'"
    x-transition.opacity
>
    <div class="container">
        <div class="lqd-cs-recent-projects py-9">
            <div class="mb-6 flex items-center justify-between gap-3">
                <h2 class="mb-0">
                    @lang('All Projects')
                </h2>
            </div>

            <div class="lqd-cs-recent-projects-grid grid grid-cols-1 place-items-start gap-5 sm:grid-cols-2 md:grid-cols-3 md:gap-x-6 lg:grid-cols-5 lg:gap-x-11">
                @include('creative-suite::includes.documents-grid')
            </div>
        </div>
    </div>
</div>
