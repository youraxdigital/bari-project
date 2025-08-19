<?php

namespace App\Controller;


use App\Entity\Caisse;
use App\Entity\MouvementCaisse;
use App\Entity\Demande;
use App\Entity\StatusDemande;
use App\Repository\DemandeRepository;
use App\Repository\CaisseRepository;
use App\Repository\MouvementCaisseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


final class CaisseController extends AbstractController
{
    #[Route('/app/v1/caisse', name: 'app_caisse')]
    public function index(
        CaisseRepository $repo,
        DemandeRepository $demandeRepository,
        EntityManagerInterface $em
    ): Response
    {
        // Dernière caisse
        $caisse = $repo->findOneBy([], ['openedAt' => 'DESC']);

        // On récupère l'objet StatusDemande correspondant à "FACTURATION"
        $status = $em->getRepository(StatusDemande::class)
            ->findOneBy(['status' => StatusDemande::FACTURATION]);

        // Puis on récupère les demandes avec ce status
        $demandes = $demandeRepository->findBy(['status' => $status]);

        return $this->render('caisse/index.html.twig', [
            'caisse' => $caisse,
            'demandes' => $demandes
        ]);
    }


    #[Route('/app/v1/caisse/open', name: 'app_caisse_open', methods: ['POST'])]
    public function open(Request $request, EntityManagerInterface $em): Response
    {
        $montant = (float)$request->request->get('montant');
        $caisse = new Caisse();
        $caisse->setAgent($this->getUser());
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
        //$caisse = $caisseRepo->findOneBy(['agent' => $this->getUser()], ['openedAt' => 'DESC']);
        $caisse = $caisseRepo->findOneBy([], ['openedAt' => 'DESC']);
        if (!$caisse) return new Response('Caisse non trouvée', 404);


        $montant = $demande->getMontant();
        $caisse->setMontantActuel($caisse->getMontantActuel() + $montant);

        // On récupère l'objet StatusDemande correspondant à "ENCAISSEMENT"
        $status = $em->getRepository(StatusDemande::class)
            ->findOneBy(['status' => StatusDemande::ENCAISSEMENT]);

        $demande->setStatus($status); // marque comme encaissé ou nouveau statut selon votre logique
        $demande->setCaisse($caisse);


        $em->flush();


        return $this->json(['success' => true]);
    }

}
