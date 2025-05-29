<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PaymeeClient
{
    private $client;
    private $apiKey;
    private $merchantCode;

    public function __construct(HttpClientInterface $client, string $apiKey, string $merchantCode)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->merchantCode = $merchantCode;
    }

    public function createPayment(float $amount, string $note, string $backUrl): ?string
    {
        $response = $this->client->request('POST', 'https://sandbox.paymee.tn/api/v1/payments/create', [
            'headers' => [
                'Authorization' => 'Token ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'vendor' => $this->merchantCode,
                'amount' => $amount,
                'note' => $note,
                'mode' => 'card',
                'back_url' => $backUrl,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode === 200) {
            $data = $response->toArray();
            return $data['payment_url'] ?? null;
        }

        return null;
    }
}
