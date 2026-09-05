@php
    use App\Extensions\BlogPilot\System\Models\BlogPilotPost;
@endphp
@extends('panel.layout.settings', ['layout' => 'fullwidth', 'disable_tblr' => true])
@section('title', __('Edit Post'))
@section('titlebar_pretitle')
    <x-button
        class="text-inherit hover:text-foreground"
        variant="link"
        href="{{ route('dashboard.user.blogpilot.agent.posts') }}"
    >
        <x-tabler-chevron-left class="size-4" stroke-width="1.5" />
        {{ __('Back to posts') }}
    </x-button>
@endsection
@section('titlebar_actions')
    <div class="flex gap-4 lg:justify-end">
        {{-- Share --}}
         <x-dropdown.dropdown
            class="doc-share-dropdown"
            class:dropdown-dropdown="max-lg:end-auto max-lg:start-0"
            anchor="end"
            offsetY="20px"
        >
            <x-slot:trigger>
                {{ __('Share') }}
                <span
                    class="inline-grid size-6 shrink-0 place-items-center rounded-md bg-foreground/10 transition-all group-hover/dropdown:scale-105 group-hover/dropdown:bg-heading-foreground group-hover/dropdown:text-heading-background"
                >
                    <x-tabler-share class="size-4" />
                </span>
            </x-slot:trigger>
            <x-slot:dropdown
                class="py-1 text-2xs"
            >
                <x-button
                    class="w-full justify-start rounded-none px-3 py-2 text-start hover:bg-heading-foreground/5"
                    variant="link"
                    target="_blank"
                    href="http://twitter.com/share?text={{ $post->content }}"
                >
                    <x-tabler-brand-x />
                    @lang('X')
                </x-button>
                <x-button
                    class="w-full justify-start rounded-none px-3 py-2 text-start hover:bg-heading-foreground/5"
                    variant="link"
                    target="_blank"
                    href="https://wa.me/?text={{ htmlspecialchars($post->content) }}"
                >
                    <x-tabler-brand-whatsapp />
                    @lang('Whatsapp')
                </x-button>
                <x-button
                    class="w-full justify-start rounded-none px-3 py-2 text-start hover:bg-heading-foreground/5"
                    variant="link"
                    target="_blank"
                    href="https://t.me/share/url?url={{ request()->host() }}&text={{ htmlspecialchars($post->content) }}"
                >
                    <x-tabler-brand-telegram />
                    @lang('Telegram')
                </x-button>
            </x-slot:dropdown>
        </x-dropdown.dropdown>
        {{-- Publish --}}
        @if (!empty($integrations) && $checkIntegration && $wordpressExist)
            <x-dropdown.dropdown
                class="doc-integrate-publish-dropdown"
                class:dropdown-dropdown="max-lg:end-auto max-lg:start-0"
                anchor="end"
                offsetY="20px"
            >
                <x-slot:trigger
                    variant="success"
                    id='publish_button'
                >
                    {{ __('Publish') }}
                </x-slot:trigger>
                <x-slot:dropdown
                    class="min-w-48 text-xs"
                >
                    @if ( $post->published_at )
                        <p class="px-3 py-3 text-foreground/70">
                            @lang('Published at:') {{ $post->published_at }}
                        </p>
                    @else
                        <p class="border-b px-3 py-3 text-foreground/70">
                            @lang('Integrations')
                        </p>
                        <div class="pb-2">
                            @foreach ($integrations as $integration)
                                <x-button
                                    class="w-full justify-start rounded-none px-3 py-2 text-start hover:bg-heading-foreground/5"
                                    variant="link"
                                    onclick="blogPilotPublish({{$post->id}},{{$integration->id}})"
                                >
                                    {{ $integration?->integration?->app }}
                                </x-button>
                            @endforeach
                        </div>
                    @endif
                </x-slot:dropdown>
            </x-dropdown.dropdown>
        @endif
        {{-- Save --}}
        <x-button
            id="post_button"
            type="submit"
            form="post_form"
        >
            {{ __('Save') }}
        </x-button>
    </div>
@endsection

@section('settings')
    <form
        class="[&_.tox]:bg-input-background"
        id="post_form"
        onsubmit="return blogPilotPostSave({{ $post != null ? $post->id : null }});"
        action=""
        enctype="multipart/form-data"
    >
        <div class="flex flex-wrap justify-between">
            <div class="flex w-full flex-col gap-5 lg:w-7/12">
                <x-forms.input
                    class="blog-post-title"
                    id="title"
                    label="{{ __('Post Title') }}"
                    name="title"
                    size="lg"
                    tooltip="{{ __('Add a post title.') }}"
                    value="{{ $post != null ? $post->title : null }}"
                />
                <x-forms.input
                    id="content"
                    label="{{ __('Content') }}"
                    name="content"
                    type="textarea"
                    size="lg"
                    tooltip="{{ __('A short description of what this chat template can help with.') }}"
                >{{ $post != null ? $post->content : null }}</x-forms.input>
            </div>

            <div class="ms-auto flex w-full flex-col gap-5 lg:w-4/12">
                <div class="flex flex-col gap-2">
                    <img
                        @class([
                            'preview mb-5 rounded-lg border',
                            'hidden' => $post == null || ($post != null && !$post->thumbnail),
                        ])
                        alt="{{ $post != null ? $post->title : __('preview') }}"
                        src="{{ custom_theme_url($post != null ? $post->thumbnail : null, true) }}"
                    >
                    <x-forms.input
                        id="thumbnail"
                        type="file"
                        size="lg"
                        name="thumbnail"
                        value="/{{ $post != null ? $post->thumbnail : null }}"
                        accept="image/*"
                        label="{{ __('Post Image') }}"
                    />
                </div>

                <x-forms.input
                    id="status"
                    tooltip="{{ __('Set the post status.') }}"
                    label="{{ __('Post Status') }}"
                    name="status"
                    type="select"
                    size="lg"
                >
                    @foreach( BlogPilotPost::getStatusArray() as $status => $statusStr )
                        <option
                            value="{{$status}}"
                            @selected($post->status == $status)
                        >
                            {{ $statusStr }}
                        </option>
                    @endforeach
                </x-forms.input>

                <x-forms.input
                    id="scheduled_at"
                    label="{{ __('Date') }}"
                    tooltip="{{ __('Set the post date.') }}"
                    size="lg"
                    name="scheduled_at"
                    type="datetime-local"
                    value="{{$post->scheduled_at}}"
                    step="1"
                    placeholder="{{ __('Date') }}"
                />

                <x-forms.input
                    id="categories"
                    type="select"
                    name="categories"
                    multiple
                    size="none"
                    label="{{ __('Category') }}"
                    tooltip="{{ __('Categories of the post. Useful for filtering in the blog posts.') }}"
                    add-new
                >
                    @if ($post->categories)
                        @foreach ($post->categories as $category)
                            <option
                                value="{{ $category }}"
                                selected
                            >
                                {{ $category }}
                            </option>
                        @endforeach
                    @endif
                </x-forms.input>

                <x-forms.input
                    id="tags"
                    type="select"
                    name="tags"
                    multiple
                    size="none"
                    label="{{ __('Tag') }}"
                    tooltip="{{ __('Tags of the post. Useful for filtering in the blog posts.') }}"
                    add-new
                >
                    @if ($post->tags)
                        @foreach ($post->tags as $tag)
                            <option
                                value="{{ $tag }}"
                                selected
                            >{{ $tag }}</option>
                        @endforeach
                    @endif
                </x-forms.input>
            </div>
        </div>
    </form>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/js/panel/blog.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/js/panel/tinymce-theme-handler.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/tinymce/tinymce.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            const tinymceOptions = {
                selector: '#content',
                height: '610',
                menubar: false,
				statusbar: false,
				relative_urls: false,
				convert_urls: false,
				remove_script_host: false,
				plugins: [
					'advlist', 'link', 'autolink', 'lists', 'supercode', 'code'
				],
				contextmenu: 'customwrite |  rewrite summarize makeitlonger makeitshorter improvewriting translateto simplify changestyle changetone fixgrammaticalmistakes | copy paste',
				toolbar: 'styles | magicIconRewrite | magicAIButton | image | link | forecolor backcolor emoticons | bold italic underline  | bullist numlist | alignleft aligncenter alignright | code supercode',
                content_css: `${window.liquid.assetsPath}/css/tinymce-theme.css`,
				directionality: document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr',
				forced_root_block: 'div',
                setup: function(editor) {
                    liquidTinyMCEThemeHandlerInit(editor);
                }
            };
            tinymce.init(tinymceOptions);
        });

        function blogPilotPostSave( post_id ) {
            "use strict";

            document.getElementById( "post_button" ).disabled = true;
            document.getElementById( "post_button" ).innerHTML = "Please Wait...";

            var formData = new FormData();

            if ( post_id != 'undefined' ) {
                formData.append( 'id', post_id );
            } else {
                formData.append( 'id', null );
            }
            formData.append( 'title', $( "#title" ).val() );
            formData.append( 'content', tinymce.activeEditor.getContent() );
            if ( $( '#thumbnail' ).val() != 'undefined' ) {
                formData.append( 'thumbnail', $( '#thumbnail' ).prop( 'files' )[ 0 ] );
            }
            formData.append( 'categories', $( "#categories" ).val() );
            formData.append( 'tags', $( "#tags" ).val() );
            formData.append( 'status', $( "#status" ).val() );
            formData.append( 'scheduled_at', $( "#scheduled_at" ).val() );

            $.ajax( {
                type: "POST",
                url: `/dashboard/user/blogpilot/agent/posts/${post_id}/update`,
                data: formData,
                contentType: false,
                processData: false,
                success: function ( data ) {
                    toastr.success('{{ __('Post Saved Successfully') }}' )
                    document.getElementById( "post_button" ).disabled = false;
                    document.getElementById( "post_button" ).innerHTML = "Save";
                },
                error: function ( data ) {
                    var err = data.responseJSON?.message;
					if (err) {
						toastr.error(err);
					} else {
						toastr.error('{{ __('An error occurred while saving the post.') }}');
					}
                    document.getElementById( "post_button" ).disabled = false;
                    document.getElementById( "post_button" ).innerHTML = "Save";
                }
            } );
            return false;
        }

        $(document).ready(function() {
            "use strict";
            var fileInput = $('#thumbnail');
            var previewImage = $('.preview');

            fileInput.on('change', function() {
            var file = this.files[0];
            var reader = new FileReader();

            if (file) {
                reader.readAsDataURL(file);
                reader.onload = function() {
                previewImage.attr('src', reader.result);
                previewImage.show();
                };
            } else {
                previewImage.hide();
            }
            });
        });

         function blogPilotPublish( post_id, integration_id ) {
            "use strict";

            document.getElementById( "publish_button" ).disabled = true;
            document.getElementById( "publish_button" ).innerHTML = '{{ __('Please wait...') }}';

            var formData = new FormData();

            if ( post_id != 'undefined' ) {
                formData.append( 'id', post_id );
            } else {
                toastr.error('{{ __('Post Not Found!') }}');
                return false;
            }

            $.ajax( {
                type: "POST",
                url: `/dashboard/user/blogpilot/agent/posts/${post_id}/publish`,
                data: formData,
                contentType: false,
                processData: false,
                success: function ( data ) {
                    toastr.success( data.message );
                    document.getElementById( "publish_button" ).disabled = false;
                    document.getElementById( "publish_button" ).innerHTML = '{{ __('Publish') }}';
                    $('#status').val( data.status ).change();
                },
                error: function ( data ) {
                   var err = data.responseJSON?.message;
					if (err) {
						toastr.error(err);
					} else {
						toastr.error('{{ __('An error occurred while publishing the post.') }}');
					}
                    document.getElementById( "publish_button" ).disabled = false;
                    document.getElementById( "publish_button" ).innerHTML = '{{ __('Publish') }}';
                }
            } );
            return false;
        }
    </script>
@endpush
