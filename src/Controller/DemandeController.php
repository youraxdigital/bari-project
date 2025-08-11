<?php

namespace App\Controller;

use App\Entity\Demande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class DemandeController extends AbstractController
{
    #[Route('/app/v1/manager/demandes/datatable', name: 'app_demandes_datatable', methods: ['GET'])]
    public function datatable(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $draw   = (int) $request->query->get('draw', 1);
        $start  = (int) $request->query->get('start', 0);
        $length = (int) $request->query->get('length', 10);

        $code = trim((string) $request->query->get('code', ''));
        $nom  = trim((string) $request->query->get('nom', ''));
        $dateStr = trim((string) $request->query->get('date', ''));

        // Default = today (without hours)
        if ($dateStr !== '') {
            // expected format dd/mm/yyyy
            $parts = explode('/', $dateStr);
            if (count($parts) === 3) {
                [$d, $m, $y] = $parts;
                $date = \DateTimeImmutable::createFromFormat('!d/m/Y', sprintf('%02d/%02d/%04d', (int)$d, (int)$m, (int)$y));
            }
        }
        if (empty($date) || !$date) {
            $date = new \DateTimeImmutable('today');
        }

        $qb = $em->createQueryBuilder()
            ->select('d','c','a','cat','s')
            ->from(\App\Entity\Demande::class, 'd')
            ->join('d.client', 'c')
            ->join('d.article', 'a')
            ->leftJoin('a.categorie', 'cat')
            ->leftJoin('d.status', 's')
            ->andWhere('d.date = :date')
            ->setParameter('date', $date);

        if ($code !== '') {
            $qb->andWhere('c.code LIKE :code')->setParameter('code', '%'.$code.'%');
        }
        if ($nom !== '') {
            $qb->andWhere('(c.nom LIKE :nom OR c.prenom LIKE :nom)')
                ->setParameter('nom', '%'.$nom.'%');
        }

        // Total count for today (no filters)
        $qbTotal = $em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(\App\Entity\Demande::class, 'd')
            ->andWhere('d.date = :date')
            ->setParameter('date', $date);
        $recordsTotal = (int) $qbTotal->getQuery()->getSingleScalarResult();

        // Filtered count
        $qbFiltered = clone $qb;
        $qbFiltered->select('COUNT(d.id)');
        $recordsFiltered = (int) $qbFiltered->getQuery()->getSingleScalarResult();

        // Page data
        $qb->setFirstResult($start)
            ->setMaxResults($length);

        $rows = $qb->getQuery()->getResult();

        $data = [];
        foreach ($rows as $d) {
            /** @var \App\Entity\Demande $d */
            $data[] = [
                'code'     => $d->getClient()?->getCode(),
                'nom'      => trim(($d->getClient()?->getNom() ?? '').' '.($d->getClient()?->getPrenom() ?? '')),
                'date'     => $d->getDate()?->format('d/m/Y'),
                'type'     => $d->getArticle()?->getCategorie()?->getLabel() ?? '',
                'effectif' => $d->getQuantite(),
                'prix'     => $d->getArticle()?->getPrixUnitaire(),
                'montant'  => $d->getMontant(),
                'flux'     => $d->getStatus()?->getStatus(),
                'id'       => $d->getId(),
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
