<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Tools\ShopifyToolHandler;
use App\Extensions\ChatbotEcommerce\System\Tools\WooCommerceToolHandler;
use Illuminate\Support\Facades\Log;

class EcommerceToolService
{
    /**
     * Handle an OpenAI-format tool call.
     * $call['function']['arguments'] is a JSON string.
     */
    public function handleToolCall(Chatbot $chatbot, string $function, array $call): ?string
    {
        $functionArgs = (array) json_decode($call['function']['arguments']);

        return $this->resolveToolCall($chatbot, $function, $functionArgs);
    }

    /**
     * Handle an Anthropic-format tool call.
     * $input is already a parsed array.
     */
    public function handleAnthropicToolCall(Chatbot $chatbot, string $function, array $input): ?string
    {
        return $this->resolveToolCall($chatbot, $function, $input);
    }

    /**
     * Handle a Gemini-format function call.
     * $args is already a parsed array.
     */
    public function handleGeminiToolCall(Chatbot $chatbot, string $function, array $args): ?string
    {
        return $this->resolveToolCall($chatbot, $function, $args);
    }

    /**
     * Core tool call logic shared across all providers.
     *
     * @param  array<string, mixed>  $functionArgs
     */
    private function resolveToolCall(Chatbot $chatbot, string $function, array $functionArgs): ?string
    {
        // Shopify Tools
        if (
            $chatbot->is_shop &&
            $chatbot->shop_source == 'shopify' &&
            in_array($function, ['getProducts', 'getPaymentGateway'])
        ) {
            $shopifyToolHandler = new ShopifyToolHandler(
                $chatbot->shopify_domain,
                $chatbot->shopify_access_token
            );

            switch ($function) {
                case 'getProducts':
                    $sRes = $shopifyToolHandler->handleToolCall($function, $functionArgs);

                    if (empty($sRes['products'])) {
                        $sRes = $shopifyToolHandler->handleToolCall('getProducts', ['']);

                        return '<p>Didn\'t find a perfect match, but these could be just what you need!</p>' . strval($shopifyToolHandler->parseProducts($sRes['products'] ?? []));
                    }

                    return strval($shopifyToolHandler->parseProducts($sRes['products'] ?? []));
                case 'getPaymentGateway':
                    $gateway = $functionArgs['gateway'] ?? '';
                    $getGateway = $shopifyToolHandler->getPaymentGateways($gateway);

                    if (empty($getGateway['message'])) {
                        return 'We are sorry, but the requested payment gateway is not available in our store. Please choose from the available payment methods.';
                    }

                    return $getGateway['message'];
            }
        }

        // WooCommerce Tools
        if (
            $chatbot->is_shop &&
            $chatbot->shop_source == 'woocommerce' &&
            in_array($function, ['getProducts', 'getPaymentGateway', 'getShippingMethods', 'getCoupons', 'getProductReviews'])
        ) {
            $wooToolHandler = new WooCommerceToolHandler(
                $chatbot->woocommerce_domain,
                $chatbot->woocommerce_consumer_key,
                $chatbot->woocommerce_consumer_secret
            );

            switch ($function) {
                case 'getProducts':
                    $args = [
                        'search'        => $functionArgs['query'] ?? '',
                        'search_fields' => ['name', 'description', 'short_description'],
                        'orderby'       => $functionArgs['orderby'] ?? 'date',
                        'order'         => $functionArgs['order'] ?? 'desc',
                        'per_page'      => 5,
                        'min_price'     => $functionArgs['min_price'] ?? '',
                        'max_price'     => $functionArgs['max_price'] ?? '',
                    ];

                    if (isset($functionArgs['stock_status']) && $functionArgs['stock_status'] === 'instock') {
                        $args['stock_status'] = 'instock';
                    }

                    $products = $wooToolHandler->getProducts($args);

                    Log::info(print_r($args, true));

                    if (empty($products)) {
                        $products = $wooToolHandler->getProducts([
                            'orderby'  => 'popularity',
                            'order'    => 'desc',
                            'per_page' => 5,
                        ]);

                        return '<p>Didn\'t find a perfect match, but these could be just what you need!</p>' . strval($wooToolHandler->parseProducts($products));
                    }

                    return strval($wooToolHandler->parseProducts($products));
                case 'getPaymentGateway':
                    $gateway = $functionArgs['gateway'] ?? '';
                    $getGateway = $wooToolHandler->getPaymentGateways($gateway);

                    if (empty($getGateway)) {
                        return 'We are sorry, but the requested payment gateway is not available in our store. Please choose from the available payment methods.';
                    }

                    return $getGateway;
                case 'getShippingMethods':
                    $getMethod = $wooToolHandler->getShippingMethods();

                    if (empty($getMethod)) {
                        return 'We are sorry, but there are no shipping methods available in our store at the moment.';
                    }

                    return $getMethod;
                case 'getCoupons':
                    $getCoupon = $wooToolHandler->getCoupons();

                    if (empty($getCoupon)) {
                        return 'We are sorry, but there are no shipping methods available in our store at the moment.';
                    }

                    return $getCoupon;
                case 'getProductReviews':
                    $product_id = $functionArgs['productID'] ?? '';
                    $getReview = $wooToolHandler->getProductReviews($product_id);

                    if (empty($getReview)) {
                        return 'No reviews available for this product at the moment.';
                    }

                    return $getReview;
            }
        }

        return null;
    }

    /**
     * Handle an OpenAI-format tool call, returning structured data with optional UI rendering.
     *
     * @return array{ai_content: string, ui: string|null}|null
     */
    public function handleToolCallWithUi(Chatbot $chatbot, string $function, array $call): ?array
    {
        $functionArgs = (array) json_decode($call['function']['arguments']);

        return $this->resolveToolCallWithUi($chatbot, $function, $functionArgs);
    }

    /**
     * Handle an Anthropic-format tool call, returning structured data with optional UI rendering.
     *
     * @return array{ai_content: string, ui: string|null}|null
     */
    public function handleAnthropicToolCallWithUi(Chatbot $chatbot, string $function, array $input): ?array
    {
        return $this->resolveToolCallWithUi($chatbot, $function, $input);
    }

    /**
     * Handle a Gemini-format function call, returning structured data with optional UI rendering.
     *
     * @return array{ai_content: string, ui: string|null}|null
     */
    public function handleGeminiToolCallWithUi(Chatbot $chatbot, string $function, array $args): ?array
    {
        return $this->resolveToolCallWithUi($chatbot, $function, $args);
    }

    /**
     * Core tool call logic that returns both AI-consumable text and optional UI rendering.
     *
     * @param  array<string, mixed>  $functionArgs
     *
     * @return array{ai_content: string, ui: string|null}|null
     */
    private function resolveToolCallWithUi(Chatbot $chatbot, string $function, array $functionArgs): ?array
    {
        // Shopify Tools
        if (
            $chatbot->is_shop &&
            $chatbot->shop_source == 'shopify' &&
            in_array($function, ['getProducts', 'getPaymentGateway'])
        ) {
            $shopifyToolHandler = new ShopifyToolHandler(
                $chatbot->shopify_domain,
                $chatbot->shopify_access_token
            );

            switch ($function) {
                case 'getProducts':
                    $sRes = $shopifyToolHandler->handleToolCall($function, $functionArgs);
                    $products = $sRes['products'] ?? [];
                    $noExactMatch = false;

                    if (empty($products)) {
                        $sRes = $shopifyToolHandler->handleToolCall('getProducts', ['']);
                        $products = $sRes['products'] ?? [];
                        $noExactMatch = true;
                    }

                    return [
                        'ai_content' => $noExactMatch
                            ? "I couldn't find an exact match, but here are some products you might like!"
                            : $this->productsToText($products),
                        'ui' => strval($shopifyToolHandler->parseProducts($products)),
                    ];
                case 'getPaymentGateway':
                    $gateway = $functionArgs['gateway'] ?? '';
                    $getGateway = $shopifyToolHandler->getPaymentGateways($gateway);
                    $message = $getGateway['message'] ?? 'We are sorry, but the requested payment gateway is not available in our store.';

                    return ['ai_content' => $message, 'ui' => null];
            }
        }

        // WooCommerce Tools
        if (
            $chatbot->is_shop &&
            $chatbot->shop_source == 'woocommerce' &&
            in_array($function, ['getProducts', 'getPaymentGateway', 'getShippingMethods', 'getCoupons', 'getProductReviews'])
        ) {
            $wooToolHandler = new WooCommerceToolHandler(
                $chatbot->woocommerce_domain,
                $chatbot->woocommerce_consumer_key,
                $chatbot->woocommerce_consumer_secret
            );

            switch ($function) {
                case 'getProducts':
                    $args = [
                        'search'        => $functionArgs['query'] ?? '',
                        'search_fields' => ['name', 'description', 'short_description'],
                        'orderby'       => $functionArgs['orderby'] ?? 'date',
                        'order'         => $functionArgs['order'] ?? 'desc',
                        'per_page'      => 5,
                        'min_price'     => $functionArgs['min_price'] ?? '',
                        'max_price'     => $functionArgs['max_price'] ?? '',
                    ];

                    if (isset($functionArgs['stock_status']) && $functionArgs['stock_status'] === 'instock') {
                        $args['stock_status'] = 'instock';
                    }

                    $products = $wooToolHandler->getProducts($args);
                    $noExactMatch = false;

                    if (empty($products)) {
                        $products = $wooToolHandler->getProducts([
                            'orderby'  => 'popularity',
                            'order'    => 'desc',
                            'per_page' => 5,
                        ]);
                        $noExactMatch = true;
                    }

                    return [
                        'ai_content' => $noExactMatch
                            ? "I couldn't find an exact match, but here are some products you might like!"
                            : $this->productsToText($products),
                        'ui' => strval($wooToolHandler->parseProducts($products)),
                    ];
                case 'getPaymentGateway':
                    $gateway = $functionArgs['gateway'] ?? '';
                    $getGateway = $wooToolHandler->getPaymentGateways($gateway);
                    $message = is_string($getGateway) ? $getGateway : 'We are sorry, but the requested payment gateway is not available in our store.';

                    return ['ai_content' => $message, 'ui' => null];
                case 'getShippingMethods':
                    $getMethod = $wooToolHandler->getShippingMethods();
                    $message = empty($getMethod) ? 'We are sorry, but there are no shipping methods available in our store at the moment.' : $getMethod;

                    return ['ai_content' => $message, 'ui' => null];
                case 'getCoupons':
                    $getCoupon = $wooToolHandler->getCoupons();
                    $message = empty($getCoupon) ? 'We are sorry, but there are no coupons available in our store at the moment.' : $getCoupon;

                    return ['ai_content' => $message, 'ui' => null];
                case 'getProductReviews':
                    $product_id = $functionArgs['productID'] ?? '';
                    $getReview = $wooToolHandler->getProductReviews($product_id);
                    $message = empty($getReview) ? 'No reviews available for this product at the moment.' : $getReview;

                    return ['ai_content' => $message, 'ui' => null];
            }
        }

        return null;
    }

    /**
     * Converts a products array into a plain-text summary for AI consumption.
     *
     * @param  array<int, array<string, mixed>>  $products
     */
    private function productsToText(array $products): string
    {
        if (empty($products)) {
            return 'No products were found.';
        }

        $count = count($products);

        return "I found {$count} product" . ($count > 1 ? 's' : '') . ' for you. Take a look!';
    }

    /**
     * OpenAI tool definitions format.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getToolDefinitions(Chatbot $chatbot): array
    {
        if (! $chatbot->is_shop) {
            return [];
        }

        $tools = [];

        $tools[] = [
            'type'     => 'function',
            'function' => $this->getProductsDeclaration(),
        ];

        if (is_array($chatbot->shop_features) && in_array('getPaymentGateway', $chatbot->shop_features, true)) {
            $tools[] = [
                'type'     => 'function',
                'function' => $this->getPaymentGatewayDeclaration(),
            ];
        }

        if ($chatbot->shop_source == 'woocommerce') {
            foreach ($this->getWooCommerceDeclarations($chatbot) as $declaration) {
                $tools[] = ['type' => 'function', 'function' => $declaration];
            }
        }

        return $tools;
    }

    /**
     * Anthropic tool definitions format (uses input_schema instead of parameters).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAnthropicToolDefinitions(Chatbot $chatbot): array
    {
        if (! $chatbot->is_shop) {
            return [];
        }

        $tools = [];

        $tools[] = $this->toAnthropicFormat($this->getProductsDeclaration());

        if (is_array($chatbot->shop_features) && in_array('getPaymentGateway', $chatbot->shop_features, true)) {
            $tools[] = $this->toAnthropicFormat($this->getPaymentGatewayDeclaration());
        }

        if ($chatbot->shop_source == 'woocommerce') {
            foreach ($this->getWooCommerceDeclarations($chatbot) as $declaration) {
                $tools[] = $this->toAnthropicFormat($declaration);
            }
        }

        return $tools;
    }

    /**
     * Gemini function declarations format (no outer wrapper, uses parameters key).
     * Returns declarations only — to be merged into functionDeclarations[].
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGeminiToolDefinitions(Chatbot $chatbot): array
    {
        if (! $chatbot->is_shop) {
            return [];
        }

        $declarations = [];

        $declarations[] = $this->getProductsDeclaration();

        if (is_array($chatbot->shop_features) && in_array('getPaymentGateway', $chatbot->shop_features, true)) {
            $declarations[] = $this->getPaymentGatewayDeclaration();
        }

        if ($chatbot->shop_source == 'woocommerce') {
            foreach ($this->getWooCommerceDeclarations($chatbot) as $declaration) {
                $declarations[] = $declaration;
            }
        }

        return $declarations;
    }

    /**
     * Convert an OpenAI-style function declaration to Anthropic format.
     * Renames 'parameters' to 'input_schema'.
     *
     * @param  array<string, mixed>  $declaration
     *
     * @return array<string, mixed>
     */
    private function toAnthropicFormat(array $declaration): array
    {
        return [
            'name'         => $declaration['name'],
            'description'  => $declaration['description'],
            'input_schema' => $declaration['parameters'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductsDeclaration(): array
    {
        return [
            'name'        => 'getProducts',
            'description' => 'Finds products in the store based on a keyword or search query. This is useful when the user asks for products.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'query' => [
                        'type'        => 'string',
                        'description' => 'A concise search query starting with the singular product name, followed by factual attributes or preferences only (such as color, size, or material). Ignore subjective or price-related words such as "cheap", "expensive", "under", "below", "less than", or numeric price values. Always use singular form for the product type. Examples: "t-shirt cotton", "t-shirt comfort", "hoodie M", "dress red L". Example input: "find hoodie under 70" → query should be "hoodie".',
                    ],
                    'orderby' => [
                        'type'        => 'string',
                        'enum'        => ['date', 'modified', 'id', 'title', 'slug', 'price', 'popularity', 'rating'],
                        'description' => 'Determines how products should be sorted. Default is "date".',
                        'default'     => 'popularity',
                    ],
                    'order' => [
                        'type'        => 'string',
                        'enum'        => ['asc', 'desc'],
                        'description' => 'Specifies the sort direction. Default is "desc".',
                        'default'     => 'desc',
                    ],
                    'min_price' => [
                        'type'        => 'string',
                        'description' => 'The minimum price limit for filtering products. Extract numeric value from user queries like "above 50", "over 100", or "min 20". Remove the decimals, example: 100.00 → 100',
                    ],
                    'max_price' => [
                        'type'        => 'string',
                        'description' => 'The maximum price limit for filtering products. Extract numeric value from user queries like "under 70", "below 100", or "max 200". Remove the decimals, example: 100.00 → 100',
                    ],
                    'stock_status' => [
                        'type'        => 'string',
                        'description' => 'Optional. Use "instock" if the user specifically requests to show only available or in-stock products.',
                    ],
                ],
                'required' => ['query', 'orderby', 'order'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPaymentGatewayDeclaration(): array
    {
        return [
            'name'        => 'getPaymentGateway',
            'description' => 'Use this function whenever the user asks about payment methods — for example, whether a specific payment method like PayPal, Stripe, or Mollie is accepted, or when they ask for all available payment methods. Use this function for product-related matters: example: show me alternative products, show me cheaper ones, etc.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'gateway' => [
                        'type'        => 'string',
                        'description' => 'The payment gateway name or keyword. Use "all" if the user asks about all payment methods. Examples: "paypal", "stripe", "mollie", "all".',
                    ],
                ],
                'required' => ['gateway'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getWooCommerceDeclarations(Chatbot $chatbot): array
    {
        $declarations = [];

        if (is_array($chatbot->shop_features) && in_array('getShippingMethods', $chatbot->shop_features, true)) {
            $declarations[] = [
                'name'        => 'getShippingMethods',
                'description' => 'Use this function whenever the user asks about shipping, delivery options, shipping costs, or available shipping methods. For example: "Do you offer free shipping?" or "What are your shipping options?".',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'method' => [
                            'type'        => 'string',
                            'enum'        => ['free', 'all'],
                            'description' => 'Specify "free" if the user asks about free shipping, or "all" if they ask generally about available shipping options.',
                        ],
                    ],
                    'required' => ['method'],
                ],
            ];
        }

        if (is_array($chatbot->shop_features) && in_array('getCoupons', $chatbot->shop_features, true)) {
            $declarations[] = [
                'name'        => 'getCoupons',
                'description' => 'Use this function whenever the user asks about discount codes, promo codes, or coupons. For example: "Do you have any discounts?", "Is there a coupon code?", or "Can I get a promo code?".',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'type' => [
                            'type'        => 'string',
                            'description' => 'The specific coupon type or name if the user mentions one, or "all" if they ask generally about available coupons.',
                        ],
                    ],
                    'required' => [],
                ],
            ];
        }

        if (is_array($chatbot->shop_features) && in_array('getProductReviews', $chatbot->shop_features, true)) {
            $declarations[] = [
                'name'        => 'getProductReviews',
                'description' => 'Use this function **only** when the user asks about product reviews, ratings, or feedback. For example: "Show reviews for a T-Shirt", "Any ratings for hoodie M?", "What do people say about this product?". Ask the user for the product name if not specified, then find the corresponding product ID from previously added products in productAddToCart($PRODUCT_ID).',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'productID' => [
                            'type'        => 'string',
                            'description' => 'The ID of the product for which reviews are requested. Retrieve it by matching the product name provided by the user to a previously added product in productAddToCart.',
                        ],
                    ],
                    'required' => ['productID'],
                ],
            ];
        }

        return $declarations;
    }
}
