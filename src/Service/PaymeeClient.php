<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class FlouciService
{
private string $appId;
private string $appSecret;
private HttpClientInterface $client;

public function __construct(HttpClientInterface $client)
{
$this->appId = $_ENV['FLOUCI_APP_ID'];
$this->appSecret = $_ENV['FLOUCI_APP_SECRET'];
$this->client = $client;
}

public function initiatePayment(array $commandeData): ?string
{
$response = $this->client->request('POST', 'https://developers.flouci.com/api/generate_payment', [
'headers' => [
'Content-Type' => 'application/json',
'apppublic' => $this->appId,
'appsecret' => $this->appSecret,
],
'json' => [
'app_token' => $this->appId,
'amount' => $commandeData['amount'],
'redirect_url' => $commandeData['redirect_url'],
'webhook_url' => $commandeData['webhook_url'],
'client' => [
'email' => $commandeData['email'],
'phone_number' => $commandeData['phone'],
'first_name' => $commandeData['first_name'],
'last_name' => $commandeData['last_name'],
],
]
]);

$data = $response->toArray(false);

return $data['payment_url'] ?? null;
}
}
