<?php

namespace App\Controller;

use App\Entity\Caisse;
use App\Repository\CaisseRepository;
use App\Repository\MouvementCaisseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Twig\Environment;

class CaisseManagerController extends AbstractController
{
    #[Route('/app/v1/manager-caisses', name: 'app_manager_caisses')]
    public function index(): Response
    {
        return $this->render('manager/caisse.html.twig');
    }

    #[Route('/app/v1/manager-caisses/list', name: 'app_manager_caisses_datatable')]
    public function datatable(Request $request, CaisseRepository $repo): JsonResponse
    {
        $start = $request->query->getInt('start');
        $length = $request->query->getInt('length');
        $agent = $request->query->get('agent');
        $dateRange = $request->query->get('date'); // format : 'YYYY-MM-DD to YYYY-MM-DD'

        $query = $repo->createQueryBuilder('c');

        if ($agent) {
            $query->andWhere('LOWER(c.agentResponsable) LIKE :agent')
                ->setParameter('agent', '%' . strtolower($agent) . '%');
        }

        // Appliquer filtre par date SEULEMENT si deux dates sont valides
        if ($dateRange && str_contains($dateRange, ' au ')) {
            [$startDateStr, $endDateStr] = explode(' au ', $dateRange);

            if (!empty($startDateStr) && !empty($endDateStr)) {
                try {
                    $startDateObj = new \DateTime($startDateStr);
                    $endDateObj = new \DateTime($endDateStr);

                    //dd($startDateStr, $endDateStr);
                    $query->andWhere('c.openedAt BETWEEN :start AND :end')
                        ->setParameter('start', $startDateObj->setTime(0, 0))
                        ->setParameter('end', $endDateObj->setTime(23, 59));
                } catch (\Exception $e) {
                    // Ne rien faire si erreur de parsing
                }
            }
        }

        $total = count($query->getQuery()->getResult());

        $data = $query->orderBy('c.openedAt', 'DESC')
            ->setFirstResult($start)
            ->setMaxResults($length)
            ->getQuery()
            ->getResult();

        $rows = array_map(function ($caisse) {
            return [
                'id' => $caisse->getId(),
                'agentResponsable' => $caisse->getAgentResponsable(),
                'openedAt' => $caisse->getOpenedAt()->format('d/m/Y H:i'),
                'closedAt' => $caisse->getClosedAt()?->format('d/m/Y H:i'),
                'montantInitial' => number_format($caisse->getMontantInitial(), 2),
                'montantCloture' => $caisse->getMontantCloture() !== null ? number_format($caisse->getMontantCloture(), 2) : null
            ];
        }, $data);

        return $this->json([
            'data' => $rows,
            'recordsTotal' => $total,
            'recordsFiltered' => $total
        ]);
    }


    #[Route('/app/v1/manager-caisses/{id}/mouvements', name: 'app_manager_caisses_mouvements')]
    public function mouvements(int $id, MouvementCaisseRepository $repo, CaisseRepository $caisseRepository): JsonResponse
    {
        $mouvements = $repo->findBy(['caisse' => $id], ['createdAt' => 'DESC']);
        /**
         * @var Caisse $caisse
         */
        $caisse = $caisseRepository->find($id);

        $grouped = [];
        $totauxParType = [];
        $totalGeneral = 0;

        foreach ($mouvements as $m) {
            $type = $m->getType();
            $montant = $m->getMontant();

            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
                $totauxParType[$type] = 0;
            }

            $grouped[$type][] = [
                'type' => $type,
                'motif' => $m->getMotif(),
                'montant' => number_format($montant, 2),
                'date' => $m->getCreatedAt()->format('d/m/Y H:i'),
            ];

            $totauxParType[$type] += $montant;
            $totalGeneral += $montant;
        }

        return $this->json([
            'groupes' => $grouped,
            'totauxParType' => array_map(fn($v) => number_format($v, 2), $totauxParType),
            'totalGlobal' => number_format($totalGeneral, 2),
            'agentResponsable' => $caisse->getAgentResponsable(),
            'closedAt' => $caisse->getClosedAt()?->format('d/m/Y H:i'),
            'openedAt' => $caisse->getOpenedAt()?->format('d/m/Y H:i'),
        ]);
    }

    #[Route('/app/v1/manager-caisses/{id}/ticket', name: 'app_caisse_pdf_ticket', methods: ['GET'])]
    public function exportTicket(
        int $id,
        CaisseRepository $caisseRepository,
        MouvementCaisseRepository $mouvementRepo,
        Environment $twig
    ): Response {
        $caisse = $caisseRepository->find($id);
        if (!$caisse) {
            throw $this->createNotFoundException('Caisse introuvable');
        }

        $mouvements = $mouvementRepo->findBy(['caisse' => $caisse], ['createdAt' => 'ASC']);
        $total = 0;
        $grouped = [];

        foreach ($mouvements as $mvt) {
            $type = $mvt->getType(); // e.g., "ENTREE" ou "SORTIE"
            $grouped[$type][] = $mvt;
            $grouped[$type.'_total'] = ($grouped[$type.'_total'] ?? 0) + $mvt->getMontant();
            $total += $mvt->getMontant();
        }

        $ecart = $total - $caisse->getMontantCloture();

        //dd($grouped);

        $html = $twig->render('partials/ticket_caisse.html.twig', [
            'caisse' => $caisse,
            'mouvementsGroupes' => $grouped,
            'total' => $total,
            'ecart' => $ecart,
            'now' => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Courier');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, 226.77, 841.89], 'portrait'); // 80mm x A4
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ticket-caisse-'.$id.'.pdf"',
        ]);
    }



}
