<?php
/**
 * MTN MoMo Open API Client — PHP
 * Supports Collections (request-to-pay) and Disbursements (transfer).
 * Sandbox: https://sandbox.momodeveloper.mtn.com
 */
class MomoBaseService {
    protected string $baseUrl;
    protected string $environment;
    protected string $currency;
    protected string $subscriptionKey;
    protected string $apiUserId;
    protected string $apiKey;
    protected string $productPath;

    public function __construct(string $subscriptionKey, string $apiUserId, string $apiKey, string $productPath) {
        $this->baseUrl         = MOMO_BASE_URL;
        $this->environment     = MOMO_ENVIRONMENT;
        $this->currency        = MOMO_CURRENCY;
        $this->subscriptionKey = $subscriptionKey;
        $this->apiUserId       = $apiUserId;
        $this->apiKey          = $apiKey;
        $this->productPath     = $productPath;
    }

    protected function getAccessToken(): string {
        if (!$this->apiUserId || !$this->apiKey) throw new RuntimeException('MoMo API credentials not configured.');
        $creds = base64_encode("{$this->apiUserId}:{$this->apiKey}");
        $res   = $this->request('POST', "/{$this->productPath}/token/", [], [
            'Authorization' => "Basic {$creds}",
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ]);
        return $res['access_token'] ?? throw new RuntimeException('Could not get access token.');
    }

    protected function request(string $method, string $path, array $body=[], array $extraHeaders=[]): array {
        $url  = $this->baseUrl . $path;
        $hdrs = array_merge([
            'Content-Type: application/json',
            'X-Target-Environment: ' . $this->environment,
            'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
        ], array_map(fn($k,$v)=>"$k: $v", array_keys($extraHeaders), array_values($extraHeaders)));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $hdrs,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->logTransaction($method, $path, $body, $response, $httpCode);
        $data = json_decode($response ?: '{}', true) ?: [];
        return array_merge($data, ['_http_code' => $httpCode]);
    }

    protected function logTransaction(string $method, string $path, array $req, string $res, int $code): void {
        try {
            db()->prepare("INSERT INTO momo_transactions (type,request_payload,response_payload,status) VALUES (?,?,?,?)")
               ->execute([strtoupper($this->productPath), json_encode($req), $res, $code >= 200 && $code < 300 ? 'OK' : 'FAILED']);
        } catch (Exception) {}
    }

    public function createApiUser(string $callbackHost='webhook.site'): string {
        $refId = $this->uuid4();
        $this->request('POST', '/v1_0/apiuser', ['providerCallbackHost' => $callbackHost], [
            'X-Reference-Id' => $refId,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ]);
        return $refId;
    }

    public function generateApiKey(string $apiUserId): string {
        $res = $this->request('POST', "/v1_0/apiuser/{$apiUserId}/apikey", [], []);
        return $res['apiKey'] ?? throw new RuntimeException('Could not generate API key.');
    }

    protected function uuid4(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
        );
    }
}

class MomoCollectionService extends MomoBaseService {
    public function __construct() {
        parent::__construct(
            MOMO_COLLECTIONS_SUBSCRIPTION_KEY,
            MOMO_COLLECTIONS_API_USER_ID,
            MOMO_COLLECTIONS_API_KEY,
            'collection'
        );
    }

    public function requestToPay(float $amount, string $phone, string $externalId, string $payerMessage='', string $payeeNote=''): array {
        $phone    = normalize_phone($phone);
        $refId    = $this->uuid4();
        $token    = $this->getAccessToken();
        $payload  = [
            'amount'      => (string)$amount,
            'currency'    => $this->currency,
            'externalId'  => $externalId,
            'payer'       => ['partyIdType'=>'MSISDN','partyId'=>$phone],
            'payerMessage'=> substr($payerMessage, 0, 160),
            'payeeNote'   => substr($payeeNote, 0, 160),
        ];

        $ch = curl_init($this->baseUrl . '/collection/v1_0/requesttopay');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-Reference-Id: ' . $refId,
                'X-Target-Environment: ' . $this->environment,
                'X-Callback-Url: https://webhook.site/momo-callback',
                'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
            ],
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 202) throw new RuntimeException("requestToPay failed: HTTP {$code} — {$res}");
        return ['reference_id' => $refId, 'status' => 'INITIATED'];
    }

    public function getPaymentStatus(string $referenceId): array {
        $token = $this->getAccessToken();
        $ch = curl_init($this->baseUrl . '/collection/v1_0/requesttopay/' . $referenceId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'X-Target-Environment: ' . $this->environment,
                'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
            ],
        ]);
        $res  = curl_exec($ch); curl_close($ch);
        return json_decode($res ?: '{}', true) ?: [];
    }
}

class MomoDisbursementService extends MomoBaseService {
    public function __construct() {
        parent::__construct(
            MOMO_DISBURSEMENTS_SUBSCRIPTION_KEY,
            MOMO_DISBURSEMENTS_API_USER_ID,
            MOMO_DISBURSEMENTS_API_KEY,
            'disbursement'
        );
    }

    public function transfer(float $amount, string $phone, string $externalId, string $payeeNote=''): array {
        $phone   = normalize_phone($phone);
        $refId   = $this->uuid4();
        $token   = $this->getAccessToken();
        $payload = [
            'amount'      => (string)$amount,
            'currency'    => $this->currency,
            'externalId'  => $externalId,
            'payee'       => ['partyIdType'=>'MSISDN','partyId'=>$phone],
            'payerMessage'=> 'Susu payout',
            'payeeNote'   => substr($payeeNote, 0, 160),
        ];

        $ch = curl_init($this->baseUrl . '/disbursement/v1_0/transfer');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-Reference-Id: ' . $refId,
                'X-Target-Environment: ' . $this->environment,
                'X-Callback-Url: https://webhook.site/momo-callback',
                'Ocp-Apim-Subscription-Key: ' . $this->subscriptionKey,
            ],
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 202) throw new RuntimeException("Transfer failed: HTTP {$code} — {$res}");
        return ['reference_id' => $refId, 'status' => 'INITIATED'];
    }
}
