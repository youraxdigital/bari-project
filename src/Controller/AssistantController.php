<?php

namespace App\Controller;

use App\Entity\CategorieClient;
use App\Repository\ArticleRepository;
use App\Repository\CategorieArticleRepository;
use App\Repository\CategorieClientRepository;
use App\Repository\DemandeRepository;
use App\Repository\StatusDemandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Client;
use App\Entity\Article;
use App\Entity\Demande;
use App\Entity\StatusDemande;
use Doctrine\ORM\EntityManagerInterface;

final class AssistantController extends AbstractController
{
    #[Route('/app/v1/assistant', name: 'app_assistant_index')]
    public function index(CategorieClientRepository $catClientRepo,
                          CategorieArticleRepository $catArticleRepo,
                          ArticleRepository $articleRepository,
                          StatusDemandeRepository $statusDemandeRepository): Response
    {

        return $this->render('assistant/index.html.twig', [
            'categories' => $catClientRepo->findBy([], ['label' => 'ASC']),            // pour le modal client
            'categoriesArticle' => $catArticleRepo->findBy([], ['label' => 'ASC']),    // pour le modal article
            'articles' => $articleRepository->findBy([], ['name' => 'ASC']),
            'statusDemandes' => $statusDemandeRepository->findBy([], ['status' => 'ASC']),
        ]);
    }


    #[Route('/app/v1/assistant/import', name: 'app_assistant_import_excel', methods: ['POST'])]
    public function importExcel(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $file = $request->files->get('excel_file');
        $expectedTotals = $request->request->all('type_totals'); // array associatif: ['OVIN' => 20, 'BOVIN' => 30]

        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], 400);
        }


        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // check sous totals
        $calculatedTotals = [];

        for ($i = 1; $i < count($rows); $i++) {
            [, , , , $type, $effectif] = $rows[$i];

            if (!$type || !$effectif) continue;

            if (!isset($calculatedTotals[$type])) {
                $calculatedTotals[$type] = 0;
            }
            $calculatedTotals[$type] += (int) $effectif;
        }

        // Comparaison des valeurs attendues vs calculées
        foreach ($expectedTotals as $type => $expected) {
            $expected = (int) $expected;
            $actual = (int) ($calculatedTotals[$type] ?? 0);

            if ($expected !== $actual) {
                return new JsonResponse([
                    'error' => "Le total du type '$type' est incorrect : attendu $expected, trouvé $actual."
                ], 400);
            }
        }


        // Status initial
        $status = $em->getRepository(StatusDemande::class)->findOneBy(['status' => StatusDemande::FACTURATION]);
        $categorieClient = $em->getRepository(CategorieClient::class)->findOneBy(['code' => 'BOUCHER']);

        // Skip header
        for ($i = 1; $i < count($rows); $i++) {
            [$code, $nom, $date, $ville, $type, $effectif] = $rows[$i];

            if ($code) {
                // Trouver ou créer le client
                $client = $em->getRepository(Client::class)->findOneBy(['code' => $code]);
                if (!$client) {
                    $client = new Client();
                    $client->setCode($code);
                    // Séparer le nom complet en prénom et nom
                    $nomParts = explode(' ', trim($nom), 2);
                    $prenom = $nomParts[0];
                    $nomDeFamille = $nomParts[1] ?? ''; // Si pas de 2ème partie

                    $client->setPrenom($prenom);
                    $client->setNom($nomDeFamille);
                    $client->setCategorie($categorieClient);
                    $em->persist($client);
                }

                // Trouver l'article par type
                /**
                 * @var Article $article
                 */
                $article = $em->getRepository(Article::class)->findOneBy(['name' => $type]);
                if (!$article) {
                    continue; // ignorer la ligne si aucun article trouvé
                }

                // Calcul du montant
                $quantite = (int)$effectif;
                $montant = $article->getPrixUnitaire() * $quantite;

                $demande = new Demande();
                $demande->setClient($client);
                $demande->setArticle($article);
                $demande->setDate(new \DateTime($date));
                $demande->setQuantite($quantite);
                $demande->setMontant($montant);
                $demande->setStatus($status);
                $demande->setDeleted(false);

                $em->persist($demande);
            }
            $em->flush();
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/app/v1/assistant/demandes', name: 'app_assistant_demandes_list', methods: ['GET'])]
    public function list(DemandeRepository $repo): JsonResponse
    {
        $demandes = $repo->findAll();

        $data = [];
        /**
         * @var Demande $d
         */
        foreach ($demandes as $d) {
            $data[] = [
                'code'     => $d->getClient()?->getCode(),
                'nom'      => $d->getClient()?->getNom() . ' ' . $d->getClient()?->getPrenom(),
                'date'     => $d->getDate()->format('d/m/Y'),
                'type'     => $d->getArticle()?->getCategorie()?->getLabel(),
                'effectif' => $d->getQuantite(),
                'prix'     => $d->getArticle()?->getPrixUnitaire(),
                'montant'  => $d->getMontant(),
                'flux'     => $d->getStatus()?->getStatus(),
                'id'       => $d->getId(),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/app/v1/assistant/demandes/datatable', name: 'app_assistant_demandes_datatable', methods: ['GET'])]
    public function datatable(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $draw   = (int) $request->query->get('draw', 1);
        $start  = (int) $request->query->get('start', 0);
        $length = (int) $request->query->get('length', 10);

        $code = trim((string) $request->query->get('code', ''));
        $nom  = trim((string) $request->query->get('nom', ''));
        $article  = trim((string) $request->query->get('article', ''));
        $flux  = trim((string) $request->query->get('flux', ''));
        $dateRange = trim((string) $request->query->get('date', ''));

        $dateFrom = null;
        $dateTo = null;

        if ($dateRange !== '' && str_contains($dateRange, 'au')) {
            $parts = explode('au', $dateRange);
            if (count($parts) === 2) {
                $fromParts = explode('/', trim($parts[0]));
                $toParts   = explode('/', trim($parts[1]));

                if (count($fromParts) === 3 && count($toParts) === 3) {
                    [$d1, $m1, $y1] = array_map('intval', $fromParts);
                    [$d2, $m2, $y2] = array_map('intval', $toParts);

                    $dateFrom = \DateTimeImmutable::createFromFormat('!d/m/Y', sprintf('%02d/%02d/%04d', $d1, $m1, $y1));
                    $dateTo   = \DateTimeImmutable::createFromFormat('!d/m/Y', sprintf('%02d/%02d/%04d', $d2, $m2, $y2));
                }
            }
        }

        if (!$dateFrom || !$dateTo) {
            $dateFrom = $dateTo = new \DateTimeImmutable('today');
        }

        $qb = $em->createQueryBuilder()
            ->select('d','c','a','cat','s')
            ->from(\App\Entity\Demande::class, 'd')
            ->join('d.client', 'c')
            ->join('d.article', 'a')
            ->leftJoin('a.categorie', 'cat')
            ->leftJoin('d.status', 's')
            ->andWhere('d.date BETWEEN :start AND :end')
            ->setParameter('start', $dateFrom->format('Y-m-d'))
            ->setParameter('end', $dateTo->format('Y-m-d'));

        if ($code !== '') {
            $qb->andWhere('c.code LIKE :code')->setParameter('code', '%'.$code.'%');
        }

        if ($nom !== '') {
            $qb->andWhere('(c.nom LIKE :nom OR c.prenom LIKE :nom)')
                ->setParameter('nom', '%'.$nom.'%');
        }

        if ($article !== '') {
            $qb->andWhere('(a.id = :articleId)')
                ->setParameter('articleId', $article);
        }

        if ($flux !== '') {
            $qb->andWhere('(s.id = :statusId)')
                ->setParameter('statusId', $flux);
        }

        // Total sans filtres autres que date
        $qbTotal = $em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(\App\Entity\Demande::class, 'd')
            ->andWhere('d.date BETWEEN :start AND :end')
            ->setParameter('start', $dateFrom->format('Y-m-d'))
            ->setParameter('end', $dateTo->format('Y-m-d'));

        $recordsTotal = (int) $qbTotal->getQuery()->getSingleScalarResult();

        // Nombre filtré
        $qbFiltered = clone $qb;
        $qbFiltered->select('COUNT(d.id)');
        $recordsFiltered = (int) $qbFiltered->getQuery()->getSingleScalarResult();

        // Pagination
        $qb->setFirstResult($start)
            ->setMaxResults($length);

        $rows = $qb->getQuery()->getResult();

        $data = [];
        foreach ($rows as $d) {
            /** @var \App\Entity\Demande $d */
            $data[] = [
                'code'     => $d->getClient()?->getCode(),
                'nom'      => trim(($d->getClient()?->getNom() ?? '') . ' ' . ($d->getClient()?->getPrenom() ?? '')),
                'date'     => $d->getDate()?->format('d/m/Y'),
                'article'  => $d->getArticle()->getName(),
                'type'     => $d->getArticle()?->getCategorie()?->getLabel() ?? '',
                'effectif' => $d->getQuantite(),
                'prix'     => $d->getArticle()?->getPrixUnitaire(),
                'montant'  => $d->getMontant(),
                'flux'     => $d->getStatus()?->getStatus(),
                'id'       => $d->getId(),
                'deleted'  => $d->getDeleted()
            ];
        }

        return new JsonResponse([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

}
