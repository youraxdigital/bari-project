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

    #[Route('/app/v1/manager/demandes/{id}/delete', name: 'app_demandes_delete', methods: ['POST'])]
    public function delete(Demande $demande, EntityManagerInterface $em): JsonResponse
    {
        $demande->setDeleted(true); // suppose que tu as un champ booléen "deleted" dans l'entité
        $em->flush();

        return new JsonResponse(['success' => true]);
    }



}
