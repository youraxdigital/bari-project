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
        $code         = trim((string)$request->request->get('code', ''));
        $nom          = trim((string)$request->request->get('nom', ''));
        $prenom       = trim((string)$request->request->get('prenom', ''));
        $telephone    = trim((string)$request->request->get('telephone', ''));
        $categorieId  = $request->request->get('categorieId');
        $localisation = trim((string)$request->request->get('localisation', ''));

        // Nouveaux champs
        $ice        = trim((string)$request->request->get('ice', ''));
        $patente    = trim((string)$request->request->get('patente', ''));
        $adresse    = trim((string)$request->request->get('adresse', ''));
        $codePostal = trim((string)$request->request->get('codePostal', ''));

        // Validations simples
        $errors = [];
        if ($code === '') { $errors['code'] = 'Code requis.'; }
        if ($nom === '')  { $errors['nom']  = 'Nom requis.'; }

        if ($ice !== '' && !preg_match('/^\d{15}$/', $ice)) {
            $errors['ice'] = "ICE invalide : 15 chiffres attendus.";
        }
        if ($codePostal !== '' && !preg_match('/^[0-9A-Za-z\- ]{3,10}$/', $codePostal)) {
            $errors['codePostal'] = "Code postal invalide.";
        }

        if ($errors) {
            return new JsonResponse(['message' => 'Validation échouée', 'errors' => $errors], 400);
        }

        // Unicité CODE
        if ($em->getRepository(Client::class)->findOneBy(['code' => $code])) {
            return new JsonResponse(['message' => 'Un client avec ce code existe déjà.'], 409);
        }

        // Unicité ICE si fourni
        if ($ice !== '') {
            if ($em->getRepository(Client::class)->findOneBy(['ice' => $ice])) {
                return new JsonResponse(['message' => 'Un client avec cet ICE existe déjà.'], 409);
            }
        }

        $client = new Client();
        $client->setCode($code);
        $client->setNom($nom);
        $client->setPrenom($prenom);
        $client->setTelephone($telephone ?: null);
        $client->setLocalisation($localisation ?: null);

        // Nouveaux champs (nullable)
        $client->setIce($ice ?: null);
        $client->setPatente($patente ?: null);
        $client->setAdresse($adresse ?: null);
        $client->setCodePostal($codePostal ?: null);

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
        $code         = trim((string)$request->request->get('code', ''));
        $nom          = trim((string)$request->request->get('nom', ''));
        $prenom       = trim((string)$request->request->get('prenom', ''));
        $telephone    = trim((string)$request->request->get('telephone', ''));
        $localisation = trim((string)$request->request->get('localisation', ''));
        $categorieId  = $request->request->get('categorieId');

        // Nouveaux champs
        $ice        = trim((string)$request->request->get('ice', ''));
        $patente    = trim((string)$request->request->get('patente', ''));
        $adresse    = trim((string)$request->request->get('adresse', ''));
        $codePostal = trim((string)$request->request->get('codePostal', ''));

        $errors = [];
        if ($code === '') { $errors['code'] = 'Code requis.'; }
        if ($nom === '')  { $errors['nom']  = 'Nom requis.'; }

        if ($ice !== '' && !preg_match('/^\d{15}$/', $ice)) {
            $errors['ice'] = "ICE invalide : 15 chiffres attendus.";
        }
        if ($codePostal !== '' && !preg_match('/^[0-9A-Za-z\- ]{3,10}$/', $codePostal)) {
            $errors['codePostal'] = "Code postal invalide.";
        }
        if ($errors) {
            return new JsonResponse(['message' => 'Validation échouée', 'errors' => $errors], 400);
        }

        // Unicité CODE (exclure ce client)
        $existsCode = $em->getRepository(Client::class)->findOneBy(['code' => $code]);
        if ($existsCode && $existsCode->getId() !== $client->getId()) {
            return new JsonResponse(['message' => 'Un client avec ce code existe déjà.'], 409);
        }

        // Unicité ICE si fourni (exclure ce client)
        if ($ice !== '') {
            $existsIce = $em->getRepository(Client::class)->findOneBy(['ice' => $ice]);
            if ($existsIce && $existsIce->getId() !== $client->getId()) {
                return new JsonResponse(['message' => 'Un client avec cet ICE existe déjà.'], 409);
            }
        }

        // MAJ valeurs
        $client->setCode($code);
        $client->setNom($nom);
        $client->setPrenom($prenom);
        $client->setTelephone($telephone ?: null);
        $client->setLocalisation($localisation ?: null);

        $client->setIce($ice ?: null);
        $client->setPatente($patente ?: null);
        $client->setAdresse($adresse ?: null);
        $client->setCodePostal($codePostal ?: null);

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

        // Nouveaux filtres (optionnels)
        $ice        = trim((string)$request->query->get('ice', ''));
        $patente    = trim((string)$request->query->get('patente', ''));
        $adresse    = trim((string)$request->query->get('adresse', ''));
        $cp         = trim((string)$request->query->get('cp', ''));

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
        if ($ice !== '') {
            $qb->andWhere('c.ice LIKE :ice')->setParameter('ice', "%$ice%");
        }
        if ($patente !== '') {
            $qb->andWhere('c.patente LIKE :patente')->setParameter('patente', "%$patente%");
        }
        if ($adresse !== '') {
            $qb->andWhere('c.adresse LIKE :adresse')->setParameter('adresse', "%$adresse%");
        }
        if ($cp !== '') {
            $qb->andWhere('c.codePostal LIKE :cp')->setParameter('cp', "%$cp%");
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
                'id'           => $c->getId(),
                'code'         => $c->getCode(),
                'nomComplet'   => trim(($c->getNom() ?? '').' '.($c->getPrenom() ?? '')),
                'telephone'    => $c->getTelephone(),
                'categorie'    => $c->getCategorie()?->getLabel(),
                'categorieId'  => $c->getCategorie()?->getId(),
                'localisation' => $c->getLocalisation(),
                // Nouveaux champs exposés côté datatable
                'ice'          => $c->getIce(),
                'patente'      => $c->getPatente(),
                'adresse'      => $c->getAdresse(),
                'codePostal'   => $c->getCodePostal(),
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
