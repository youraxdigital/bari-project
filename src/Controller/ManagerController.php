<?php

namespace App\Controller;

use App\Entity\CategorieClient;
use App\Repository\ArticleRepository;
use App\Repository\CategorieArticleRepository;
use App\Repository\CategorieClientRepository;
use App\Repository\DemandeRepository;
use App\Repository\StatusDemandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Client;
use App\Entity\Article;
use App\Entity\Demande;
use App\Entity\StatusDemande;
use Doctrine\ORM\EntityManagerInterface;

final class ManagerController extends AbstractController
{
    #[Route('/app/v1/manager', name: 'app_index')]
    public function index(CategorieClientRepository $catClientRepo,
                          CategorieArticleRepository $catArticleRepo,
                          ArticleRepository $articleRepository,
                          StatusDemandeRepository $statusDemandeRepository): Response
    {

        return $this->render('manager/index.html.twig', [
            'categories' => $catClientRepo->findBy([], ['label' => 'ASC']),            // pour le modal client
            'categoriesArticle' => $catArticleRepo->findBy([], ['label' => 'ASC']),    // pour le modal article
            'articles' => $articleRepository->findBy([], ['name' => 'ASC']),
            'statusDemandes' => $statusDemandeRepository->findBy([], ['status' => 'ASC']),
        ]);
    }


    #[Route('/app/v1/manager/import', name: 'app_import_excel', methods: ['POST'])]
    public function importExcel(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $file = $request->files->get('excel_file');

        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], 400);
        }


        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        // Status initial
        $status = $em->getRepository(StatusDemande::class)->findOneBy(['status' => StatusDemande::FACTURATION]);
        $categorieClient = $em->getRepository(CategorieClient::class)->findOneBy(['code' => 'BOUCHER']);

        // Skip header
        for ($i = 1; $i < count($rows); $i++) {
            [$code, $nom, $date, $ville, $type, $effectif] = $rows[$i];

            if ($code) {
                // Trouver ou créer le client
                $client = $em->getRepository(Client::class)->findOneBy(['code' => $code]);
                if (!$client) {
                    $client = new Client();
                    $client->setCode($code);
                    // Séparer le nom complet en prénom et nom
                    $nomParts = explode(' ', trim($nom), 2);
                    $prenom = $nomParts[0];
                    $nomDeFamille = $nomParts[1] ?? ''; // Si pas de 2ème partie

                    $client->setPrenom($prenom);
                    $client->setNom($nomDeFamille);
                    $client->setCategorie($categorieClient);
                    $em->persist($client);
                }

                // Trouver l'article par type
                /**
                 * @var Article $article
                 */
                $article = $em->getRepository(Article::class)->findOneBy(['name' => $type]);
                if (!$article) {
                    continue; // ignorer la ligne si aucun article trouvé
                }

                // Calcul du montant
                $quantite = (int)$effectif;
                $montant = $article->getPrixUnitaire() * $quantite;

                $demande = new Demande();
                $demande->setClient($client);
                $demande->setArticle($article);
                $demande->setDate(new \DateTime($date));
                $demande->setQuantite($quantite);
                $demande->setMontant($montant);
                $demande->setStatus($status);

                $em->persist($demande);
            }
            $em->flush();
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/app/v1/manager/demandes', name: 'app_demandes_list', methods: ['GET'])]
    public function list(DemandeRepository $repo): JsonResponse
    {
        $demandes = $repo->findAll();

        $data = [];
        /**
         * @var Demande $d
         */
        foreach ($demandes as $d) {
            $data[] = [
                'code'     => $d->getClient()?->getCode(),
                'nom'      => $d->getClient()?->getNom() . ' ' . $d->getClient()?->getPrenom(),
                'date'     => $d->getDate()->format('d/m/Y'),
                'type'     => $d->getArticle()?->getCategorie()?->getLabel(),
                'effectif' => $d->getQuantite(),
                'prix'     => $d->getArticle()?->getPrixUnitaire(),
                'montant'  => $d->getMontant(),
                'flux'     => $d->getStatus()?->getStatus(),
                'id'       => $d->getId(),
            ];
        }

        return new JsonResponse($data);
    }

}
