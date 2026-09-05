<?php

namespace App\Services\PaymentGateways;

use Exception;

class WaafiPay
{
    public function formatMerchantPhone($phone)
    {
        $phone = (string) $phone;


        if (substr($phone, 0, 4) === "+252") {
            $formattedPhone = substr($phone, 1, 13);
            return $formattedPhone;
        }

        $countryCodeIncluded = substr($phone, 0, 4) !== "+252" && substr($phone, 0, 3) !== "252";

        if ($countryCodeIncluded) {
            $formattedPhone = "252" . $phone;
            return $formattedPhone;
        }

        return $phone;
    }

    public function payByWaafiPay($params)
    {

        $requiredParams = ['phone', 'amount', 'merchantUid', 'apiUserId', 'apiKey'];
        foreach ($requiredParams as $key) {
            if (!isset($params[$key]) || empty($params[$key])) {
                $errorMessage = "[MERCHANT-ERROR]::: {$key} is required";
                throw new Exception($errorMessage);
            }
        }

        $phone = $params['phone'];
        $amount = $params['amount'];
        $merchantUid = $params['merchantUid'];
        $apiUserId = $params['apiUserId'];
        $apiKey = $params['apiKey'];
        $description = $params['description'] ?? "Test";
        $invoiceId = $params['invoiceId'] ?? uniqid('INV_');
$referenceId = $params['referenceId'] ?? uniqid('REF_');
        $currency = $params['currency'] ?? "USD";
        $hppSuccessCallbackUrl = $params['hppSuccessCallbackUrl'] ?? null;
$hppFailureCallbackUrl = $params['hppFailureCallbackUrl'] ?? null;

        try {
            $sender = $this->formatMerchantPhone($phone);

            $body = [
                "schemaVersion" => "1.0",
                "requestId" => uniqid(),
                "timestamp" => round(microtime(true) * 1000),
                "channelName" => "WEB",
                "serviceName" => "API_PURCHASE",
                "serviceParams" => [
                    "merchantUid" => $merchantUid,
                    "apiUserId" => $apiUserId,
                    "apiKey" => $apiKey,
                    "paymentMethod" => "MWALLET_ACCOUNT",
                    "payerInfo" => [
                        "accountNo" => $sender,
                    ],
                    
                    "transactionInfo" => [
                        "referenceId" => $referenceId,
                        "invoiceId" => $invoiceId,
                        "amount" => $amount,
                        "currency" => $currency,
                        "description" => $description,
                        "paymentBrand"=> "EVCPLUS",
                    ],
                ],
            ];


            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, "https://api.waafipay.net/asm");
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);


            if ($httpCode !== 200) {
                throw new Exception("Failed to connect to WaafiPay API");
            }

            $data = json_decode($response, true);

            if (!isset($data['responseMsg'])) {
    return [
        'status' => false,
        'error' => 'Invalid response from WaafiPay API'
    ];
}


            if ($data['responseMsg'] !== "RCS_SUCCESS") {
                $errorMessage = "";
                switch ($data['responseMsg']) {
                    case "RCS_NO_ROUTE_FOUND":
                        $errorMessage = "Phone Number Not Found";
                        break;
                    case "RCS_USER_REJECTED":
                        $errorMessage = "Customer rejected to authorize payment";
                        break;
                    case "Invalid_PIN":
                        $errorMessage = "Invalid PIN provided by customer";
                        break;
                    default:
                        $errorMessage = $data['responseMsg'];
                        break;
                }


                return [
                    "status" => false,
                    "error" => $errorMessage,
                ];
            }


            return [
                "status" => true,
                "message" => "Paid",
            ];
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw new Exception($e->getMessage());
        }
    }
}