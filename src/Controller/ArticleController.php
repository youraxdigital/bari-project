<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\CategorieArticle;
use App\Repository\ArticleRepository;
use App\Repository\CategorieArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use function Symfony\Component\String\s;

final class ArticleController extends AbstractController
{
    #[Route('/app/v1/articles', name: 'app_articles_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Read form fields (x-www-form-urlencoded)
        $code         = trim((string)$request->request->get('code', ''));
        $name         = trim((string)$request->request->get('name', ''));
        $tva          = $request->request->get('tva', null);
        $prixUnitaire = $request->request->get('prixUnitaire', null);
        $categorieId  = $request->request->get('categorieId');

        // Basic validation
        $errors = [];
        if ($code === '')         { $errors['code'] = 'Code requis.'; }
        if ($name === '')         { $errors['name'] = 'Nom requis.'; }
        if ($tva === null || $tva === '') { $errors['tva'] = 'TVA requise.'; }
        if ($prixUnitaire === null || $prixUnitaire === '') { $errors['prixUnitaire'] = 'Prix unitaire requis.'; }
        if (empty($categorieId))  { $errors['categorieId'] = 'Catégorie requise.'; }

        if (!empty($errors)) {
            return new JsonResponse(['message' => 'Validation échouée', 'errors' => $errors], 400);
        }

        // Unicity check (optional but recommended)
        $exists = $em->getRepository(Article::class)->findOneBy(['code' => $code]);
        if ($exists) {
            return new JsonResponse(['message' => 'Un article avec ce code existe déjà.'], 409);
        }

        // Fetch category
        /** @var CategorieArticle|null $categorie */
        $categorie = $em->getRepository(CategorieArticle::class)->find($categorieId);
        if (!$categorie) {
            return new JsonResponse(['message' => 'Catégorie introuvable.'], 404);
        }

        // Create & persist
        $article = new Article();
        $article->setCode($code);
        $article->setName($name); // or setLibelle() if your entity uses a different field
        $article->setTva((float)$tva);
        $article->setPrixUnitaire((float)$prixUnitaire);
        $article->setCategorie($categorie);

        $em->persist($article);
        $em->flush();

        // Return minimal info (DataTables will reload the page)
        return new JsonResponse([
            'message' => 'Article créé avec succès.',
            'id' => $article->getId(),
        ], 201);
    }

    #[Route('/app/v1/articles/manage', name: 'app_articles_manage', methods: ['GET'])]
    public function manageArticles(CategorieArticleRepository $catRepo): Response
    {
        return $this->render('manager/articles.html.twig', [
            'categories' => $catRepo->findBy([], ['label' => 'ASC'])
        ]);
    }

    #[Route('/app/v1/articles/list', name: 'app_articles_list', methods: ['GET'])]
    public function listArticles(Request $request, ArticleRepository $repo): JsonResponse
    {
        $params = $request->query->all();

        // ask the repo to build a filtered, paginated result
        $result = $repo->datatable($params); // ['items'=>[], 'recordsTotal'=>int, 'recordsFiltered'=>int]

        $data = array_map(function (Article $a) {
            return [
                'id'           => $a->getId(),
                'code'         => $a->getCode(),
                'name'         => $a->getName(),
                'tva'          => $a->getTva(),
                'prixUnitaire' => $a->getPrixUnitaire(),
                'categorie'    => $a->getCategorie()?->getLabel(),
            ];
        }, $result['items']);

        return new JsonResponse([
            'draw'            => (int)($params['draw'] ?? 0),
            'recordsTotal'    => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data'            => $data,
        ]);
    }

    #[Route('/app/v1/articles/datatable', name: 'app_articles_datatable', methods: ['GET'])]
    public function articlesDatatable(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $draw   = (int) $request->query->get('draw', 1);
        $start  = (int) $request->query->get('start', 0);
        $length = (int) $request->query->get('length', 10);

        $code      = trim((string) $request->query->get('code', ''));
        $name      = trim((string) $request->query->get('name', ''));
        $tva       = trim((string) $request->query->get('tva', ''));
        $prix      = trim((string) $request->query->get('prix', ''));
        $categorie = trim((string) $request->query->get('categorie', ''));

        // Base query with join on category
        $qb = $em->createQueryBuilder()
            ->select('a', 'c')
            ->from(Article::class, 'a')
            ->leftJoin('a.categorie', 'c');

        // Filters
        if ($code !== '') {
            $qb->andWhere('a.code LIKE :code')->setParameter('code', '%' . $code . '%');
        }
        if ($name !== '') {
            $qb->andWhere('a.name LIKE :name')->setParameter('name', '%' . $name . '%');
        }

        if ($categorie !== '') {
            $qb->andWhere('c.label LIKE :cat')->setParameter('cat', '%' . $categorie . '%');
        }
        if ($tva !== '') {
            $this->applyNumericFilter($qb, 'a.tva', $tva, 'tva');
        }
        if ($prix !== '') {
            $this->applyNumericFilter($qb, 'a.prixUnitaire', $prix, 'prix');
        }

        // (Optional) ordering from DataTables
        // if you enable 'ordering' in JS, map visible columns to fields here.
        // $qb->orderBy('a.id', 'DESC');

        // Total count (no filters)
        $qbTotal = $em->createQueryBuilder()
            ->select('COUNT(a2.id)')
            ->from(Article::class, 'a2');
        $recordsTotal = (int) $qbTotal->getQuery()->getSingleScalarResult();

        // Filtered count
        $qbFiltered = clone $qb;
        $recordsFiltered = (int) $qbFiltered->select('COUNT(a.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        // Page data
        $qb->setFirstResult($start)->setMaxResults($length);
        /** @var Article[] $rows */
        $rows = $qb->getQuery()->getResult();

        $data = array_map(function (Article $a) {
            return [
                'id'           => $a->getId(),
                'code'         => $a->getCode(),
                'name'         => $a->getName(),
                'tva'          => $a->getTva(),
                'prixUnitaire' => $a->getPrixUnitaire(),
                'categorie'    => $a->getCategorie()?->getLabel(),
            ];
        }, $rows);

        return new JsonResponse([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    /**
     * Supports "100", ">=100", "<=50", "<10", ">5", "10-50".
     */
    private function applyNumericFilter(\Doctrine\ORM\QueryBuilder $qb, string $field, string $raw, string $paramBase): void
    {
        $raw = str_replace(',', '.', trim($raw));

        // range "min-max"
        if (preg_match('/^\s*([+-]?\d+(?:\.\d+)?)\s*-\s*([+-]?\d+(?:\.\d+)?)\s*$/', $raw, $m)) {
            $min = (float)$m[1]; $max = (float)$m[2];
            $qb->andWhere("$field BETWEEN :{$paramBase}Min AND :{$paramBase}Max")
                ->setParameter("{$paramBase}Min", $min)
                ->setParameter("{$paramBase}Max", $max);
            return;
        }

        // operator form
        if (preg_match('/^(<=|>=|=|<|>)\s*([+-]?\d+(?:\.\d+)?)$/', $raw, $m)) {
            [$all, $op, $num] = $m;
            $qb->andWhere("$field $op :$paramBase")->setParameter($paramBase, (float)$num);
            return;
        }

        // plain number => equality
        if (is_numeric($raw)) {
            $qb->andWhere("$field = :$paramBase")->setParameter($paramBase, (float)$raw);
        }
    }

    #[Route('/app/v1/articles/update/{id}', name: 'app_articles_update', methods: ['POST'])]
    public function updateArticle(Request $request, Article $article, EntityManagerInterface $em): JsonResponse
    {
        $article->setCode($request->request->get('code'));
        $article->setName($request->request->get('name'));
        $article->setTva((float)$request->request->get('tva'));
        $article->setPrixUnitaire((float)$request->request->get('prixUnitaire'));
        // You might need to fetch and set the category entity here

        $em->flush();

        return new JsonResponse(['status' => 'ok']);
    }


}
