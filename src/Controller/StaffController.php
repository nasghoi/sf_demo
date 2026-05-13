<?php

namespace App\Controller;

use App\Entity\Staff;
use App\Form\StaffType;
use App\Repository\StaffRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff/', name: 'app_staff_')]
#[IsGranted("IS_AUTHENTICATED_FULLY")]
final class StaffController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(StaffRepository $staffRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');
        return $this->render('staff/index.html.twig', [
            'staffs' => $staffRepository->findAll(),
        ]);
    }

    #[Route('new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $manager): Response
    {
        $staff = new Staff();

        $staff->setStatus(1);

        $form = $this->createForm(StaffType::class, $staff);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager->persist($staff);
            $manager->flush();

            return $this->redirectToRoute('app_staff_index');
        }

        return $this->render('staff/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('show', name: 'show')]
    public function show(): Response
    {
        return $this->render('staff/show.html.twig', [
        ]);
    }

    #[Route('update', name: 'update')]
    public function update(): Response
    {
        return $this->render('staff/update.html.twig', [
        ]);
    }

    #[Route('delete', name: 'delete')]
    public function delete(): Response
    {
        return $this->render('staff/delete.html.twig', [
        ]);
    }
}
