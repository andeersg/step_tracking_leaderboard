<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Service\GoogleService;
use App\Entity\User;
use App\Service\SettingsService;
use App\Repository\UserRepository;
use App\Form\Type\UserEdit;
use Psr\Log\LoggerInterface;

class AppController extends AbstractController {

  public function index(SessionInterface $session, GoogleService $googleService, UserRepository $userRepo, LoggerInterface $logger): Response {
    $client = $googleService->getClient();

    if ($this->isGranted('ROLE_USER')) {
      $output_data = [];
      $best = 0;
      $total_steps = 0;

      $users = $userRepo->findAll();

      foreach ($users as $user) {
        $logger->info('ID: ' . $user->getId() . ', Mail: ' . $user->getMail());
        $stepsTaken = 0;
        $steps = $user->getStepData();
        foreach ($steps as $stepData) {
          $stepsTaken += $stepData->getSteps();
        }

        $output_data[] = [
          'name' => $user->getUsername() ?: $user->getMail(),
          'steps' => number_format($stepsTaken, 0, ',', '.'),
          'raw_steps' => $stepsTaken,
          'percentage' => 0, // Calculate this somehow.
        ];

        // Keep track of the best.
        $best = $stepsTaken > $best ? $stepsTaken : $best;
        $total_steps += $stepsTaken;
      }

      foreach ($output_data as $key => $item) {
        $output_data[$key]['percentage'] = floor(($item['raw_steps'] / $best) * 100);
      }

      $sample = [
        [
          'name' => 'Anders Grendstadbakk',
          'steps' => number_format(300123, 0, ',', '.'),
          'percentage' => 100,
        ],
        [
          'name' => 'Ola Nordmann',
          'steps' => number_format(298000, 0, ',', '.'),
          'percentage' => 96,
        ],
        [
          'name' => 'Kari Nordmann',
          'steps' => number_format(257000, 0, ',', '.'),
          'percentage' => 94,
        ],
        [
          'name' => 'Per Nordmann',
          'steps' => number_format(30000, 0, ',', '.'),
          'percentage' => 10,
        ],
      ];

      $profile_form = $this->createForm(UserEdit::class);

      // Authenticated
      $response = $this->render('home.html.twig', [
        'users' => $output_data,
        'total_steps' => number_format($total_steps, 0, ',', '.'),
        'total_distance' => number_format($total_steps * 0.0008, 0, ',', '.'),
        'profile_form' => $profile_form->createView(),
      ]);
    }
    else {
      // Show login.
      $response = $this->render('login.html.twig', [
        'login_url' => $client->createAuthUrl(),
      ]);
    }

    return $response;
  }

  public function authenticate(SessionInterface $session, Request $request, GoogleService $googleService) {

  }

  public function logout(SessionInterface $session) {
    $session->remove('access_token');
    return $this->redirectToRoute('index');
  }

  public function debug(SettingsService $settings, GoogleService $googleService) {
    $setting = $settings->get('contest_start');
    $client = $googleService->getClient();

    return $this->render('debug.html.twig', [
      'data' => print_r([
        'setting' => $setting,
        'clienturl' => $client->createAuthUrl(),
      ], TRUE),
    ]);
  }

  public function saveProfile(Request $request): Response {
    $user = new User();

    $form = $this->createForm(UserEdit::class, $user);

    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // $form->getData() holds the submitted values
      // but, the original `$task` variable has also been updated
      $user = $form->getData();

      // ... perform some action, such as saving the task to the database
      // for example, if Task is a Doctrine entity, save it!
      // $entityManager = $this->getDoctrine()->getManager();
      // $entityManager->persist($task);
      // $entityManager->flush();

      return $this->redirectToRoute('index');
    }
  }


}
