<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Service\GoogleService;
use App\Entity\User;
use App\Service\SettingsService;

class AppController extends AbstractController {

  public function index(SessionInterface $session, GoogleService $googleService): Response {
    $client = $googleService->getClient();

    if ($session->get('access_token')) {
      // Authenticated
      $response = $this->render('home.html.twig', [
        'users' => [
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
        ],
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
    $client = $googleService->getClient();

    if ($request->query->get('code')) {
      $entityManager = $this->getDoctrine()->getManager();
      $code = $request->query->get('code');

      $token = $client->fetchAccessTokenWithAuthCode($code);
      $client->setAccessToken($token);
      $refresh_token = $client->getRefreshToken();
      $token_data = $client->verifyIdToken();


      $repository = $this->getDoctrine()->getRepository(User::class);
      $user = $repository->findOneBy(['google_id' => $token_data['sub']]);
      if ($user) {
        // Already created, don't create them.
        if (!$user->getRefreshToken() && $refresh_token) {
          $user->setRefreshToken($refresh_token);
        }
      }
      else {
        // Save refresh_token to database and create profile.
        $user = new User();
        $user->setGoogleId($token_data['sub']);
        $user->setMail($token_data['email']);
        $user->setRefreshToken($refresh_token);

        // tell Doctrine you want to (eventually) save the Product (no queries yet)
        $entityManager->persist($user);
      }
      
      // actually executes the queries (i.e. the INSERT query)
      $entityManager->flush();
      
      // Set token in session and redirect to home or profile.
      $session->set('access_token', $token);

      return $this->redirectToRoute('index');
    }

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

}
