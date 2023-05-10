<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\User;
use App\Service\TokenValidator;
use App\Service\UserValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommentController extends AbstractController
{
    #[Route('/comment/{article_id}', name: 'app_comment_create', methods: ['POST'])]
    public function create(
        int $article_id,
        TokenValidator $tokenValidator,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        // Check if token is valid
        $headers = $request->headers->all(); // On récupère les infos envoyées en header
        $tokenResponse = $tokenValidator->validateToken($headers);
        if ($tokenResponse['status'] !== 200) {
            return new JsonResponse($tokenResponse['message'], $tokenResponse['status']);
        }

        if (!$tokenResponse['decoded']->user_id) {
            return new JsonResponse('Utilisateur introuvable', 404);
        }

        $user = $em->getRepository(User::class)->findOneBy(['id' => $tokenResponse['decoded']->user_id]);

        $article = $em->getRepository(Article::class)->findOneBy(['id' => $article_id]);
        if ($article === null) {
            return new JsonResponse('Article introuvable', 404);
        }

        $comment = new Comment();
        $comment
            ->setComment($request->get('comment'))
            ->setArticle($article)
            ->setAuthor($user);

        $em->persist($comment);
        $em->flush();
        return new Response('Commentaire créé');
    }

    #[Route('/comment/{id}', name: 'app_comment_moderate', methods: ['PATCH'])]
    public function moderate(
        Request $request,
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
            return new JsonResponse('Access denied', 403);
        }

        $comment->setState($request->get('state'));
        $em->persist($comment);
        $em->flush();
        return new JsonResponse('Success', 201);
    }
}
