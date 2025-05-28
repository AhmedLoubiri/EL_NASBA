<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Category;
use App\Entity\Season;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    public function list(Request $request): Response
    {
        $id = $request->query->get('id');
        if ($id !== null) {
            $product = $this->repository->find($id);
            if (!$product) {
                throw $this->createNotFoundException('Product not found');
            }
            return $this->render(
                'product/show.html.twig', [
                    'product' => $product,
                ]
            );
        }
        $products = $this->repository->findAll();
        return $this->render(
            'product/index.html.twig', [
                'products' => $products,
            ]
        );
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

    // NEW METHODS FOR FRONTEND - ADD THESE TO YOUR CONTROLLER
    #[Route('s', name: 'app_products')]
    public function products(Request $request): Response
    {
        $queryBuilder = $this->repository->createQueryBuilder('p');

        // Filter by categories
        if ($request->query->get('categories')) {
            $categoryIds = explode(',', $request->query->get('categories'));
            $queryBuilder->join('p.categories', 'c')
                ->andWhere('c.id IN (:categories)')
                ->setParameter('categories', $categoryIds);
        }

        // Filter by price range
        if ($request->query->get('price_min')) {
            $queryBuilder->andWhere('p.prix >= :price_min')
                ->setParameter('price_min', $request->query->get('price_min'));
        }

        if ($request->query->get('price_max')) {
            $queryBuilder->andWhere('p.prix <= :price_max')
                ->setParameter('price_max', $request->query->get('price_max'));
        }

        // Sorting
        $sort = $request->query->get('sort', 'label');
        switch ($sort) {
            case 'price_asc':
                $queryBuilder->orderBy('p.prix', 'ASC');
                break;
            case 'price_desc':
                $queryBuilder->orderBy('p.prix', 'DESC');
                break;
            case 'newest':
                $queryBuilder->orderBy('p.id', 'DESC');
                break;
            default:
                $queryBuilder->orderBy('p.label', 'ASC');
        }

        $products = $queryBuilder->getQuery()->getResult();
        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        $seasons = $this->entityManager->getRepository(Season::class)->findAll();

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'seasons' => $seasons,
        ]);
    }

    #[Route('/show/{id}', name: 'app_product_show')]
    public function show(Product $product): Response
    {
        // Get related products (simple implementation)
        $relatedProducts = $this->repository->findBy([], ['id' => 'DESC'], 4);

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'related_products' => $relatedProducts,
        ]);
    }

    #[Route('/category/{id}', name: 'app_products_by_category')]
    public function byCategory(Category $category): Response
    {
        $queryBuilder = $this->repository->createQueryBuilder('p')
            ->join('p.categories', 'c')
            ->where('c.id = :category_id')
            ->setParameter('category_id', $category->getId())
            ->orderBy('p.label', 'ASC');

        $products = $queryBuilder->getQuery()->getResult();
        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        $seasons = $this->entityManager->getRepository(Season::class)->findAll();

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'seasons' => $seasons,
            'current_category' => $category,
        ]);
    }
}
