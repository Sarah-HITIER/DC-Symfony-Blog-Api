<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Category;
use App\Service\TokenValidator;
use App\Service\UserValidator;
use App\Service\Validator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CategoryController extends AbstractController
{
    #[Route('/categories', name: 'app_category', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $limit = $request->get('limit') ?? 3;
        $categories = $em->getRepository(Category::class)->findLastItems($limit);
        return new JsonResponse($categories, 200);
    }

    #[Route('/category/{id}', name: 'app_category_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $category = $em->getRepository(Category::class)->findOneById($id);
        if ($category === null) {
            return new JsonResponse('Catégorie introuvable', 404);
        }

        $articles = $em->getRepository(Article::class)->findByCategory($category['id']);
        return new JsonResponse(["category" => $category, "articles" => $articles], 200);
    }

    #[Route('/category', name: 'app_category_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        TokenValidator $tokenValidator,
        UserValidator $userValidator,
        Validator $validator
    ): Response {
        // Check if token is valid
        $headers = $request->headers->all(); // On récupère les infos envoyées en header
        $tokenResponse = $tokenValidator->validateToken($headers);
        if ($tokenResponse['status'] !== 200) {
            return new JsonResponse($tokenResponse['message'], $tokenResponse['status']);
        }

        // Check if user is admin
        $isAdmin = $userValidator->isAdmin($tokenResponse['decoded']);
        if (!$isAdmin) {
            return new JsonResponse('Accès refusé', 403);
        }

        // Check if all parameters are provided
        $missingParameters = $validator->checkRequiredParameters($request, ['name']);
        if (count($missingParameters) > 0) {
            return new JsonResponse('Paramètres manquants : ' . implode(", ", $missingParameters), 400);
        }

        $category = new Category();
        $category->setTitle($request->get('name'));

        // Check if category is valid
        $isValid = $validator->isValid($category);
        if ($isValid !== true) {
            return new JsonResponse($isValid, 400);
        }

        $em->persist($category);
        $em->flush();

        return new JsonResponse('Catégorie créée', 201);
    }

    #[Route('/category/{id}', name: 'app_category_update', methods: ['PATCH'])]
    public function update(
        Request $request,
        TokenValidator $tokenValidator,
        UserValidator $userValidator,
        Validator $validator,
        EntityManagerInterface $em,
        Category $category = null
    ): Response {
        if ($category === null) {
            return new JsonResponse('Catégorie introuvable', 404);
        }

        // Check if token is valid
        $headers = $request->headers->all(); // On récupère les infos envoyées en header
        $tokenResponse = $tokenValidator->validateToken($headers);
        if ($tokenResponse['status'] !== 200) {
            return new JsonResponse($tokenResponse['message'], $tokenResponse['status']);
        }

        // Check if user is admin
        $isAdmin = $userValidator->isAdmin($tokenResponse['decoded']);
        if (!$isAdmin) {
            return new JsonResponse('Accès refusé', 403);
        }

        // On récupère et vérifie les infos envoyées en body
        $params = 0;
        if ($request->get('name') != null) {
            $params++;
            $category->setTitle($request->get('name'));
        }

        if ($params > 0) {
            // Check if category is valid
            $isValid = $validator->isValid($category);
            if ($isValid !== true) {
                return new JsonResponse($isValid, 400);
            }

            $em->persist($category);
            $em->flush();
        } else {
            return new JsonResponse('Aucune donnée reçue', 200);
        }
        return new JsonResponse('Catégorie modifiée', 200);
    }

    #[Route('/category/{id}', name: 'app_category_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        TokenValidator $tokenValidator,
        UserValidator $userValidator,
        EntityManagerInterface $em,
        Category $category = null
    ): Response {
        if ($category == null) {
            return new JsonResponse('Catégorie introuvable', 404);
        }

        // Check if token is valid
        $headers = $request->headers->all(); // On récupère les infos envoyées en header
        $tokenResponse = $tokenValidator->validateToken($headers);
        if ($tokenResponse['status'] !== 200) {
            return new JsonResponse($tokenResponse['message'], $tokenResponse['status']);
        }

        // Check if user is admin
        $isAdmin = $userValidator->isAdmin($tokenResponse['decoded']);
        if (!$isAdmin) {
            return new JsonResponse('Accès refusé', 403);
        }

        $em->remove($category);
        $em->flush();

        return new JsonResponse('Catégorie supprimée', 204);
    }
}
