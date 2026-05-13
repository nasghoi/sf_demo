<?php

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
class HomeController
{
    #[Route('welcome', name: 'welcome', methods: ['GET', 'POST'])]
    public function index(): Response
    {
        return new Response("Hello World");
    }

    public function yamlroute(): Response
    {
        return new Response("Hello World from YAML");
    }

    #[Route('routeparam/{name?}')]
    public function routeparam($name): Response
    {
        return new Response("Hello World " . $name);
    }

    #[Route('book/{page}', name: 'book_page', defaults: ['page' => '1', 'title' => 'Book Title'])]
    public function defaultparam($page, $title): Response
    {
        return new Response("Page: " . $page . "<br> Title: " . $title);
    }
}