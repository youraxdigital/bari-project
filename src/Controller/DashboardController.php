<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Client;
use App\Entity\Demande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    /**
     * (Optional) Page route that renders the Twig dashboard.
     * Point your menu "Dashboard" button here.
     */
    #[Route('/app/v1/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('manager/dashboard.html.twig');
    }

    /**
     * API feeding ApexCharts.
     */
    #[Route('/app/v1/dashboard/stats', name: 'app_dashboard_stats', methods: ['GET'])]
    public function stats(EntityManagerInterface $em): JsonResponse
    {
        $today = new \DateTimeImmutable('today');
        $since = $today->sub(new \DateInterval('P29D')); // 30 jours glissants

        // ---------- KPIs ----------
        $totalClients = (int) $em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Client::class, 'c')
            ->getQuery()->getSingleScalarResult();

        $totalArticles = (int) $em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Article::class, 'a')
            ->getQuery()->getSingleScalarResult();

        // ---------- Timeseries Demandes & CA (30j) ----------
        // DQL: on sélectionne la date telle quelle (Date/DateTime),
        // on groupe par le champ et on formate en PHP.
        $rows = $em->createQueryBuilder()
            ->select('d.date AS dte, COUNT(d.id) AS cnt, COALESCE(SUM(d.montant), 0) AS ca')
            ->from(Demande::class, 'd')
            ->andWhere('d.date BETWEEN :since AND :today')
            ->groupBy('d.date')
            ->orderBy('d.date', 'ASC')
            ->setParameter('since', $since)
            ->setParameter('today', $today)
            ->getQuery()->getArrayResult();

        // Map jour -> {count, ca}
        $map = [];
        foreach ($rows as $r) {
            $key = $r['dte'] instanceof \DateTimeInterface
                ? $r['dte']->format('Y-m-d')
                : (string) $r['dte'];
            $map[$key] = [
                'count' => (int) ($r['cnt'] ?? 0),
                'ca'    => (float) ($r['ca'] ?? 0),
            ];
        }

        // Remplir tous les jours manquants (zéro)
        $timeseries = [];
        $cursor = $since;
        while ($cursor <= $today) {
            $key = $cursor->format('Y-m-d');
            $timeseries[] = [
                'date'  => $key,
                'count' => $map[$key]['count'] ?? 0,
                'ca'    => $map[$key]['ca'] ?? 0.0,
            ];
            $cursor = $cursor->add(new \DateInterval('P1D'));
        }

        $demandes30j = array_sum(array_column($timeseries, 'count'));
        $ca30j       = array_sum(array_column($timeseries, 'ca'));

        // ---------- Répartition par Catégorie (30j) ----------
        // Demande -> Article -> Catégorie
        // Adaptez le nom de la relation "categorie" sur Article si différent.
        $catRows = $em->createQueryBuilder()
            ->select('cat.label AS label, COUNT(d.id) AS cnt')
            ->from(Demande::class, 'd')
            ->join('d.article', 'a')
            ->leftJoin('a.categorie', 'cat')
            ->andWhere('d.date BETWEEN :since AND :today')
            ->groupBy('cat.id, cat.label')
            ->orderBy('cnt', 'DESC')
            ->setParameter('since', $since)
            ->setParameter('today', $today)
            ->getQuery()->getArrayResult();

        $categories = array_map(
            fn ($r) => [
                'label' => $r['label'] ?? '—',
                'count' => (int) $r['cnt'],
            ],
            $catRows
        );

        // ---------- Distribution TVA (sur tous les articles) ----------
        $tvaRows = $em->createQueryBuilder()
            ->select('a.tva AS tva, COUNT(a.id) AS cnt')
            ->from(Article::class, 'a')
            ->groupBy('a.tva')
            ->getQuery()->getArrayResult();

        // Buckets simples (adaptez selon votre grille TVA)
        $buckets = ['0%' => 0, '10%' => 0, '20%' => 0, '>20%' => 0];
        foreach ($tvaRows as $r) {
            $t = (float) ($r['tva'] ?? 0);
            $c = (int) $r['cnt'];
            if ($t <= 0)        { $buckets['0%']   += $c; }
            elseif ($t <= 10)   { $buckets['10%']  += $c; }
            elseif ($t <= 20)   { $buckets['20%']  += $c; }
            else                { $buckets['>20%'] += $c; }
        }
        $tva = [];
        foreach ($buckets as $label => $cnt) {
            $tva[] = ['bucket' => $label, 'count' => $cnt];
        }

        // ---------- Top 5 Clients par CA (30j) ----------
        // Pour éviter CONCAT à rallonge en DQL, on récupère nom/prenom et on assemble en PHP.
        $topRaw = $em->createQueryBuilder()
            ->select('c.id AS id, c.nom AS nom, c.prenom AS prenom, COALESCE(SUM(d.montant), 0) AS ca')
            ->from(Demande::class, 'd')
            ->join('d.client', 'c')
            ->andWhere('d.date BETWEEN :since AND :today')
            ->groupBy('c.id, c.nom, c.prenom')
            ->orderBy('ca', 'DESC')
            ->setMaxResults(5)
            ->setParameter('since', $since)
            ->setParameter('today', $today)
            ->getQuery()->getArrayResult();

        $topClients = array_map(function (array $r) {
            $nom = trim((string) ($r['nom'] ?? ''));
            $prenom = trim((string) ($r['prenom'] ?? ''));
            $label = trim($nom . ' ' . $prenom);
            return [
                'client' => $label !== '' ? $label : '—',
                'ca'     => (float) $r['ca'],
            ];
        }, $topRaw);

        // ---------- Réponse ----------
        return new JsonResponse([
            'kpis' => [
                'totalClients'  => $totalClients,
                'totalArticles' => $totalArticles,
                'demandes30j'   => $demandes30j,
                'ca30j'         => $ca30j,
            ],
            'timeseries' => $timeseries,
            'categories' => $categories,
            'tva'        => $tva,
            'topClients' => $topClients,
        ]);
    }
}
