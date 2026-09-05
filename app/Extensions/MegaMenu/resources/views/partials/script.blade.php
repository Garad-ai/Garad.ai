<script src="{{ custom_theme_url('/assets/libs/jquery-ui/jquery-ui.min.js') }}"></script>
<script src="{{ custom_theme_url('/assets/libs/nested-sortable/jquery.mjs.nestedSortable.js') }}"></script>
<script src="{{ custom_theme_url('/assets/libs/universal-icon-picker-main/assets/js/universal-icon-picker.min.js') }}"></script>
<script src="{{ custom_theme_url('/assets/js/panel/settings.js') }}"></script>

<script>
    $(document).ready(function() {
        const $menuList = $('.lqd-menu-list');

        $('[type="file"]').change(function() {

            let input = $(this);

            let route = input.data('link');
            let div = input.data('div');

            let file = input[0].files[0];

            let formData = new FormData();

            formData.append('file', file);

            $.ajax({
                type: 'POST',
                url: route,
                data: formData,
                processData: false,
                contentType: false,
                dataType: "json",
                success: function(resultData) {
                    toastr.success(resultData.message);
                    if (div) {
                        $(`#${div}`).html(`<img src="${resultData.url}" alt="Image" class="max-w-8 mx-auto">`);
                    }
                },
                error: function(resultData) {
                    toastr.error('{{ trans('File upload error') }}');
                }
            });
        })

        $('.menu-item-add-form').on('submit', function(event) {
            event.preventDefault();

            const form = event.currentTarget;
            const label = form.elements.label.value;
            const type = form.elements.type.value;
            const link = form.elements.link.value;
            const space = form.elements.space.value;
            const description = form.elements.description.value;

            if (type === 'v-space') {
                if (space === '') {
                    toastr.error('Space is required');
                    return;
                }
            }

            if (type !== 'divider' && type !== 'v-space') {
                if (label === '') {
                    toastr.error('Label is required');
                    return;
                }

                if (link === '' && type === 'item') {
                    toastr.error('Link is required');
                    return;
                }
            }

            $.ajax({
                type: 'POST',
                url: '{{ route('dashboard.admin.mega-menu.items.store', $megaMenu->id) }}',
                data: {
                    _token: "{{ csrf_token() }}",
                    type,
                    label: type === 'divider' ? 'divider' : (type === 'v-space' ? 'Empty Space' : label),
                    link: type === 'item' ? link : null,
                    description: type === 'item' || type === 'label' ? description : null,
                    space: space
                },
                dataType: "json",
                success: function(resultData) {
                    if (resultData.status == 'success') {
                        toastr.success(resultData.message);
                        setTimeout(() => {
                            location.reload()
                        }, 1500);
                    } else {
                        toastr.error(resultData.message);
                    }
                }
            });
        })


        $('[data-status="menu"]').on('change', function() {

            let route = $(this).data('href');

            let colorDiv = $(this).data('color-div');

            if (colorDiv) {
                $(`#${colorDiv}`).toggleClass('hidden');
            }

            $.ajax({
                type: 'POST',
                url: route,
                data: {
                    _token: "{{ csrf_token() }}"
                },
                dataType: "json",
                success: function(resultData) {
                    toastr.success(resultData.message);
                }
            });
        });

        $('[data-item="input"]').on('change', function() {
            let input = $(this);
            let value = input.val();
            if (value == '' && input.attr('name') !== 'icon') {
                return;
            }

            input.closest('li').find('.lqd-menu-item-c').text(value?.trim()?.split('')?.at(0) || '')

            let route = input.data('link');

            $.ajax({
                type: 'POST',
                url: route,
                data: {
                    value: value,
                    _token: "{{ csrf_token() }}"
                },
                dataType: "json",
                success: function(resultData) {
                    toastr.success(resultData.message);
                }
            });
        });

        $menuList.nestedSortable({
            handle: ".lqd-menu-item-handle",
            items: 'li',
            toleranceElement: '> div',
            maxLevels: 2,
            placeholder: 'lqd-menu-item-placeholder',
            forcePlaceholderSize: true,
            isAllowed: function(placeholder, placeholderParent, currentItem) {
                if (currentItem.hasClass('no-parent')) {
                    // Eğer placeholder'ın altına başka bir öğe ekleniyorsa izin verme
                    if (placeholderParent && placeholderParent.closest('li').length > 0) {
                        return false; // Üst öğe olmasını engelle
                    }
                }
                return true; // Diğer durumlarda izin ver
            },
            update: function() {
                let menu_serialized = $menuList.nestedSortable("serialize");
                $.ajax({
                    type: 'POST',
                    url: '{{ route('dashboard.admin.mega-menu.items.order', $megaMenu['id']) }}',
                    data: $menuList.nestedSortable("serialize"),
                    dataType: "text",
                    success: function(resultData) {
                        toastr.success(resultData.message && resultData.message.length ? resultData.message : '{{ __('Updated successfully') }}');
                    }
                });
            },
            activate: function(event, ui) {

            }
        });
    });
</script>
