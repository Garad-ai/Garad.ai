<?php

declare(strict_types=1);

namespace App\Extensions\VideoEditor\System\Http\Requests;

use App\Extensions\AiVideoPro\System\Services\ModelConfigurationService;
use Illuminate\Foundation\Http\FormRequest;

class GenerateAiVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'model'      => ['required', 'string'],
            'action'     => ['nullable', 'string'],
            'feature'    => ['nullable', 'string'],
            'project_id' => ['nullable', 'integer', 'exists:video_editor_projects,id'],
        ];

        // Dynamically add rules from model config inputs
        $feature = $this->input('feature') ?? $this->input('model');
        $action = $this->input('action');

        $featureHasPrompt = false;

        if ($action && $feature) {
            $config = ModelConfigurationService::getConfig();

            $featureConfig = $this->findFeatureConfig($config, $action, $feature);

            if ($featureConfig && isset($featureConfig['inputs'])) {
                foreach ($featureConfig['inputs'] as $input) {
                    $name = $input['name'] ?? null;
                    if (! $name) {
                        continue;
                    }

                    if (($input['type'] ?? '') === 'file') {
                        $fileRules = [$input['required'] ?? false ? 'required' : 'nullable'];
                        if (! empty($input['multiple'])) {
                            $rules[$name] = ['nullable', 'array'];
                            $rules[$name . '.*'] = ['file', 'max:20480'];
                        } else {
                            $fileRules[] = 'file';
                            $fileRules[] = 'max:20480';
                            $rules[$name] = $fileRules;
                        }

                        continue;
                    }

                    $fieldRules = ['nullable'];

                    switch ($input['type'] ?? 'text') {
                        case 'textarea':
                        case 'text':
                            $fieldRules[] = 'string';
                            if (isset($input['max'])) {
                                $fieldRules[] = 'max:' . $input['max'];
                            } else {
                                $fieldRules[] = 'max:2000';
                            }

                            break;

                        case 'number':
                        case 'range':
                            $fieldRules[] = 'numeric';
                            if (isset($input['min'])) {
                                $fieldRules[] = 'min:' . $input['min'];
                            }
                            if (isset($input['max'])) {
                                $fieldRules[] = 'max:' . $input['max'];
                            }

                            break;

                        case 'select':
                            if (! empty($input['options'])) {
                                $validValues = array_map(
                                    fn ($opt) => $opt['value'] ?? '',
                                    $input['options']
                                );
                                $fieldRules[] = 'in:' . implode(',', $validValues);
                            }

                            break;

                        case 'checkbox':
                            $fieldRules = ['nullable', 'boolean'];

                            break;
                    }

                    // Make prompt required when feature actually exposes it
                    if ($name === 'prompt') {
                        $featureHasPrompt = true;
                        $fieldRules = array_filter($fieldRules, fn ($r) => $r !== 'nullable');
                        array_unshift($fieldRules, 'required');
                    }

                    $rules[$name] = $fieldRules;
                }
            }
        }

        // Only enforce prompt when the selected feature actually exposes it.
        // Features like background-removal don't take a prompt at all.
        if (! $featureHasPrompt && ! isset($rules['prompt'])) {
            $rules['prompt'] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        // Convert checkbox string values to booleans
        $booleanFields = ['generate_audio', 'enhance_prompt', 'auto_fix', 'loop'];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $this->merge([
                    $field => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
    }

    private function findFeatureConfig(array $config, string $action, string $feature): ?array
    {
        if (! isset($config[$action])) {
            return null;
        }

        $group = $config[$action];

        foreach ($group['subModels'] ?? [] as $subModel) {
            if (isset($subModel['features'][$feature])) {
                return $subModel['features'][$feature];
            }
        }

        return null;
    }
}
