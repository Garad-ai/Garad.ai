<?php

declare(strict_types=1);

namespace App\Extensions\Migration\System\Utils;

use App\Domains\Entity\Enums\EntityEnum;
use App\Models\GatewayProducts;
use App\Models\Gateways;
use App\Models\Plan;
use App\Models\SettingTwo;
use App\Services\GatewaySelector;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription as Subscriptions;
use RuntimeException;
use Throwable;

class MigrationHelpers
{
    /**
     * @throws Throwable
     */
    public static function processMigration(?string $sqlFilePath, string $table, callable $processCallback, ?string $envFilePath = null): string
    {
        static $sqlContent = null;

        if ($sqlContent === null) {
            $sqlContent = file_get_contents($sqlFilePath);
        }

        // Extract table columns from schema
        $schemaColumns = SqlSchemaParser::extractTableColumns($sqlContent, $table);

        if (empty($schemaColumns)) {
            throw new RuntimeException("No columns found for {$table} in the SQL file.");
        }

        // Handle different INSERT statement formats
        $insertData = self::extractInsertStatements($sqlContent, $table);

        if (empty($insertData)) {
            Log::info("No records to migrate for table {$table}");

            return "No records found for {$table}";
        }

        $inserted = 0;

        try {
            foreach ($insertData as $data) {
                $columns = $data['columns'] ?? $schemaColumns;
                $values = $data['values'];

                if (count($columns) !== count($values)) {
                    Log::error("{$table} migration failed: column count mismatch", [
                        'expected' => count($columns),
                        'actual'   => count($values),
                        'columns'  => $columns,
                        'values'   => $values,
                    ]);

                    continue;
                }

                $record = array_combine($columns, $values);
                if (! $record) {
                    Log::error("{$table} migration failed: could not combine columns and values", [
                        'columns' => $columns,
                        'values'  => $values,
                    ]);

                    continue;
                }

                $env = null;
                if (! empty($envFilePath)) {
                    $env = self::convertEnvToArr($envFilePath);
                }

                if ($processCallback($record, $env)) {
                    $inserted++;
                }
            }
        } catch (Throwable $e) {
            Log::critical("Migration error on table {$table}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        return "{$table} migrated successfully: {$inserted} record(s)";
    }

    /**
     * Extract INSERT statements handling multiple formats
     */
    private static function extractInsertStatements(string $sqlContent, string $table): array
    {
        $insertData = [];

        // Pattern 1: INSERT INTO table (columns) VALUES (...)
        $patternWithColumns = "/INSERT\s+INTO\s+`{$table}`\s*\(([^)]+)\)\s+VALUES\s*(.*?);/is";

        // Pattern 2: INSERT INTO table VALUES (...)
        $patternWithoutColumns = "/INSERT\s+INTO\s+`{$table}`\s+VALUES\s*(.*?);/is";

        // First, try to match INSERT statements with column specifications
        if (preg_match_all($patternWithColumns, $sqlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $columnsPart = trim($match[1]);
                $valuesPart = trim($match[2]);

                // Parse columns
                $columns = self::parseColumns($columnsPart);

                // Parse values
                $valuesRows = self::parseValues($valuesPart);

                foreach ($valuesRows as $values) {
                    $insertData[] = [
                        'columns' => $columns,
                        'values'  => $values,
                    ];
                }
            }
        }

        // Then, try to match INSERT statements without column specifications
        if (preg_match_all($patternWithoutColumns, $sqlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $valuesPart = trim($match[1]);

                // Parse values
                $valuesRows = self::parseValues($valuesPart);

                foreach ($valuesRows as $values) {
                    $insertData[] = [
                        'columns' => null, // Will use schema columns
                        'values'  => $values,
                    ];
                }
            }
        }

        return $insertData;
    }

    /**
     * Parse column names from INSERT statement
     */
    private static function parseColumns(string $columnsPart): array
    {
        $columns = [];
        $columnsPart = trim($columnsPart);

        // Split by comma, but handle quoted column names
        $parts = preg_split('/,(?=(?:[^`]*`[^`]*`)*[^`]*$)/', $columnsPart);

        foreach ($parts as $part) {
            $column = trim($part, " \t\n\r\0\x0B`");
            if (! empty($column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Parse values from INSERT statement
     */
    private static function parseValues(string $valuesPart): array
    {
        $allValues = [];
        $valuesPart = trim($valuesPart);

        // Handle multiple value rows: (val1, val2), (val3, val4)
        if (preg_match_all('/\(([^)]*(?:\([^)]*\)[^)]*)*)\)/', $valuesPart, $matches)) {
            foreach ($matches[1] as $valuesRow) {
                $values = SqlValueParser::parseRow($valuesRow);
                $allValues[] = $values;
            }
        }

        return $allValues;
    }

    public static function normalizeModelName(string $value): string
    {
        return str_replace(['_', '-'], '', strtolower($value));
    }

    public static function processCredits(?array $credits, ?array $normalizedCredits): array
    {
        if (empty($credits)) {
            return [];
        }

        foreach ($credits as $engine => &$engineModels) {
            foreach ($engineModels as $modelName => &$modelData) {
                $normalizedModelName = MigrationHelpers::normalizeModelName($modelName);
                if (array_key_exists($normalizedModelName, $normalizedCredits)) {
                    $value = $normalizedCredits[$normalizedModelName];
                    $modelData['isUnlimited'] = ((int) $value) === -1;
                    $modelData['credit'] = max((int) $value, 0);
                }
            }
        }

        return $credits;
    }

    public static function createGatewayProducts(?array $record, ?Plan $plan): void
    {
        $record = $record ?? [];

        $gateways = [
            'stripe' => [
                'productId'     => $record['stripe_gateway_plan_id'] ?? null,
                'gateway_title' => 'Stripe',
            ],
            'paypal' => [
                'productId'     => $record['paypal_gateway_plan_id'] ?? null,
                'gateway_title' => 'PayPal',
            ],
            'paystack' => [
                'productId'     => $record['paystack_gateway_plan_id'] ?? null,
                'gateway_title' => 'Paystack',
            ],
            'razorpay' => [
                'productId'     => $record['razorpay_gateway_plan_id'] ?? null,
                'gateway_title' => 'Razorpay',
            ],
        ];

        foreach ($gateways as $key => $gateway) {
            if (! empty($gateway['productId'])) {
                $prod = new GatewayProducts;
                $prod->plan_id = $plan?->id;
                $prod->plan_name = $plan?->name;
                $prod->gateway_code = $key;
                $prod->gateway_title = $gateway['gateway_title'];
                $prod->product_id = $gateway['productId'];
                $prod->save();
            }
        }
    }

    public static function getPlansPriceIds(?Gateways $gateway): void
    {
        try {
            if ($gateway) {
                GatewaySelector::selectGateway($gateway->code)::getPlansPriceIdsForMigration();
            }
        } catch (Throwable $e) {
        }
    }

    public static function getUsersCustomerIds(?Gateways $gateway, ?Subscriptions $subscription): void
    {
        try {
            if ($gateway && $subscription) {
                GatewaySelector::selectGateway($gateway->code)::getUsersCustomerIdsForMigration($subscription);
            }
        } catch (Throwable $e) {
        }
    }

    public static function convertEnvToArr(string $envFilePath): array
    {
        $fileContent = file_get_contents($envFilePath);
        $lines = explode("\n", $fileContent);
        $env = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1); // Remove surrounding quotes
            }
            if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
                $value = substr($value, 1, -1); // Remove surrounding single quotes
            }
            $env[$key] = $value;
        }

        return $env;
    }

    public static function defaultWordModel(): EntityEnum
    {
        $defaultEngine = setting('default_ai_engine');

        return match ($defaultEngine) {
            'openai'    => EntityEnum::GPT_5_MINI,
            'anthropic' => EntityEnum::fromSlug(setting('anthropic_default_model')),
            'gemini'    => EntityEnum::fromSlug(setting('gemini_default_model')),
            default     => EntityEnum::GPT_5_MINI,
        };
    }

    public static function defaultImageModels(): array
    {
        $settingsTwo = SettingTwo::getCache();

        $models = [];

        $dalleModel = match ($settingsTwo?->dalle) {
            'dalle3' => EntityEnum::DALL_E_3->slug(),
            'dalle2' => EntityEnum::DALL_E_2->slug(),
            default  => $settingsTwo?->dalle ?? EntityEnum::DALL_E_2->slug(),
        };

        if (! setting('dalle_hidden') && $openAIModel = EntityEnum::fromSlug($dalleModel)) {
            $models['openai'] = $openAIModel;
        }

        $stableModel = $settingsTwo?->stablediffusion_default_model ?? $settingTwo?->stablediffusion_default_model ?? EntityEnum::STABLE_DIFFUSION_XL_1024_V_1_0->slug();
        if (! setting('stable_hidden') && $stableModel = EntityEnum::fromSlug($stableModel)) {
            $models['stable'] = $stableModel;
        }

        $falModel = setting('fal_ai_default_model', 'flux-realism');
        if ($falModel = EntityEnum::fromSlug($falModel)) {
            $models['fal'] = $falModel;
        }

        return $models;
    }
}
