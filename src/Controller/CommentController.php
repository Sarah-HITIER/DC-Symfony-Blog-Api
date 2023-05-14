<?php

namespace App\Controller;

use App\Entity\Article;
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

#[Route('/comment')]
class CommentController extends AbstractController
{
    #[Route('/{article_id}', name: 'app_comment_create', methods: ['POST'])]
    public function create(
        int $article_id,
        Validator $validator,
        TokenValidator $tokenValidator,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        // Check if article exists
        $article = $em->getRepository(Article::class)->findOneBy(['id' => $article_id]);
        if ($article === null) {
            return new JsonResponse('Article introuvable', 404);
        }

        // Check if token is valid
        $headers = $request->headers->all(); // On récupère les infos envoyées en header
        $tokenResponse = $tokenValidator->validateToken($headers);
        if ($tokenResponse['status'] !== 200) {
            return new JsonResponse($tokenResponse['message'], $tokenResponse['status']);
        }

        // Check if token contains user
        $userId = $tokenResponse['decoded']->user_id;
        if (!$userId) {
            return new JsonResponse('Accès refusé', 403);
        }
        $user = $em->getRepository(User::class)->findOneBy(['id' => $userId]);

        // Check if all parameters are provided
        $missingParameters = $validator->checkRequiredParameters($request, ['comment']);
        if (count($missingParameters) > 0) {
            return new JsonResponse('Paramètres manquants : ' . implode(", ", $missingParameters), 400);
        }

        $comment = new Comment();
        $comment
            ->setComment($request->get('comment'))
            ->setArticle($article)
            ->setAuthor($user);

        // Check if comment is valid
        $isValid = $validator->isValid($comment);
        if ($isValid !== true) {
            return new JsonResponse($isValid, 400);
        }

        $em->persist($comment);
        $em->flush();
        return new JsonResponse('Commentaire créé', 201);
    }

    #[Route('/{id}', name: 'app_comment_moderate', methods: ['PATCH'])]
    public function moderate(
        Request $request,
        Validator $validator,
        TokenValidator $tokenValidator,
        UserValidator $userValidator,
        EntityManagerInterface $em,
        Comment $comment = null
    ) {
        if ($comment === null) {
            return new JsonResponse('Commentaire introuvable', 404);
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

        $params = 0;
        if ($request->get('state') != null) {
            $params++;
            $comment->setState($request->get('state'));
        }

        if ($params > 0) {
            $isValid = $validator->isValid($comment);
            if ($isValid !== true) {
                return new JsonResponse($isValid, 400);
            }

            $em->persist($comment);
            $em->flush();
        } else {
            return new JsonResponse('Aucune donnée reçue', 200);
        }
        return new JsonResponse('Commentaire modifié', 200);
    }
}
