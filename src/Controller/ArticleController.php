<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\User;
use App\Service\TokenValidator;
use App\Service\UserValidator;
use App\Service\Validator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/article')]
class ArticleController extends AbstractController
{
    #[Route('/{id}', name: 'app_article_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $article = $em->getRepository(Article::class)->findOneById($id);
        if ($article === null) {
            return new JsonResponse('Article introuvable', 404);
        }

        $comments = $em->getRepository(Comment::class)->findByArticle($article['id']);
        return new JsonResponse(['article' => $article, 'comments' => $comments], 200);
    }

    #[Route('', name: 'app_article_create', methods: ['POST'])]
    public function create(
        Request $request,
        TokenValidator $tokenValidator,
        UserValidator $userValidator,
        Validator $validator,
        EntityManagerInterface $em
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
        $missingParameters = $validator->checkRequiredParameters($request, ['title', 'content', 'category']);
        if (count($missingParameters) > 0) {
            return new JsonResponse('Paramètres manquants : ' . implode(", ", $missingParameters), 400);
        }

        // Check if category exists
        $category = $em->getRepository(Category::class)->findOneBy(['id' => $request->get('category')]);
        if ($category == null) {
            return new JsonResponse('Catégorie introuvable', 404);
        }

        $user = $em->getRepository(User::class)->findOneBy(['id' => $tokenResponse['decoded']->user_id]);

        $article = new Article();
        $article
            ->setTitle($request->get('title'))
            ->setContent($request->get('content'))
            ->setCategory($category)
            ->setAuthor($user);

        // Check if category is valid
        $isValid = $validator->isValid($article);
        if ($isValid !== true) {
            return new JsonResponse($isValid, 400);
        }

        $em->persist($article);
        $em->flush();

        return new JsonResponse('Article créé', 201);
    }

    #[Route('/{id}', name: 'app_article_update', methods: ['PATCH'])]
    public function update(
        Request $request,
        TokenValidator $tokenValidator,
        UserValidator $userValidator,
        Validator $validator,
        EntityManagerInterface $em,
        Article $article = null
    ) {
        if ($article === null) {
            return new JsonResponse('Article introuvable', 404);
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
        if ($request->get('title') != null) {
            $params++;
            $article->setTitle($request->get('title'));
        }

        if ($request->get('content') != null) {
            $params++;
            $article->setContent($request->get('content'));
        }

        if ($request->get('category') != null) {
            $category = $em->getRepository(Category::class)->findOneBy(['id' => $request->get('category')]);
            if ($category == null) {
                return new JsonResponse('Catégorie introuvable', 404);
            }
            $params++;
            $article->setCategory($category);
        }

        if ($request->get('state') != null) {
            $params++;
            $article->setState($request->get('state'));
            if ($article->isState()) {
                $article->setPublicationDate(new \DateTime());
            } else {
                $article->setPublicationDate(null);
            }
        }

        if ($params > 0) {
            $isValid = $validator->isValid($article);
            if ($isValid !== true) {
                return new JsonResponse($isValid, 400);
            }

            $em->persist($article);
            $em->flush();
        } else {
            return new JsonResponse('Aucune donnée reçue', 200);
        }
        return new JsonResponse('Article modifié', 200);
    }

    #[Route('/{id}', name: 'app_article_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        TokenValidator $tokenValidator,
        UserValidator $userValidator,
        EntityManagerInterface $em,
        Article $article = null
    ) {
        if ($article === null) {
            return new JsonResponse('Article introuvable', 404);
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

        $em->remove($article);
        $em->flush();

        return new JsonResponse('Article supprimé', 204);
    }
}
