<?php

namespace App\Extensions\AiVideoPro\System\Http\Requests;

use App\Extensions\AiVideoPro\System\Services\ModelConfigurationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreAiVideoProRequest extends FormRequest
{
    protected array $models;

    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        // Convert checkbox values to boolean before validation
        $this->convertCheckboxesToBoolean();
    }

    public function rules(): array
    {
        $this->models = ModelConfigurationService::getConfig();

        $action = $this->input('action');
        $subModel = $this->input('sub_model_value');
        $feature = $this->input('feature');

        $rules = [
            'action'          => 'required|string',
            'sub_model_value' => 'required|string',
            'feature'         => 'required|string',
        ];

        // Get feature config
        $featureConfig = $this->getFeatureConfig($action, $subModel, $feature);

        if ($featureConfig && isset($featureConfig['inputs'])) {
            $rules = array_merge($rules, $this->buildDynamicRules($featureConfig['inputs']));
        }

        // Advanced JSON passthrough fields for models that support structured inputs.
        $rules = array_merge($rules, [
            'multi_prompt_json' => 'nullable|json',
            'voice_ids_json'    => 'nullable|json',
            'elements_json'     => 'nullable|json',
        ]);

        return $rules;
    }

    public function validationData()
    {
        $data = parent::validationData();

        // Flatten nested file arrays
        foreach ($this->allFiles() as $key => $files) {
            if (is_array($files)) {
                $flatFiles = $this->flattenFileArray($files);
                if (! empty($flatFiles)) {
                    $data[$key] = $flatFiles;

                    // CRITICAL FIX: If the field name has [], also set it without []
                    // This handles configs that use "image_urls[]" as the field name
                    if (str_ends_with($key, '[]')) {
                        $baseKey = rtrim($key, '[]');
                        $data[$baseKey] = $flatFiles;
                    } else {
                        // Also set with [] suffix if it doesn't have one
                        $data[$key . '[]'] = $flatFiles;
                    }
                }
            }
        }

        return $data;
    }

    protected function flattenFileArray($array): array
    {
        $result = [];

        array_walk_recursive($array, static function ($item) use (&$result) {
            if ($item instanceof UploadedFile) {
                $result[] = $item;
            }
        });

        return $result;
    }

    protected function convertCheckboxesToBoolean(): void
    {
        $checkboxFields = [
            'generate_audio',
            'enhance_prompt',
            'auto_fix',
            'loop',
            'keep_original_sound',
        ];

        foreach ($checkboxFields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);

                // Convert 'on', '1', 'true' to boolean true
                // Convert 'off', '0', 'false', null to boolean false
                $this->merge([
                    $field => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                ]);
            } else {
                // If checkbox is not present, it means it's unchecked
                $this->merge([
                    $field => false,
                ]);
            }
        }
    }

    protected function getFeatureConfig($action, $subModel, $feature): ?array
    {
        return $this->models[$action]['subModels'][$subModel]['features'][$feature] ?? null;
    }

    protected function buildDynamicRules(array $inputs): array
    {
        $rules = [];

        foreach ($inputs as $input) {
            $inputRules = [];
            $name = $input['name'];

            // Required or nullable
            $inputRules[] = ($input['required'] ?? false) ? 'required' : 'nullable';

            // Type-specific rules
            switch ($input['type']) {
                case 'text':
                case 'textarea':
                    $inputRules[] = 'string';
                    if (isset($input['max'])) {
                        $inputRules[] = "max:{$input['max']}";
                    }

                    break;

                case 'number':
                case 'range':
                    $inputRules[] = 'numeric';
                    if (isset($input['min'])) {
                        $inputRules[] = "min:{$input['min']}";
                    }
                    if (isset($input['max'])) {
                        $inputRules[] = "max:{$input['max']}";
                    }

                    break;

                case 'select':
                    $validOptions = array_column($input['options'] ?? [], 'value');
                    if (! empty($validOptions)) {
                        $inputRules[] = 'in:' . implode(',', $validOptions);
                    }

                    break;

                case 'checkbox':
                    $inputRules[] = 'boolean';

                    break;

                case 'file':
                    if ($input['multiple'] ?? false) {
                        $inputRules[] = 'array';
                        $minFiles = $input['min_files'] ?? 1;
                        $maxFiles = $input['max_files'] ?? 3;
                        $inputRules[] = "min:{$minFiles}";
                        $inputRules[] = "max:{$maxFiles}";

                        // Strip [] from name for the .* rule
                        $baseName = rtrim($name, '[]');
                        $rules["{$baseName}.*"] = $this->getFileRules($input);
                    } else {
                        array_push($inputRules, ...$this->getFileRules($input));
                    }

                    break;
            }

            if (! empty($inputRules)) {
                $rules[$name] = $inputRules;
            }
        }

        return $rules;
    }

    protected function getFileRules(array $input): array
    {
        $rules = ['file'];
        $accept = $input['accept'] ?? '';

        if (str_contains($accept, 'image')) {
            $rules[] = 'image';
            $rules[] = 'mimes:jpeg,jpg,png,webp';
            $rules[] = 'max:10240'; // 10MB
        } elseif (str_contains($accept, 'video')) {
            $rules[] = 'mimes:mp4,mov,avi,wmv,flv,webm';
            $rules[] = 'max:51200'; // 50MB
        } elseif (str_contains($accept, 'audio')) {
            $rules[] = 'mimes:mp3,wav,ogg,flac,aac,m4a';
            $rules[] = 'max:15360'; // 15MB
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'action.required'            => 'Please select an AI model.',
            'sub_model_value.required'   => 'Please select a model version.',
            'feature.required'           => 'Please select a feature.',
            'prompt.required'            => 'The prompt field is required.',
            'image_urls.required'        => 'Please upload at least one image.',
            'image_urls[].required'      => 'Please upload at least one image.',
            'image_urls.min'             => 'Please upload at least one image.',
            'image_urls[].min'           => 'Please upload at least one image.',
            'image_urls.*.image'         => 'All uploaded files must be valid images.',
            'image_urls[].*.image'       => 'All uploaded files must be valid images.',
            'image_urls.*.mimes'         => 'Images must be in jpeg, jpg, png, or webp format.',
            'image_urls[].*.mimes'       => 'Images must be in jpeg, jpg, png, or webp format.',
            'image_urls.*.max'           => 'Each image must not exceed 10MB.',
            'image_urls[].*.max'         => 'Each image must not exceed 10MB.',
            'video_urls.*.mimes'         => 'Videos must be in mp4, mov, avi, wmv, flv, or webm format.',
            'video_urls[].*.mimes'       => 'Videos must be in mp4, mov, avi, wmv, flv, or webm format.',
            'video_urls.*.max'           => 'Each video must not exceed 50MB.',
            'video_urls[].*.max'         => 'Each video must not exceed 50MB.',
            'audio_urls.*.mimes'         => 'Audio files must be in mp3, wav, ogg, flac, aac, or m4a format.',
            'audio_urls[].*.mimes'       => 'Audio files must be in mp3, wav, ogg, flac, aac, or m4a format.',
            'audio_urls.*.max'           => 'Each audio file must not exceed 15MB.',
            'audio_urls[].*.max'         => 'Each audio file must not exceed 15MB.',
            '*.required'                 => 'This field is required.',
            '*.boolean'                  => 'This field must be true or false.',
            '*.image'                    => 'The file must be an image.',
            '*.mimes'                    => 'Invalid file type.',
            '*.max'                      => 'The file is too large.',
        ];
    }

    /**
     * Get validated data with proper boolean conversion
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Ensure boolean fields are actually boolean type
        if (is_array($validated)) {
            $booleanFields = ['generate_audio', 'enhance_prompt', 'auto_fix', 'loop', 'keep_original_sound'];
            foreach ($booleanFields as $field) {
                if (isset($validated[$field])) {
                    $validated[$field] = (bool) $validated[$field];
                }
            }
        }

        return $validated;
    }
}
