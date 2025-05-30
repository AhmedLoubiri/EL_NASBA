<?php

namespace App\Controller;

use App\Entity\Panier;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

final class StripeController extends AbstractController
{
    #[Route('/payment/{cartId}', name: 'app_stripe_payment')]
    public function payment(int $cartId, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $cart = $entityManager->getRepository(Panier::class)->find($cartId);
        if (!$cart) {
            throw $this->createNotFoundException('Cart not found');
        }
        $items = [];
        $quantities = $session->get('user_cart_quantities', []);

        $products = $cart->getProduct();
        foreach ($products as $product) {
            $items[] = [
                'quantity' => $quantities[$product->getId()] ?? 1,
                'label' => $product->getLabel(),
                'price' => $product->getPrix(),
                'imageUrl' => $product->getImageUrl(),
            ];
        }
        return $this->render(
            'stripe/payment.html.twig', [
              'panier' => $cart,
              'items'  => $items,
              'cartId' => $cartId,
            ]
        );
    }

    #[Route('/checkout/{cartId}', name: 'app_stripe_checkout', methods:'POST')]
    public function checkout(int $cartId,
        SessionInterface $session,
        EntityManagerInterface $em,
        ParameterBagInterface $param
    ): Response {
        $cart = $em->getRepository(Panier::class)->find($cartId);
        if(!$cart) {
            throw $this->createNotFoundException('cart is not found');
        }
        $items = [];
        $quantities = $session->get('user_cart_quantities', []);

        $products = $cart->getProduct();
        foreach($products as $product) {
            $items[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $product->getLabel(),
                        'description' => $product->getDescription(),
                        'images' => [
                             $product->getImageUrl()
                        ],
                    ],
                    'unit_amount' => (int) round($product->getPrix() * 100),
                ],
                'quantity' => $quantities[$product->getId()] ?? 1,
            ];
        }
        // dd($items);
        $stripeSK = $param->get('stripe_secret_key');
        \Stripe\Stripe::setApiKey($stripeSK);
        $session = \Stripe\Checkout\Session::create(
            [
            'line_items' => $items,
            'mode' => 'payment',
            'success_url' => $this->generateUrl('app_stripe_sucess', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'cancel_url' => $this->generateUrl('app_stripe_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]
        );

        return $this->redirect($session->url, 303);
    }

     #[Route('/success-url', name: 'app_stripe_sucess')]
    public function successUrl(): Response
    {
        return $this->render('stripe/success.html.twig', []);
    }

    #[Route('/cancel-url', name: 'app_stripe_cancel')]
    public function failureUrl(): Response
    {
        return $this->render('stripe/failure.html.twig', []);
    }
}
