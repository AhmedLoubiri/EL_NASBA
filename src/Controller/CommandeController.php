<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Product;
use App\Form\CommandeForm;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        return $this->render('commande/adminIndex.html.twig', [
            'commandes' => $commandes,
            'controller_name' => 'CommandeController',
        ]);
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
        if ($request->isMethod('POST')) {
            $etat = $request->request->get('etat');
            if (!in_array($etat, ['En attente', 'En cours', 'Expédiée', 'Annulée'])) {
                $this->addFlash('error', 'État invalide.');
                return $this->redirectToRoute('admin_commande_edit', ['id' => $id]);
            }
            $commande->setEtat($etat);
            $em->flush();
            $this->addFlash('success', 'État de la commande mis à jour.');
            return $this->redirectToRoute('admin.commandes.list');
        }

        return $this->render('commande/adminEdit.html.twig', [
            'commande' => $commande,
            'etats' => ['En attente', 'En cours', 'Expédiée', 'Annulée']
        ]);
    }
    //user:
    #[Route('/mes-commandes', name: 'app_orders')]
    public function userIndex(CommandeRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $commandes = $repo->findBy(['user' => $user], ['date_commande' => 'DESC']);
        return $this->render('commande/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }
    #[Route('/edit/{id}', name: 'commande_edit')]
    public function editCommande(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CommandeForm::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Optional: update total price if products changed
            $total = 0;
            foreach ($commande->getProducts() as $product) {
                $total += $product->getPrix(); // Adjust as needed
            }
            $commande->setPrixTotal($total);

            $em->flush();

            $this->addFlash('success', 'Commande modifiée avec succès.');
            return $this->redirectToRoute('app_orders');
        }

        return $this->render('commande/edit.html.twig', [
            'form' => $form->createView(),
            'commande' => $commande
        ]);
    }

    /*#[Route('/commande/edit/{id}', name: 'commande_user_edit')]
    public function userEditCommande(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CommandeForm::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La commande a été modifiée avec succès.');
            return $this->redirectToRoute('app_orders');
        }

        return $this->render('commande/edit.html.twig', [
            'form' => $form->createView(),
            'commande' => $commande,
        ]);
    }*/
    #[Route('/annuler/{id}', name: 'commande_cancel', methods: ['GET'])]
    public function cancel(Commande $commande, EntityManagerInterface $entityManager): RedirectResponse
    {
        // Vérifie si la commande est encore "En attente"
        if ($commande->getEtat() !== 'En attente') {
            $this->addFlash('warning', 'Seules les commandes en attente peuvent être supprimées.');
            return $this->redirectToRoute('app_orders');
        }

        // Supprimer la commande de la base
        $entityManager->remove($commande);
        $entityManager->flush();

        $this->addFlash('success', 'La commande a été supprimée avec succès.');

        return $this->redirectToRoute('app_orders');
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

            foreach ($selectedProducts as $product) {
                $id = $product->getId();
                $qty = $quantities[$id] ?? 0;

                if ($product->getQuantité() < $qty) {
                    $this->addFlash('error', "Stock insuffisant pour : " . $product->getLabel());
                    return $this->redirectToRoute('app_cart');
                }

                $product->setQuantité($product->getQuantité() - $qty);
                $total += $product->getPrix() * $qty;

                // Retirer du panier
                $user->getPanier()->removeProduct($product);
                unset($quantities[$id]);
            }

            $commande->setUser($user);
            $commande->setEtat('En attente');
            $commande->setPrixTotal($total);
            $em->persist($commande);
            $em->flush();

            $session->set('user_cart_quantities', $quantities); // Mise à jour partielle du panier
            $this->addFlash('success', 'Commande validée avec succès.');

            return $this->redirectToRoute('app_orders');
        }

        return $this->render('commande/validation.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Route('/admin/{id<\d+>}', name: 'admin.commandes.detail')]
    public function adminDetail(Commande $commande = null): Response
    {
        if (!$commande) {
            $this->addFlash('error', "La commande n'existe pas");
            return $this->redirectToRoute('admin.commandes.list');
        }
        return $this->render('commande/adminDetail.html.twig', [
            'commande' => $commande
        ]);
    }



    #[Route('/{id<\d+>}', name: 'commandes.detail')]
    public function detail(Commande $commande = null): Response
    {
        if (!$commande) {
            $this->addFlash('error', "La commande n'existe pas");
            return $this->redirectToRoute('commande.list');
        }
        return $this->render('commande/detail.html.twig', [
            'commande' => $commande
        ]);
    }
}

