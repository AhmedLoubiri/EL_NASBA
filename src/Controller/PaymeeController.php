<?php
// src/Controller/PaymentController.php
namespace App\Controller;

use App\Service\PaymeeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $backUrl = $this->generateUrl('payment_success', [], 0);

        $paymentUrl = $this->paymee->createPayment($amount, $note, $backUrl);

        if ($paymentUrl) {
            // Redirection vers la page de paiement Paymee
            return $this->redirect($paymentUrl);
        }

        // En cas d'erreur, redirige vers une page d’échec personnalisée
        return $this->redirectToRoute('payment_failed');
    }


    #[Route('/paiement/success', name: 'payment_success')]
    public function success()
    {
        return $this->render('paiment/success.html.twig');
    }
    #[Route('/paiement/echec', name: 'payment_failed')]
    public function failed()
    {
        return $this->render('paiment/failed.html.twig');
    }

}
