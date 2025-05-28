<?php

namespace App\Controller;

use App\Entity\Season;
use App\Repository\SeasonRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[Route('/season')]
final class SeasonController extends AbstractController
{
    private SeasonRepository $repository;
    private EntityManager $entityManager;

    public function __construct(private ManagerRegistry $doctrine)
    {
        $this->entityManager = $doctrine->getManager();
        $this->repository = $this->entityManager->getRepository(Season::class);
    }

    #[Route('/list', name: 'list_season')]
    public function list(): Response
    {
        $seasons = $this->repository->findAll();
        return $this->render('season/index.html.twig', [
            'seasons' => $seasons,
        ]);
    }
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/add/{name}/{dateDebut}/{dateFin}', name: 'add_season')]
    public function addSeason(
        $name,
        $dateDebut,
        $dateFin
    ): Response {
        $season = new Season();
        $season->setName($name);

        try {
            $season->setDateDebut(new \DateTime($dateDebut));
            $season->setDateFin(new \DateTime($dateFin));
        } catch (\Exception $e) {
            return new Response('Please check that the format is: YYYY-MM-DD.', 400);
        }

        $this->entityManager->persist($season);
        $this->entityManager->flush();

        return $this->redirectToRoute('list_season');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/delete/{id}', name: 'delete_person')]
    public function delete(Season $season = null): Response
    {
        if (!$season) {
            throw $this->createNotFoundException('Season not found');
        }
        $this->entityManager->remove($season);
        $this->entityManager->flush();
        return $this->redirectToRoute('list_season');
    }

}
