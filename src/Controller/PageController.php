<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('page/index.html.twig');
    }

    #[Route('/journal', name: 'journal')]
    public function journal(): Response
    {
        return $this->render('pages/journal.html.twig');
    }

    #[Route('/mouvement', name: 'mouvement')]
    public function mouvement(): Response
    {
        return $this->render('pages/journal.html.twig');
    }

    #[Route('/client', name: 'client')]
    public function client(): Response
    {
        return $this->render('pages/client.html.twig');
    }

    #[Route('/article', name: 'article')]
    public function article(): Response
    {
        return $this->render('pages/article.html.twig');
    }

    #[Route('/dash', name: 'dash')]
    public function dash(): Response
    {
        return $this->render('pages/dash.html.twig');
    }
}
