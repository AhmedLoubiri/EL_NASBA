<?php

namespace App\Controller;

use App\Entity\Panier;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository
    ) {
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/cart', name: 'app_cart')]
    public function index(SessionInterface $session): Response
    {
        $user = $this->getUser();
        $cartItems = [];
        $subtotal = 0;
        $shipping = 9.99;
        $total = 0;

        if ($user) {
            $panier = $user->getPanier();

            if ($panier) {
                $products = $panier->getProduct();

                foreach ($products as $product) {
                    $quantity = $this->getProductQuantityFromSession($session, $product->getId());
                    $cartItems[] = [
                        'id' => $product->getId(),
                        'product' => $product,
                        'quantity' => $quantity
                    ];
                    $subtotal += $product->getPrix() * $quantity;
                }
            }
        } else {
            $sessionCart = $session->get('cart', []);
            $cartItems = $this->convertSessionToCartItems($sessionCart);
            foreach ($cartItems as $item) {
                $subtotal += $item['product']->getPrix() * $item['quantity'];
            }
        }
        if ($subtotal >= 50) {
            $shipping = 0;
        }

        $total = $subtotal + $shipping;

        return $this->render(
            'cart/cart.html.twig', [
            'controller_name' => 'CartController',
            'cart_items' => $cartItems,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'cart_count' => count($cartItems)
            ]
        );
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(int $id, Request $request, SessionInterface $session): Response
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            $this->addFlash('error', 'Produit non trouvé');
            return $this->redirectToRoute(
                'app_cart', [
                'error' => 'Produit non trouvé'
                ]
            );
        }

        $quantity = $request->request->get('quantity');
        if (!$quantity || !is_numeric($quantity) || $quantity <= 0) {
            $quantity = 1;
        }

        $user = $this->getUser();

        if ($user) {
            $panier = $user->getPanier();

            if (!$panier) {
                $panier = new Panier();
                $user->setPanier($panier);
                $this->entityManager->persist($panier);
            }

            if (!$panier->getProduct()->contains($product)) {
                $panier->addProduct($product);
            }

            $currentQuantity = $this->getProductQuantityFromSession($session, $id);
            $this->setProductQuantityInSession($session, $id, $currentQuantity + $quantity);

            $this->entityManager->flush();
        } else {
            $this->addToSessionCart($session, $id, $quantity);
        }
        $this->addFlash('success', 'Produit ajouté au panier');
        $referrer = $request->headers->get('referer');
        if ($referrer) {
            return $this->redirect($referrer);
        }
        return $this->redirectToRoute('app_cart');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/cart/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(int $id, Request $request, SessionInterface $session): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $quantity = $data['quantity'] ?? 1;

        $user = $this->getUser();

        if ($user) {
            $product = $this->productRepository->find($id);
            $panier = $user->getPanier();

            if (!$product || !$panier || !$panier->getProduct()->contains($product)) {
                return new JsonResponse(['success' => false, 'message' => 'Article non trouvé'], 404);
            }

            if ($quantity <= 0) {
                $panier->removeProduct($product);
                $this->removeProductQuantityFromSession($session, $id);
            } else {
                $this->setProductQuantityInSession($session, $id, $quantity);
            }

            $this->entityManager->flush();
        } else {
            $this->updateSessionCart($session, $id, $quantity);
        }

        return new JsonResponse(['success' => true]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/cart/remove/{id}', name: 'app_cart_remove', methods: ['DELETE'])]
    public function remove(int $id, SessionInterface $session): JsonResponse
    {
        $user = $this->getUser();

        if ($user) {
            $product = $this->productRepository->find($id);
            $panier = $user->getPanier();

            if (!$product || !$panier || !$panier->getProduct()->contains($product)) {
                return new JsonResponse(['success' => false, 'message' => 'Article non trouvé'], 404);
            }

            $panier->removeProduct($product);
            $this->removeProductQuantityFromSession($session, $id);
            $this->entityManager->flush();
        } else {
            $this->removeFromSessionCart($session, $id);
        }

        return new JsonResponse(['success' => true]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/cart/clear', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(SessionInterface $session): JsonResponse
    {
        $user = $this->getUser();

        if ($user) {
            $panier = $user->getPanier();

            if ($panier) {
                foreach ($panier->getProduct() as $product) {
                    $panier->removeProduct($product);
                }
                $this->clearProductQuantitiesFromSession($session);
                $this->entityManager->flush();
            }
        } else {
            $this->clearSessionCart($session);
        }

        return new JsonResponse(['success' => true]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/cart/count', name: 'app_cart_count', methods: ['GET'])]
    public function getCartCount(SessionInterface $session): JsonResponse
    {
        $user = $this->getUser();
        $count = 0;

        if ($user) {
            $panier = $user->getPanier();

            if ($panier) {
                foreach ($panier->getProduct() as $product) {
                    $count += $this->getProductQuantityFromSession($session, $product->getId());
                }
            }
        } else {
            $sessionCart = $session->get('cart', []);
            $count = array_sum($sessionCart);
        }

        return new JsonResponse(['count' => $count]);
    }

    private function addToSessionCart(SessionInterface $session, int $productId, int $quantity): void
    {
        $cart = $session->get('cart', []);

        if (isset($cart[$productId])) {
            $cart[$productId] += $quantity;
        } else {
            $cart[$productId] = $quantity;
        }

        $session->set('cart', $cart);
    }

    private function updateSessionCart(SessionInterface $session, int $productId, int $quantity): void
    {
        $cart = $session->get('cart', []);

        if ($quantity <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $quantity;
        }

        $session->set('cart', $cart);
    }

    private function removeFromSessionCart(SessionInterface $session, int $productId): void
    {
        $cart = $session->get('cart', []);
        unset($cart[$productId]);
        $session->set('cart', $cart);
    }

    private function clearSessionCart(SessionInterface $session): void
    {
        $session->remove('cart');
    }

    private function convertSessionToCartItems(array $sessionCart): array
    {
        $cartItems = [];

        foreach ($sessionCart as $productId => $quantity) {
            $product = $this->productRepository->find($productId);
            if ($product) {
                $cartItems[] = [
                    'id' => $productId,
                    'product' => $product,
                    'quantity' => $quantity
                ];
            }
        }

        return $cartItems;
    }

    private function setProductQuantityInSession(SessionInterface $session, int $productId, int $quantity): void
    {
        $quantities = $session->get('user_cart_quantities', []);
        $quantities[$productId] = $quantity;
        $session->set('user_cart_quantities', $quantities);
    }

    private function removeProductQuantityFromSession(SessionInterface $session, int $productId): void
    {
        $quantities = $session->get('user_cart_quantities', []);
        unset($quantities[$productId]);
        $session->set('user_cart_quantities', $quantities);
    }

    private function clearProductQuantitiesFromSession(SessionInterface $session): void
    {
        $session->remove('user_cart_quantities');
    }

    private function getProductQuantityFromSession(SessionInterface $session, int $productId): int
    {
        $quantities = $session->get('user_cart_quantities', []);
        return $quantities[$productId] ?? 0;
    }
}
