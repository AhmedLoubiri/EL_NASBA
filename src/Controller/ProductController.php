<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Season;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/product')]
final class ProductController extends AbstractController
{
    private ProductRepository $repository;
    private EntityManager $entityManager;

    public function __construct(private ManagerRegistry $doctrine)
    {
        $this->entityManager = $doctrine->getManager();
        $this->repository = $this->entityManager->getRepository(Product::class);
    }

    #[Route('/list', name: 'list_product')]
    public function list(): Response
    {
        $products = $this->repository->findAll();
        return $this->render('product/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/add/{label}/{prix}', name: 'add_product')]
    public function add($label, $prix): Response{
        $product = new Product();
        $product->setLabel($label);
        $product->setPrix($prix);
        $this->entityManager->persist($product);
        $this->entityManager->flush();
        return $this->redirectToRoute('list_product');
    }

    #[Route('/assignSeason/{productId}/{seasonId}', name: 'assign_season')]
    public function assignSeason(int $productId, int $seasonId): Response
    {
        $product = $this->entityManager->getRepository(Product::class)->find($productId);
        $season = $this->entityManager->getRepository(Season::class)->find($seasonId);

        if (!$product || !$season) {
            throw $this->createNotFoundException('Product or Season not found');
        }

        $product->addRelation($season);
        $this->entityManager->flush();

        return $this->redirectToRoute('list_product');
    }

    # a method to add a product with seasons
    #[Route('/add/{label}/{prix}/{seasons}', name: 'add_product_with_seasons')]
    public function addWithSeasons(
        $label,
        $prix,
        $seasons
    ): Response{
        $product = new Product();
        $product->setLabel($label);
        $product->setPrix($prix);

        $seasonIds = explode(',', $seasons);

        foreach ($seasonIds as $seasonId) {
            $season = $this->entityManager->getRepository(Season::class)->find($seasonId);
            if ($season) {
                $product->addRelation($season);
            }
        }
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $this->redirectToRoute('list_product');
    }

    #[Route('/delete/{id}', name: 'delete_product')]
    public function delete(Product $product = null): Response
    {
        if (!$product) {
            throw $this->createNotFoundException('Product not found');
        }
        $this->entityManager->remove($product);
        $this->entityManager->flush();
        return $this->redirectToRoute('list_product');
    }
}
