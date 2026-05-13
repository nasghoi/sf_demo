<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

// prefix kan di group per satu class
#[Route('/group', name: 'group_')]
class GroupController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return new Response("Hello Worldssss");
    }

    #[Route('/show', name: 'show')]
    public function show(): Response
    {
        return $this->render('group/show.html.twig', [
            'name' => 'NASR'
        ]);
    }
}