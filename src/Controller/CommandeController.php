<?php

namespace App\Controller;

use App\Entity\Personne;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommandeController extends AbstractController
{
    #[Route('/commande', name: 'app_orders')]
    public function index(): Response
    {
        return $this->render('commande/index.html.twig', [
            'controller_name' => 'CommandeController',
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
            'commandes' => $commande
        ]);
    }
}
