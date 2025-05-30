<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class PaymeeClient
{
    private $client;
    private $apiKey;
    private $merchantCode;
    private $logger;

    public function __construct(HttpClientInterface $client, string $apiKey, string $merchantCode, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->merchantCode = $merchantCode;
        $this->logger = $logger;
    }

    public function createPayment(float $amount, string $note, string $backUrl): ?string
    {
        try {
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
                'timeout' => 30, // Timeout de 30 secondes
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false); // false pour éviter les exceptions sur les erreurs HTTP

            if ($this->logger) {
                $this->logger->info('Paymee API Response', [
                    'status_code' => $statusCode,
                    'response_data' => $data
                ]);
            }

            if ($statusCode === 200 || $statusCode === 201) {
                return $data['payment_url'] ?? null;
            }

            // Log des erreurs
            if ($this->logger) {
                $this->logger->error('Paymee API Error', [
                    'status_code' => $statusCode,
                    'response' => $data
                ]);
            }

            return null;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Paymee API Exception', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return null;
        }
    }
}