<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends AbstractController
{
#[Route('/create-checkout-session', name: 'app_create_checkout_session', methods: ['POST'])]
public function createCheckoutSession(Request $request): JsonResponse
{
Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

$session = Session::create([
'payment_method_types' => ['card'],
'line_items' => [[
'price_data' => [
'currency' => 'eur',
'product_data' => [
'name' => 'Produit Exemple',
],
'unit_amount' => 1000, // = 10.00 €
],
'quantity' => 1,
]],
'mode' => 'payment',
'success_url' => $this->generateUrl('app_checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL),
'cancel_url' => $this->generateUrl('app_checkout_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
]);

return new JsonResponse(['id' => $session->id]);
}
#[Route('/checkout', name: 'stripe_checkout')]
public function checkout(): Response
    {
        return $this->render('stripe/index.html.twig', [
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'],
        ]);
    }
#[Route('/checkout/success', name: 'app_checkout_success')]
public function success(): \Symfony\Component\HttpFoundation\Response
{
return $this->render('stripe/success.html.twig');
}

#[Route('/checkout/cancel', name: 'app_checkout_cancel')]
public function cancel(): \Symfony\Component\HttpFoundation\Response
{
return $this->redirectToRoute('app_cart');
}
}
