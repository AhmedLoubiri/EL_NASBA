<?php
// src/Controller/StripeController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends AbstractController
{
#[Route('/create-checkout-session', name: 'stripe_checkout', methods: ['POST'])]
public function createCheckoutSession(Request $request): JsonResponse
{
Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
$session = Session::create([
'payment_method_types' => ['card'],
'line_items' => [[
'price_data' => [
'currency' => 'eur',
'unit_amount' => 2000, // 20€ in cents
'product_data' => [
'name' => 'Commande #123',
],
],
'quantity' => 1,
]],
'mode' => 'payment',
'success_url' => $this->generateUrl('payment_success', [], 0),
'cancel_url' => $this->generateUrl('payment_cancel', [], 0),
]);

return new JsonResponse(['id' => $session->id]);
}

#[Route('/payment/success', name: 'payment_success')]
public function success()
{
return $this->render('stripe/success.html.twig');
}

#[Route('/payment/cancel', name: 'payment_cancel')]
public function cancel()
{
return $this->render('stripe/cancel.html.twig');
}
}
