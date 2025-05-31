<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Entity\Category;
use App\Entity\Season;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/product', name: 'app_product_')]
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
    public function add($label, $prix): Response
    {
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
    ): Response {
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

    #[Route('/{id}/delete', name: 'admin_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($product);
            $this->entityManager->flush();
            $this->addFlash('success', 'Produit supprimé avec succès!');
        }

        return $this->redirectToRoute('app_product_app_product_admin');
    }

    #[Route('s', name: 'app_products')]
    public function products(Request $request): Response
    {
        $queryBuilder = $this->repository->createQueryBuilder('p');
        $currentSeason = null;
        $filterDate = null;

        if ($request->query->get('filter_date')) {
            try {
                $filterDate = new \DateTime($request->query->get('filter_date'));
                $currentSeason = $this->getSeasonByDate($filterDate);

                if ($currentSeason) {
                    $queryBuilder->join('p.relation', 's')
                        ->andWhere('s.id = :season_id')
                        ->setParameter('season_id', $currentSeason->getId());
                } else {
                    $queryBuilder->andWhere('1 = 0'); // Condition impossible pour retourner 0 résultats
                }
            } catch (\Exception $e) {
                $queryBuilder->andWhere('1 = 0');
            }
        }

        $categoryIds = $request->query->all('categories');
        if (!empty($categoryIds)) {
            $queryBuilder->join('p.categories', 'c')
                ->andWhere('c.id IN (:categories)')
                ->setParameter('categories', $categoryIds);
        }

        $seasonIds = $request->query->all('seasons'); // pas explode() ici
        if (!empty($seasonIds)) {
            $queryBuilder->join('p.relation', 'season')
                ->andWhere('season.id IN (:seasons)')
                ->setParameter('seasons', $seasonIds);
        }

        if ($request->query->get('price_min')) {
            $queryBuilder->andWhere('p.prix >= :price_min')
                ->setParameter('price_min', $request->query->get('price_min'));
        }

        if ($request->query->get('price_max')) {
            $queryBuilder->andWhere('p.prix <= :price_max')
                ->setParameter('price_max', $request->query->get('price_max'));
        }

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

        return $this->render(
            'product/index.html.twig', [
                'products' => $products,
                'categories' => $categories,
                'selected_categories' => $categoryIds,
                'seasons' => $seasons,
                'current_season' => $currentSeason,
                'filter_date' => $request->query->get('filter_date'),
                'total_products' => count($products),
            ]
        );
    }

    #[Route('/admin', name: 'app_product_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        $products = $this->repository->findAll();

        return $this->render('product/admin.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/new', name: 'admin_new')]
    public function new(Request $request): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($product);
            $this->entityManager->flush();

            $this->addFlash('success', 'Produit créé avec succès!');

            return $this->redirectToRoute('app_product_app_product_admin');
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Product $product): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Produit modifié avec succès!');

            return $this->redirectToRoute('app_product_app_product_admin');
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }



    #[Route('/season/{id}', name: 'products_by_season')]
    public function productsBySeason(Season $season): Response
    {
        $queryBuilder = $this->repository->createQueryBuilder('p')
            ->join('p.relation', 's')
            ->where('s.id = :season_id')
            ->setParameter('season_id', $season->getId())
            ->orderBy('p.label', 'ASC');

        $products = $queryBuilder->getQuery()->getResult();
        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        $seasons = $this->entityManager->getRepository(Season::class)->findAll();

        return $this->render(
            'product/index.html.twig', [
                'products' => $products,
                'categories' => $categories,
                'seasons' => $seasons,
                'current_season' => $season,
                'selected_season' => $season,
                'total_products' => count($products),
            ]
        );
    }

    #[Route('/show/{id}', name: 'show_product')]
    public function show(Product $product): Response
    {
        // Get related products from the same categories
        $relatedProducts = [];

        // Check if product has categories
        if ($product->getCategories()->count() > 0) {
            // Get category IDs
            $categoryIds = [];
            foreach ($product->getCategories() as $category) {
                $categoryIds[] = $category->getId();
            }

            // Find products in the same categories, excluding current product
            $queryBuilder = $this->repository->createQueryBuilder('p')
                ->join('p.categories', 'c')
                ->where('c.id IN (:categoryIds)')
                ->andWhere('p.id != :productId')
                ->setParameter('categoryIds', $categoryIds)
                ->setParameter('productId', $product->getId())
                ->setMaxResults(4)
                ->orderBy('p.label', 'ASC');

            $relatedProducts = $queryBuilder->getQuery()->getResult();
        }

        return $this->render(
            'product/show.html.twig', [
                'product' => $product,
                'related_products' => $relatedProducts,
            ]
        );
    }

    #[Route('/category/{id}', name: 'list_product_by_category')]
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

        return $this->render(
            'product/index.html.twig', [
                'products' => $products,
                'categories' => $categories,
                'seasons' => $seasons,
                'current_category' => $category,
                'total_products' => count($products),
            ]
        );
    }

    /**
     * Trouve la saison qui correspond à une date donnée
     * La date doit être entre date_debut et date_fin de la saison
     */
    private function getSeasonByDate(\DateTime $date): ?Season
    {
        $seasonRepository = $this->entityManager->getRepository(Season::class);

        $queryBuilder = $seasonRepository->createQueryBuilder('s')
            ->where('s.date_debut <= :date')
            ->andWhere('s.date_fin >= :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setMaxResults(1);

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * Vérifie si une saison est actuellement active
     */
    private function isSeasonActive(Season $season): bool
    {
        $today = new \DateTime();
        return $season->getDateDebut() <= $today && $season->getDateFin() >= $today;
    }
}
