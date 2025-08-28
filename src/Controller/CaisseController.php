<?php

namespace App\Controller;

use App\Entity\Article;
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

        $articles = $em->getRepository(Article::class)->findAll();

        return $this->render('caisse/index.html.twig', [
            'caisse' => $caisse,
            'demandes' => $demandes,
            'mouvements' => $mouvements,
            'articles' => $articles,
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
        $agent = $request->request->get('agentResponsable');

        $caisse = new Caisse();
        $caisse->setOpenedAt(new \DateTime());
        $caisse->setMontantInitial($montant);
        $caisse->setMontantActuel($montant);
        $caisse->setAgentResponsable($agent);

        $em->persist($caisse);
        $em->flush();

        if ($montant >= 0) {
            $mouvement = new MouvementCaisse();
            $mouvement->setCaisse($caisse);
            $mouvement->setType("ENTREE");
            $mouvement->setMotif("Début de caisse");
            $mouvement->setMontant($montant);
            $mouvement->setCreatedAt(new \DateTime());
            $em->persist($mouvement);
            $em->flush();
        }

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
        if ($type == MouvementCaisse::SORTIE_TYPE) {
            $montant = $montant*-1;
        }
        $mouvement->setMontant($montant);
        $mouvement->setCreatedAt(new \DateTime());

        $caisse->setMontantActuel($caisse->getMontantActuel() + $montant);
        //if ($type === 'ENTREE') {
        //    $caisse->setMontantActuel($caisse->getMontantActuel() + $montant);
        //} else {
        //    $caisse->setMontantActuel($caisse->getMontantActuel() - $montant);
        //}

        $em->persist($mouvement);
        $em->flush();

        return $this->redirectToRoute('app_caisse');
    }

    #[Route('/app/v1/caisse/encaisser/{id}', name: 'app_caisse_encaisse', methods: ['POST'])]
    public function encaisser(Demande $demande, EntityManagerInterface $em, CaisseRepository $caisseRepo, Request $request): Response
    {
        $caisse = $caisseRepo->createQueryBuilder('c')
            ->where('c.closedAt IS NULL')
            ->orderBy('c.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$caisse) return new Response('Caisse non trouvée', 404);

        $typePaiement = $request->request->get('typePaiement');
        if (!in_array($typePaiement, ['espece', 'banque', 'carte'])) {
            return new Response('Type de paiement invalide', 400);
        }

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
        $mouvement->setTypePaiement($typePaiement);

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
    public function close(Request $request, CaisseRepository $repo, EntityManagerInterface $em): JsonResponse
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

        $montantCloture = $request->request->get('montantCloture');

        if (!is_numeric($montantCloture)) {
            return new JsonResponse(['success' => false, 'message' => 'Montant invalide'], 400);
        }


        $caisse->setClosedAt(new \DateTime());
        $caisse->setMontantCloture((float) $montantCloture);
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
        // 80mm = ~226.77pt ; on donne une hauteur “grande” (ex: 1200pt)
        // Dompdf recoupera si nécessaire. Adapte si ton ticket est plus long/court.
        $widthPt  = 226.77;         // 80mm
        $heightPt = 1200;           // ~423mm
        $dompdf->setPaper([$widthPt, 0, $widthPt, $heightPt], 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="historique-caisse.pdf"',
        ]);

    }


    #[Route('/app/v1/caisse/encaisser-lot', name: 'app_caisse_encaisser_lot', methods: ['POST'])]
    public function encaisserLot(
        Request $request,
        CaisseRepository $caisseRepository,
        DemandeRepository $demandeRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $idsString = $request->request->get('ids'); // "24,25,41"
        $typePaiement = $request->request->get('typePaiement');

        if (!$idsString || !$typePaiement) {
            return new JsonResponse(['success' => false, 'message' => 'Champs manquants'], 400);
        }

        $ids = explode(',', $idsString); // ['24', '25', '41']

        $caisse = $caisseRepository->findOneBy(['closedAt' => null], ['openedAt' => 'DESC']);

        $total = 0;
        foreach ($ids as $id) {
            /**
             * @var Demande $demande
             */
            $demande = $demandeRepository->find($id);
            if (!$demande || $demande->getStatus()->getStatus() !== Demande::DEMANDE_STATUS_FACTURATION) continue;

            $montant = $demande->getMontant();
            $clientName = $demande->getClient()?->getNom() . ' ' . $demande->getClient()?->getPrenom();
            $articleName = $demande->getArticle()?->getName();
            $date = $demande->getDate()?->format('d/m/Y');

            $motif = "Encaissement de {$clientName} - {$articleName} ({$date})";

            $mvt = new MouvementCaisse();
            $mvt->setCaisse($caisse);
            $mvt->setMontant($montant);
            $mvt->setType('ENCAISSEMENT');
            $mvt->setMotif($motif);
            $mvt->setDemande($demande);
            $mvt->setTypePaiement($typePaiement);
            $mvt->setCreatedAt(new \DateTime());

            $caisse->setMontantActuel($caisse->getMontantActuel() + $montant);

            $status = $em->getRepository(StatusDemande::class)
                ->findOneBy(['status' => StatusDemande::ENCAISSEMENT]);
            $demande->setStatus($status);

            $em->persist($mvt);
            $em->persist($demande);
            $total += $montant;
        }

        $em->persist($caisse);
        $em->flush();

        return new JsonResponse(['success' => true, 'total' => $total]);
    }


}
