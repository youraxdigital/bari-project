<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\CategorieArticle;
use App\Entity\CategorieClient;
use App\Entity\StatusDemande;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // CATEGORIES ARTICLES + ARTICLES
        $categoriesArticle = [
            'OVIN',
            'Abats OVIN',
            'BOVIN',
            'Abats BOVIN',
            'CAPRINE',
            'DROMADAIRE',
            'Équidé',
        ];

        foreach ($categoriesArticle as $cat) {
            $categorie = new CategorieArticle();
            $code = strtoupper(str_replace(' ', '_', $cat));
            $categorie->setLabel($cat);
            $categorie->setCode($code);
            $manager->persist($categorie);

            // Create one Article per CategorieArticle
            $article = new Article();
            $article->setCode($code); // same as category code
            $article->setTva(20.0);
            $article->setPrixUnitaire(100.0);
            $article->setCategorie($categorie);
            $manager->persist($article);
        }

        // CATEGORIES CLIENTS
        $categoriesClient = [
            'Boucher',
            'Grossiste',
            'Tripie',
        ];

        foreach ($categoriesClient as $cat) {
            $categorie = new CategorieClient();
            $categorie->setLabel($cat);
            $categorie->setCode(strtoupper($cat));
            $manager->persist($categorie);
        }

        $statusDemandes = [
          "Facturation",
          "Encaissement"
        ];

        foreach ($statusDemandes as $statusDemande) {
            $status = new StatusDemande();
            $status->setStatus($statusDemande);
            $manager->persist($status);
        }

        $manager->flush();
    }
}
