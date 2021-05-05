<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
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
      $loggedin_user = $this->getUser();
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
          'percentage' => 0,
          'me' => $loggedin_user->getId() === $user->getId(),
        ];

        // Keep track of the best.
        $best = $stepsTaken > $best ? $stepsTaken : $best;
        $total_steps += $stepsTaken;
      }

      foreach ($output_data as $key => $item) {
        if ($best === 0) {
          continue;
        }
        $output_data[$key]['percentage'] = floor(($item['raw_steps'] / $best) * 100);
      }

      usort($output_data, function ($item1, $item2) {
        return $item2['raw_steps'] <=> $item1['raw_steps'];
      });

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

      $profile_form = $this->createForm(UserEdit::class, $loggedin_user, [
        'action' => $this->generateUrl('save_profile'),
      ]);

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
    // Logic is handled in the Authenticator.
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

  /**
   * Require ROLE_USER for only this controller method.
   *
   * @IsGranted("ROLE_USER")
   */
  public function saveProfile(Request $request): Response {
    $user = $this->getUser();

    $form = $this->createForm(UserEdit::class, $user);

    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // $form->getData() holds the submitted values
      // but, the original `$user` variable has also been updated
      $user = $form->getData();

      // ... perform some action, such as saving the user to the database
      // for example, if Task is a Doctrine entity, save it!
      $entityManager = $this->getDoctrine()->getManager();
//       $entityManager->persist($user);
      $entityManager->flush();

      return $this->redirectToRoute('index');
    }
  }


}
