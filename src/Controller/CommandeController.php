<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeForm;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commande')]
final class CommandeController extends AbstractController
{
    //crud admin:
    #[Route('/admin', name: 'admin_commandes')]
    public function adminIndex(ManagerRegistry $doctrine): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(Commande::class);
        $commandes = $repository->findBy([], ['prix_total' => 'DESC', 'date_commande' => 'DESC']);
        return $this->render('commande/admin_index.html.twig', [
            'commandes' => $commandes,
            'controller_name' => 'CommandeController',
        ]);
    }
    #[Route('/add', name: 'add_commandes')]
    public function addCommande(\Symfony\Component\HttpFoundation\Request $request,ManagerRegistry $doctrine): Response
    {
        $commande = new Commande();
        $form = $this->createForm(CommandeType::class, $commande);
        $form->remove('createdAt');
        $form->remove('updatedAt');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager = $doctrine->getManager();
            $manager->persist($commande);
            $manager->flush();
            $this->addFlash('success', $commande->getId() . " a été ajouté avec succès");

            return $this->redirectToRoute('admin_commandes');
        } else {
            return $this->render('commande/new.html.twig', [
                'form' => $form->createView(),
            ]);
        }
    }


    #[Route('/admin/delete/{id}', name: 'commande_admin_delete', methods: ['POST'])]
    public function adminDelete(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete' . $commande->getId(), $request->request->get('_token'))) {
            $em->remove($commande);
            $em->flush();
        }

        return $this->redirectToRoute('admin_commandes');
    }
    //user:
    #[Route('/mes-commandes', name: 'app_orders')]
    public function userIndex(CommandeRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $commandes = $repo->findBy(['user' => $user], ['date_commande' => 'DESC']);

        $tz = new \DateTimeZone('Africa/Tunis');
        $now = new \DateTimeImmutable('now', $tz);
        $minus10Minutes = $now->modify('-10 minutes');
        $minus24Hours = $now->modify('-24 hours');

        foreach ($commandes as $commande) {
            $etat = $commande->getEtat();
            $dateCommande = $commande->getDateCommande();

            // Normalize the timezone of dateCommande
            if ($dateCommande instanceof \DateTimeInterface) {
                $dateCommande = (clone $dateCommande)->setTimezone($tz);
            }

            if ($etat === 'En attente' && $dateCommande < $minus10Minutes) {
                $commande->setEtat('En cours');
            }

            if ($etat === 'En cours' && $dateCommande < $minus24Hours) {
                $commande->setEtat('Expédiée');
            }
        }

        $em->flush();

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



    #[Route('/delete/{id}', name: 'commande_user_delete', methods: ['POST'])]
    public function delete(Commande $commande, Request $request, ManagerRegistry $doctrine ): Response
    {
        $user = $this->getUser();
        if ($commande->getUser() !== $user || $commande->getStatut() === 'expédiée') {
            throw $this->createAccessDeniedException('Impossible de supprimer cette commande.Commande déjà expédiée!');
        }
        $manager = $doctrine->getManager();
        if ($this->isCsrfTokenValid('delete' . $commande->getId(), $request->request->get('_token'))) {
            $manager->remove($commande);
            $manager->flush();
        }

        return $this->redirectToRoute('user_commandes');
    }
    #[Route('/commande/edit/{id}', name: 'commande_user_edit')]
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
    }
    #[Route('/commande/annuler/{id}', name: 'commande_cancel', methods: ['GET'])]
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

