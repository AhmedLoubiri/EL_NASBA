<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Product;
use App\Form\CommandeForm;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commande')]
final class CommandeController extends AbstractController
{
    //crud admin:
    #[Route('/admin', name: 'admin.commandes.list')]
    public function adminIndex(ManagerRegistry $doctrine): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(Commande::class);
        $commandes = $repository->findBy([], ['prix_total' => 'DESC', 'date_commande' => 'DESC']);
        return $this->render(
            'commande/adminIndex.html.twig', [
            'commandes' => $commandes,
            'controller_name' => 'CommandeController',
            ]
        );
    }
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/edit/{id}', name: 'admin_edit_commande')]
    public function editEtatCommande(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $commande = $em->getRepository(Commande::class)->find($id);
        if (!$commande) {
            $this->addFlash('error', 'Commande non trouvée.');
            return $this->redirectToRoute('admin.commandes.list');
        }
        $oldEtat = $commande->getEtat();
        if ($request->isMethod('POST')) {
            $etat = $request->request->get('etat');
            if (!in_array($etat, ['En attente', 'En cours', 'Expédiée', 'Annulée'])) {
                $this->addFlash('error', 'État invalide.');
                return $this->redirectToRoute('admin_edit_commande', ['id' => $id]);
            }
            // La transition de l'etat "En attente" à "En cours", reduction de stock
            if ($oldEtat === 'En attente' && $etat === 'En cours') {
                $productQuantities = $commande->getProductQuantities();
                foreach ($commande->getProducts() as $product) {
                    $id = $product->getId();
                    $qty = $productQuantities[$id] ?? 0;
                    if ($product->getQuantity() < $qty) {
                        $this->addFlash('error', "Stock insuffisant pour le produit : " . $product->getLabel());
                        return $this->redirectToRoute('admin_edit_commande', ['id' => $id]);
                    }

                    $product->setQuantity($product->getQuantity() - $qty);
                }
            }
            $commande->setEtat($etat);
            if($etat === 'Annulée') {
                $productQuantities = $commande->getProductQuantities();
                foreach ($commande->getProducts() as $product) {
                    $id = $product->getId();
                    $qty = $productQuantities[$id] ?? 0;
                    $product->setQuantity($product->getQuantity() + $qty);
                }
            }
            $em->flush();
            $this->addFlash('success', 'État de la commande mis à jour.');
            return $this->redirectToRoute('admin.commandes.list');
        }
        $etats = ['En attente', 'En cours', 'Expédiée', 'Annulée'];
        if( $oldEtat === 'En cours') {
            $etats = ['En cours','Annulée','Expediée'];

        }
        return $this->render('commande/adminEdit.html.twig', [
            'commande' => $commande,
            'etats' => $etats,
        ]);
    }

    //user:
    #[Route('/mes-commandes', name: 'app_orders')]
    public function userIndex(CommandeRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $commandes = $repo->findBy(['user' => $user], ['date_commande' => 'DESC']);
        return $this->render(
            'commande/index.html.twig', [
            'commandes' => $commandes,
            ]
        );
    }
    #[Route('/commande/annuler/{id}', name: 'user.annuler.commande')]
    public function annulerCommande(Commande $commande, EntityManagerInterface $em, Request $request): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user || $commande->getUser() !== $user) {
            throw new AccessDeniedException('Vous ne pouvez pas annuler cette commande.');
        }
        if (in_array($commande->getEtat(), ['Livrée', 'Annulée'])) {
            $this->addFlash('warning', 'Cette commande ne peut pas être annulée.');
            return $this->redirectToRoute('app_orders');
        }
        if ($commande->getEtat() == 'En cours') {
            $productQuantities = $commande->getProductQuantities();
            foreach ($commande->getProducts() as $product) {
                $id = $product->getId();
                $qty = $productQuantities[$id] ?? 0;
                $product->setQuantity($product->getQuantity() + $qty);
            }
        }

        $commande->setEtat('Annulée');
        $em->flush();
        $this->addFlash('success', 'Votre commande a été annulée avec succès.');
        return $this->redirectToRoute('app_orders');
    }
    #[Route('/edit/{id}', name: 'commande.edit')]
    public function editCommande(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $availableProducts = $commande->getProducts()->toArray();
        $form = $this->createForm(
            CommandeForm::class,
            $commande,
            [
                'panier_products' => $availableProducts,
            ]
        );
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $total = 0;
            $productQuantities = $commande->getProductQuantities();
            foreach ($commande->getProducts() as $product) {
                $productId = $product->getId();
                $qty = $productQuantities[$productId] ?? 1; // Par défaut 1 si pas défini
                $total += $product->getPrix() * $qty;
            }
            if ($total < 50) {
                $total += 9.99;
            }
            $commande->setPrixTotal($total);
            $em->flush();

            $this->addFlash('success', 'Commande modifiée avec succès.');
            return $this->redirectToRoute('app_orders');
        }

        return $this->render(
            'commande/edit.html.twig', [
                'form' => $form->createView(),
                'commande' => $commande
            ]
        );
    }
    //final
    #[Route('admin/supprimer/{id}', name: 'admin.commande.cancel', methods: ['GET'])]
    public function cancel(Commande $commande, EntityManagerInterface $entityManager): RedirectResponse
    {
        // Vérifie si la commande est encore "En attente" ou si elle est annulée avant de la supprimer de la base.
        if ($commande->getEtat() !== 'En attente' && $commande->getEtat() !== 'Annulée') {
            $this->addFlash('warning', 'Seules les commandes en attente ou annulées peuvent être supprimées.');
            return $this->redirectToRoute('admin.commandes.list');
        }
        // Supprimer la commande de la base
        $entityManager->remove($commande);
        $entityManager->flush();
        $this->addFlash('success', 'La commande a été supprimée avec succès.');
        return $this->redirectToRoute('admin.commandes.list');
    }

    #[Route('/validate', name: 'app_validation')]
    public function validateCommande(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $session = $request->getSession();

        if (!$user) {
            $this->addFlash('error', 'Veuillez vous connecter pour passer une commande.');
            return $this->redirectToRoute('app_login');
        }
        $panier = $user->getPanier();
        $quantities = $session->get('user_cart_quantities', []);
        $productsInCart = [];
        if ($panier) {
            foreach ($panier->getProduct() as $product) {
                $id = $product->getId();
                $qty = $quantities[$id] ?? 0;
                if ($qty > 0) {
                    $productsInCart[] = $product;
                }
            }
        }
        if (empty($productsInCart)) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('app_cart');
        }
        $commande = new Commande();
        $form = $this->createForm(CommandeForm::class, $commande, [
            'panier_products' => $productsInCart
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $selectedProducts = $commande->getProducts();
            $total = 0;
            $productQuantities = [];
            foreach ($selectedProducts as $product) {
                $id = $product->getId();
                $qty = $quantities[$id] ?? 0;
                if ($product->getQuantity() < $qty) {
                    $this->addFlash('error', "Stock insuffisant pour : " . $product->getLabel());
                    return $this->redirectToRoute('app_cart');
                }
                $total += $product->getPrix() * $qty;
                $productQuantities[$id] = $qty;
                $user->getPanier()->removeProduct($product);
                unset($quantities[$id]);
            }
            if ($total < 50.0) {
                $total += 9.99;
            }
            $commande->setUser($user);
            $commande->setEtat('En attente');
            $commande->setPrixTotal($total);
            $commande->setProductQuantities($productQuantities);
            $em->persist($commande);
            $em->flush();
            $session->set('user_cart_quantities', $quantities);
            $this->addFlash('success', 'Commande validée avec succès.');
            return $this->redirectToRoute('app_orders');
        }
        return $this->render('commande/validation.html.twig', [
            'form' => $form->createView(),
            'quantities' => $quantities,// tableau [product_id => qty]
            'products' => $productsInCart,


        ]);
    }

    #[Route('/admin/{id<\d+>}', name: 'admin.commandes.detail')]
    public function adminDetail(Commande $commande = null): Response
    {
        if (!$commande) {
            $this->addFlash('error', "La commande n'existe pas");
            return $this->redirectToRoute('admin.commandes.list');
        }
        return $this->render(
            'commande/adminDetail.html.twig', [
            'commande' => $commande
            ]
        );
    }
    #[Route('/{id<\d+>}', name: 'commandes.detail')]
    public function detail(Commande $commande = null): Response
    {
        if (!$commande) {
            $this->addFlash('error', "La commande n'existe pas");
            return $this->redirectToRoute('commande.list');
        }
        return $this->render(
            'commande/detail.html.twig', [
            'commande' => $commande
            ]
        );
    }
}

