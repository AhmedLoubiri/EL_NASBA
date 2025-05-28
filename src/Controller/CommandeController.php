<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    #[Route('admin/add', name: 'add_commandes')]
    public function addCommande(\Symfony\Component\HttpFoundation\Request $request,ManagerRegistry $doctrine): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $commande = new Commande();
        $form = $this->createForm(CommandeType::class, $commande);
        $form->remove('createdAt');
        $form->remove('updatedAt');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager = $doctrine->getManager();
            $manager->persist($commande);
            $manager->flush();
            $this->addFlash('success',$commande->getId() ." a été ajouté avec succès");

            return $this->redirectToRoute('admin_commandes');
        }
        else {
            return $this->render('commande/new.html.twig', [
                'form' => $form->createView(),
            ]);
        }

    }
    #[Route('/admin/edit/{id}', name: 'commande_admin_edit')]
    public function adminEditCommande(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('admin_commandes');
        }

        return $this->render('commande/edit.html.twig', ['form' => $form->createView()]);
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
    #[Route('/mes-commandes', name: 'user_commandes')]
    public function userIndex(CommandeRepository $repo): Response
    {
        $user = $this->getUser();
        $commandes = $repo->findBy(['user' => $user], ['dateCommande' => 'DESC']);
        return $this->render('commande/user_index.html.twig', ['commandes' => $commandes]);
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

    #[Route('/{id<\d+>}', name: 'commandes.detail')]
    public function detail(Commande $commande = null): Response
    {
        if (!$commande) {
            $this->addFlash('error', "La commande n'existe pas");
            return $this->redirectToRoute('commande.list');
        }
        return $this->render('commande/detail.html.twig', [
            'commandes' => $commande
        ]);
    }
}
