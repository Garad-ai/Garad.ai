{{--
    Open With AI Video Editor button.
    Renders nothing when the Video Editor extension is not registered.

    Usage:
        @include('video-editor::partials.open-with-button', [
            'url'          => 'currentVideo?.video_url',  // Alpine JS expression for the video URL
            'title'        => 'currentVideo?.prompt',     // Alpine JS expression for an optional title
            'width'        => 'currentVideo?.width',      // Alpine JS expression (optional)
            'height'       => 'currentVideo?.height',     // Alpine JS expression (optional)
            'wrapperClass' => 'mt-4',                     // optional wrapper classes
            'buttonClass'  => 'w-full',                   // optional button classes
            'buttonSize'   => 'lg',                       // optional x-button size
        ])
--}}
@if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('video-editor'))
    @php
        $veUrlExpr = $url ?? 'null';
        $veTitleExpr = $title ?? "''";
        $veWidthExpr = $width ?? 'null';
        $veHeightExpr = $height ?? 'null';
        $veWrapperClass = $wrapperClass ?? 'mt-4';
        $veButtonClass = $buttonClass ?? 'w-full';
        $veButtonSize = $buttonSize ?? 'lg';
        $veEndpoint = route('dashboard.user.video-editor.projects.fromUrl');
        $veErrorMsg = __('Could not open with AI Video Editor. Please try again.');
        $veLabel = __('Open with AI Video Editor');
        $veLoadingLabel = __('Opening editor…');
    @endphp

    <div
        class="{{ $veWrapperClass }}"
        data-ve-endpoint="{{ $veEndpoint }}"
        data-ve-error="{{ $veErrorMsg }}"
        x-data="{
            veLoading: false,
            veOpenWithEditor() {
                if (this.veLoading) return;
                let sourceUrl = {!! $veUrlExpr !!};
                const errorMsg = this.$root.dataset.veError;
                if (!sourceUrl) {
                    (window.toastr && window.toastr.error) ? window.toastr.error(errorMsg) : alert(errorMsg);
                    return;
                }
                try { sourceUrl = new URL(sourceUrl, window.location.origin).href; } catch (_) {}
                this.veLoading = true;
                const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
                fetch(this.$root.dataset.veEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        url: sourceUrl,
                        title: ({!! $veTitleExpr !!}) || '',
                        width: ({!! $veWidthExpr !!}) || null,
                        height: ({!! $veHeightExpr !!}) || null,
                    }),
                })
                .then(r => r.ok ? r.json() : Promise.reject(r))
                .then(d => {
                    if (d && d.editor_url) { window.location.href = d.editor_url; return; }
                    throw new Error('bad_response');
                })
                .catch(() => {
                    this.veLoading = false;
                    (window.toastr && window.toastr.error) ? window.toastr.error(errorMsg) : alert(errorMsg);
                });
            },
        }"
    >
        <x-button
            class="{{ $veButtonClass }} inline-flex items-center justify-center gap-2"
            size="{{ $veButtonSize }}"
            variant="primary"
            hover-variant="primary"
            type="button"
            ::disabled="veLoading"
            @click="veOpenWithEditor()"
        >
            <x-tabler-edit class="size-4" />
            <span x-show="!veLoading">{{ $veLabel }}</span>
            <span
                x-show="veLoading"
                x-cloak
            >{{ $veLoadingLabel }}</span>
        </x-button>
    </div>
@endif
