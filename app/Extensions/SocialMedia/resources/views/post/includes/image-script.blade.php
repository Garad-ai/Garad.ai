@php
	$socialMediaImageModel = setting('social_media_image_model', 'nano-banana-pro');
@endphp

const formData = new FormData();

formData.append("post_type", "ai_image_generator");
formData.append("openai_id", "36");
formData.append("custom_template", "0");
formData.append("image_generator", "{{ $socialMediaImageModel }}");
formData.append("model", "{{ $socialMediaImageModel }}");
formData.append("fal_ai_model", "{{ $socialMediaImageModel }}");
formData.append("image_number_of_images", "1");
formData.append("image_style", "");
formData.append("image_lighting", "");
formData.append("image_mood", "");
formData.append("size", "1024x1024");
formData.append("quality", "standard");
formData.append("image_resolution", "1x1");
formData.append("type", "text-to-image");
formData.append("negative_prompt", "");
formData.append("style_preset", "");
formData.append("sampler", "");
formData.append("clip_guidance_preset", "");
formData.append("image_ratio", "");
formData.append("description", "");
formData.append("template_description", "");
formData.append("prompt_description", "");

formData.append('description_ideogram', prompt);
formData.append('description_flux_pro', prompt);
formData.append('description_midjourney', prompt);
formData.append('description', prompt);
formData.append('stable_description', prompt);