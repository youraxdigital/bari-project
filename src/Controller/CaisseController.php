<?php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\Caisse;
use App\Entity\MouvementCaisse;
use App\Entity\Demande;
use App\Entity\StatusDemande;
use App\Repository\DemandeRepository;
use App\Repository\CaisseRepository;
use App\Repository\MouvementCaisseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


final class CaisseController extends AbstractController
{
    #[Route('/app/v1/caisse', name: 'app_caisse')]
    public function index(
        CaisseRepository $repo,
        DemandeRepository $demandeRepository,
        MouvementCaisseRepository $mouvementRepo,
        EntityManagerInterface $em
    ): Response
    {
        $startOfDay = (new \DateTime())->setTime(0, 0, 0);
        $endOfDay = (new \DateTime())->setTime(23, 59, 59);

        $caisse = $repo->createQueryBuilder('c')
            ->where('c.openedAt BETWEEN :start AND :end')
            ->andWhere('c.closedAt IS NULL')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->orderBy('c.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();


        $status = $em->getRepository(StatusDemande::class)
            ->findOneBy(['status' => StatusDemande::FACTURATION]);

        $demandes = $caisse
            ? $demandeRepository->findBy(['status' => $status, 'deleted' => false])
            : [];

        $mouvements = $caisse
            ? $mouvementRepo->findBy(['caisse' => $caisse], ['createdAt' => 'DESC'])
            : [];

        return $this->render('caisse/index.html.twig', [
            'caisse' => $caisse,
            'demandes' => $demandes,
            'mouvements' => $mouvements
        ]);
    }

    #[Route('/app/v1/caisse/open', name: 'app_caisse_open', methods: ['POST'])]
    public function open(Request $request, EntityManagerInterface $em): Response
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        $existing = $em->getRepository(Caisse::class)->createQueryBuilder('c')
            ->where('c.openedAt >= :start')
            ->andWhere('c.openedAt < :end')
            ->andWhere('c.closedAt IS NULL')
            ->setParameter('start', $today)
            ->setParameter('end', $tomorrow)
            ->getQuery()
            ->getOneOrNullResult();


        if ($existing) {
            return $this->redirectToRoute('app_caisse');
        }

        $montant = (float)$request->request->get('montant');

        $caisse = new Caisse();
        $caisse->setOpenedAt(new \DateTime());
        $caisse->setMontantInitial($montant);
        $caisse->setMontantActuel($montant);

        $em->persist($caisse);
        $em->flush();

        return $this->redirectToRoute('app_caisse');
    }

    #[Route('/app/v1/caisse/mouvement', name: 'app_caisse_mouvement', methods: ['POST'])]
    public function mouvement(Request $request, CaisseRepository $caisseRepo, EntityManagerInterface $em): Response
    {
        $caisse = $caisseRepo->find($request->request->get('caisse_id'));
        $montant = (float)$request->request->get('montant');
        $type = $request->request->get('type');
        $motif = $request->request->get('motif');

        $mouvement = new MouvementCaisse();
        $mouvement->setCaisse($caisse);
        $mouvement->setType($type);
        $mouvement->setMotif($motif);
        $mouvement->setMontant($montant);
        $mouvement->setCreatedAt(new \DateTime());

        if ($type === 'ENTREE') {
            $caisse->setMontantActuel($caisse->getMontantActuel() + $montant);
        } else {
            $caisse->setMontantActuel($caisse->getMontantActuel() - $montant);
        }

        $em->persist($mouvement);
        $em->flush();

        return $this->redirectToRoute('app_caisse');
    }

    #[Route('/app/v1/caisse/encaisser/{id}', name: 'app_caisse_encaisse', methods: ['POST'])]
    public function encaisser(Demande $demande, EntityManagerInterface $em, CaisseRepository $caisseRepo): Response
    {
        $caisse = $caisseRepo->createQueryBuilder('c')
            ->where('c.closedAt IS NULL')
            ->orderBy('c.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$caisse) return new Response('Caisse non trouvée', 404);

        $montant = $demande->getMontant();
        $caisse->setMontantActuel($caisse->getMontantActuel() + $montant);

        $status = $em->getRepository(StatusDemande::class)
            ->findOneBy(['status' => StatusDemande::ENCAISSEMENT]);

        $demande->setStatus($status);
        $demande->setCaisse($caisse);

        // Créer un mouvement ENCAISSEMENT lié à la demande
        $mouvement = new MouvementCaisse();
        $mouvement->setCaisse($caisse);
        $mouvement->setType('ENCAISSEMENT');

        $clientName = $demande->getClient()?->getNom() . ' ' . $demande->getClient()?->getPrenom();
        $articleName = $demande->getArticle()?->getName();
        $date = $demande->getDate()?->format('d/m/Y');

        $motif = "Encaissement de {$clientName} - {$articleName} ({$date})";
        $mouvement->setMotif($motif);
        $mouvement->setMontant($montant);
        $mouvement->setCreatedAt(new \DateTime());
        $mouvement->setDemande($demande); // 💡 liaison avec la demande

        $em->persist($mouvement);
        $em->flush();

        return $this->json(['success' => true]);
    }


    #[Route('/app/v1/caisse/fermer', name: 'app_caisse_fermer', methods: ['POST'])]
    public function fermer(EntityManagerInterface $em, CaisseRepository $repo): Response
    {
        $today = (new \DateTime())->format('Y-m-d');

        $caisse = $repo->createQueryBuilder('c')
            ->where('DATE(c.openedAt) = :today')
            ->andWhere('c.closedAt IS NULL')
            ->setParameter('today', $today)
            ->getQuery()
            ->getOneOrNullResult();

        if ($caisse) {
            $caisse->setClosedAt(new \DateTime());
            $em->flush();
        }

        return $this->redirectToRoute('app_caisse');
    }

    #[Route('/app/v1/caisse/close', name: 'app_caisse_close', methods: ['POST'])]
    public function close(CaisseRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $today = (new \DateTime())->format('Y-m-d');

        $caisse = $repo->createQueryBuilder('c')
            ->where('c.closedAt IS NULL')
            ->andWhere('c.openedAt >= :today')
            ->setParameter('today', new \DateTime($today . ' 00:00:00'))
            ->orderBy('c.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$caisse) {
            return new JsonResponse(['success' => false, 'message' => 'Aucune caisse ouverte trouvée.'], 404);
        }

        $caisse->setClosedAt(new \DateTime());
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/app/v1/caisse/export', name: 'app_caisse_export')]
    public function exportPdf(CaisseRepository $repo): Response
    {
        $today = (new \DateTime())->format('Y-m-d');

        $caisse = $repo->createQueryBuilder('c')
            ->andWhere('c.openedAt >= :start AND c.openedAt < :end')
            ->andWhere('c.closedAt IS NULL')
            ->setParameter('start', new \DateTimeImmutable("$today 00:00:00"))
            ->setParameter('end', new \DateTimeImmutable("$today 23:59:59"))
            ->orderBy('c.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$caisse) {
            throw $this->createNotFoundException("Aucune caisse ouverte pour aujourd'hui.");
        }

        $html = $this->renderView('caisse/pdf.html.twig', [
            'caisse' => $caisse,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="historique-caisse.pdf"',
        ]);

    }


}
