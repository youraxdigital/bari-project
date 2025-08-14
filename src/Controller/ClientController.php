<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\CategorieClient; // adjust or remove if not used
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientController extends AbstractController
{
    #[Route('/app/v1/clients/manage', name: 'app_clients_manage', methods: ['GET'])]
    public function manage(EntityManagerInterface $em): Response
    {
        // If you don't use categories, set [] here and simplify twig
        $categories = $em->getRepository(CategorieClient::class)->findBy([], ['label' => 'ASC']);
        return $this->render('manager/clients.html.twig', ['categories' => $categories]);
    }

    #[Route('/app/v1/clients', name: 'app_clients_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $code = trim((string)$request->request->get('code', ''));
        $nom  = trim((string)$request->request->get('nom', ''));
        $prenom = trim((string)$request->request->get('prenom', ''));
        $telephone = trim((string)$request->request->get('telephone', ''));
        $categorieId = $request->request->get('categorieId');

        $errors = [];
        if ($code === '') { $errors['code'] = 'Code requis.'; }
        if ($nom === '')  { $errors['nom']  = 'Nom requis.'; }

        if ($errors) {
            return new JsonResponse(['message' => 'Validation échouée', 'errors' => $errors], 400);
        }

        // Unicité code (optionnel)
        if ($em->getRepository(Client::class)->findOneBy(['code' => $code])) {
            return new JsonResponse(['message' => 'Un client avec ce code existe déjà.'], 409);
        }

        $client = new Client();
        $client->setCode($code);
        $client->setNom($nom);
        $client->setPrenom($prenom);
        $client->setTelephone($telephone);

        // Catégorie si utilisée
        if ($categorieId) {
            $cat = $em->getRepository(CategorieClient::class)->find($categorieId);
            if ($cat) { $client->setCategorie($cat); }
        }

        $em->persist($client);
        $em->flush();

        return new JsonResponse(['message' => 'Client créé avec succès.', 'id' => $client->getId()], 201);
    }

    #[Route('/app/v1/clients/update/{id}', name: 'app_clients_update', methods: ['POST'])]
    public function update(Client $client, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $client->setCode($request->request->get('code'));
        $client->setNom($request->request->get('nom'));
        $client->setPrenom($request->request->get('prenom'));
        $client->setTelephone($request->request->get('telephone'));

        $categorieId = $request->request->get('categorieId');
        if ($categorieId !== null) {
            $cat = $em->getRepository(CategorieClient::class)->find($categorieId);
            $client->setCategorie($cat ?: null);
        }

        $em->flush();
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/app/v1/clients/datatable', name: 'app_clients_datatable', methods: ['GET'])]
    public function datatable(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $draw   = (int)$request->query->get('draw', 1);
        $start  = (int)$request->query->get('start', 0);
        $length = (int)$request->query->get('length', 10);

        $code = trim((string)$request->query->get('code', ''));
        $nom  = trim((string)$request->query->get('nom', ''));
        $tel  = trim((string)$request->query->get('tel', ''));
        $cat  = trim((string)$request->query->get('cat', ''));

        $qb = $em->createQueryBuilder()
            ->select('c','catg')
            ->from(Client::class, 'c')
            ->leftJoin('c.categorie', 'catg');

        if ($code !== '') {
            $qb->andWhere('c.code LIKE :code')->setParameter('code', "%$code%");
        }
        if ($nom !== '') {
            $qb->andWhere('(c.nom LIKE :nom OR c.prenom LIKE :nom)')
                ->setParameter('nom', "%$nom%");
        }
        if ($tel !== '') {
            $qb->andWhere('c.telephone LIKE :tel')->setParameter('tel', "%$tel%");
        }
        if ($cat !== '') {
            $qb->andWhere('catg.label LIKE :cat')->setParameter('cat', "%$cat%");
        }

        // total
        $recordsTotal = (int)$em->createQueryBuilder()
            ->select('COUNT(c2.id)')
            ->from(Client::class, 'c2')
            ->getQuery()->getSingleScalarResult();

        // filtered
        $qbFiltered = clone $qb;
        $recordsFiltered = (int)$qbFiltered->select('COUNT(c.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()->getSingleScalarResult();

        // page
        $qb->setFirstResult($start)->setMaxResults($length);
        /** @var Client[] $rows */
        $rows = $qb->getQuery()->getResult();

        $data = array_map(function (Client $c) {
            return [
                'id'         => $c->getId(),
                'code'       => $c->getCode(),
                'nomComplet' => trim(($c->getNom() ?? '').' '.($c->getPrenom() ?? '')),
                'telephone'  => $c->getTelephone(),
                'categorie'  => $c->getCategorie()?->getLabel(),
                // optionally return categorieId to preselect in edit modal
                'categorieId'=> $c->getCategorie()?->getId(),
            ];
        }, $rows);

        return new JsonResponse([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }
}
