@extends('panel.layout.app', ['disable_tblr' => true, 'disable_titlebar' => true])
@section('title', __('Create New Post'))

@section('content')
    <input
        id="image"
        type="hidden"
        nae="image"
        value="{{ old('image') }}"
    >

    <input
        id="upload_image"
        type="file"
        name="upload_image"
    >
@endsection

@push('scripts')
    <script>
        $('#upload_image').on('change', function() {
            uploadImage();
        });

        function uploadImage() {
            const fileInput = document.getElementById('upload_image');
            const file = fileInput.files[0];
            if (!file) return;
			let formData = new FormData();
            formData.append('upload_image', file);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('{{ route('dashboard.user.social-media.upload.image') }}', {
                    method: 'POST',
                    body: formData,
                    // headers: {
                    //   'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    // }
                })
                .then(response => response.json())
                .then(data => {
                    if (data && data.image_path) {
                        document.getElementById('image').value = data.image_path;
                    } else {
						console.error('Image upload failed or no image path returned.');
                    }
                })
                .catch(error => {
                    console.error('Error uploading image:', error);
                });
        }
    </script>

    <script>
        function uploadImage() {

        }

        function generateImage(prompt = 'Image prompt #Mohsen') {
            $.ajax({
                url: "/dashboard/user/openai/generate",
                type: 'POST',
                data: {
                    "post_type": "ai_image_generator",
                    "openai_id": "36",
                    "custom_template": "0",
                    "image_generator": "openai",
                    "image_style": "",
                    "image_lighting": "",
                    "image_mood": "",
                    "image_number_of_images": "1",
                    "size": "1024x1024",
                    "quality": "standard",
                    "description": prompt
                },
                beforeSend: function() {},
                success: function(response) {
                    if (response.status === 'success') {
                        let images = response.images;

                        if (images[0]) {
                            let image = images[0];

                            let output = image.output;

                            preview.src = output;

                            preview.classList.remove("hidden");

                            placeholder.classList.add("hidden");

                            imageInput.value = response.nameOfImage;

                            $('#lds-dual-ring1').toggleClass('hidden');
                            $('.generate').toggleClass('hidden');

                        }
                    } else {
                        toastr.error(response.message);

                    }
                },
                error: function(error) {
                    // console.log(error);
                    toastr.error(error.responseJSON.message);

                }
            });
        }
    </script>
@endpush
