<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/category')]
class CategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository
    ) {}

    #[Route('/list', name: 'app_categories')]
    public function index(): Response
    {
        $categories = $this->categoryRepository->findAll();

        return $this->render('category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}', name: 'app_category_show', requirements: ['id' => '\d+'])]
    public function show(Category $category): Response
    {
        $products = $category->getProducts();

        return $this->render('category/show.html.twig', [
            'category' => $category,
            'products' => $products,
        ]);
    }

    #[Route('/admin', name: 'app_category_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(): Response
    {
        $categories = $this->categoryRepository->findAll();

        return $this->render('category/admin.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/admin/new', name: 'app_category_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->addFlash('success', 'Catégorie créée avec succès!');

            return $this->redirectToRoute('app_category_admin');
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/admin/{id}/edit', name: 'app_category_edit', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Category $category): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Catégorie modifiée avec succès!');

            return $this->redirectToRoute('app_category_admin');
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/admin/{id}/delete', name: 'app_category_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Category $category): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->request->get('_token'))) {
            $products = $category->getProducts();
            if (!$products) {
                $this->addFlash('error', 'Impossible de supprimer cette catégorie car elle contient des produits.');
            } else {
                $this->entityManager->remove($category);
                $this->entityManager->flush();
                $this->addFlash('success', 'Catégorie supprimée avec succès!');
            }
        }

        return $this->redirectToRoute('app_category_admin');
    }
}
