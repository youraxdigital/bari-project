<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\CategorieClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class ClientController extends AbstractController
{
    #[Route('/app/v1/clients', name: 'app_client_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $nom        = trim((string) $request->request->get('nom', ''));
        $prenom     = trim((string) $request->request->get('prenom', ''));
        $code       = trim((string) $request->request->get('code', ''));
        $telephone  = trim((string) $request->request->get('telephone', ''));
        $prix       = $request->request->get('prix');
        $categorieId= (int) $request->request->get('categorieId');

        if ($nom === '' || $prenom === '' || $code === '' || $prix === null || !$categorieId) {
            return new JsonResponse(['ok' => false, 'message' => 'Champs requis manquants'], 400);
        }

        // Vérifier unicité code
        $exists = $em->getRepository(Client::class)->findOneBy(['code' => $code]);
        if ($exists) {
            return new JsonResponse(['ok' => false, 'message' => 'Code client déjà utilisé'], 409);
        }

        $categorie = $em->getRepository(CategorieClient::class)->find($categorieId);
        if (!$categorie) {
            return new JsonResponse(['ok' => false, 'message' => 'Catégorie introuvable'], 404);
        }

        $client = new Client();
        $client->setNom($nom)
            ->setPrenom($prenom)
            ->setCode($code)
            ->setTelephone($telephone !== '' ? $telephone : null)
            ->setPrix((float)$prix)
            ->setCategorie($categorie);

        $em->persist($client);
        $em->flush();

        return new JsonResponse([
            'ok'   => true,
            'id'   => $client->getId(),
            'code' => $client->getCode(),
            'nom'  => $client->getNom(),
            'prenom' => $client->getPrenom(),
            'categorie' => $categorie->getLabel()
        ], 201);
    }
}

