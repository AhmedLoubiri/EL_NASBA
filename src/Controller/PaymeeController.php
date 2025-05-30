<?php
namespace App\Controller;

use App\Service\PaymeeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymeeController extends AbstractController
{
    private $paymee;

    public function __construct(PaymeeClient $paymee)
    {
        $this->paymee = $paymee;
    }

    #[Route('/paiement', name: 'app_payment')]
    public function paiement(): RedirectResponse
    {
        $amount = 10.0; // montant en dinars
        $note = "Commande test";

        // Générer l'URL absolue pour le retour
        $backUrl = $this->generateUrl('payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $paymentUrl = $this->paymee->createPayment($amount, $note, $backUrl);

        if ($paymentUrl) {
            // Redirection vers la page de paiement Paymee
            return $this->redirect($paymentUrl);
        }

        // En cas d'erreur, ajouter un message flash
        $this->addFlash('error', 'Impossible de créer le paiement. Veuillez réessayer.');
        return $this->redirectToRoute('payment_failed');
    }

    #[Route('/paiement/success', name: 'payment_success')]
    public function success(): Response
    {
        return $this->render('paiment/success.html.twig');
    }

    #[Route('/paiement/echec', name: 'payment_failed')]
    public function failed(): Response
    {
        return $this->render('paiment/failed.html.twig');
    }
}