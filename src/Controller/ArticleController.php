<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\CategorieArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class ArticleController extends AbstractController
{
    #[Route('/app/v1/articles', name: 'app_article_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $code         = trim((string) $request->request->get('code', ''));
        $tva          = $request->request->get('tva');
        $prixUnitaire = $request->request->get('prixUnitaire');
        $categorieId  = (int) $request->request->get('categorieId');

        if ($code === '' || $tva === null || $prixUnitaire === null || !$categorieId) {
            return new JsonResponse(['ok' => false, 'message' => 'Champs requis manquants'], 400);
        }

        // unicité du code
        if ($em->getRepository(Article::class)->findOneBy(['code' => $code])) {
            return new JsonResponse(['ok' => false, 'message' => 'Code article déjà utilisé'], 409);
        }

        $categorie = $em->getRepository(CategorieArticle::class)->find($categorieId);
        if (!$categorie) {
            return new JsonResponse(['ok' => false, 'message' => 'Catégorie introuvable'], 404);
        }

        $article = new Article();
        $article->setCode($code)
            ->setTva((float) $tva)
            ->setPrixUnitaire((float) $prixUnitaire)
            ->setCategorie($categorie);

        $em->persist($article);
        $em->flush();

        return new JsonResponse([
            'ok'   => true,
            'id'   => $article->getId(),
            'code' => $article->getCode(),
            'categorie' => $categorie->getLabel()
        ], 201);
    }
}
