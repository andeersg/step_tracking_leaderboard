<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\GoogleService;
use App\Entity\User;
use App\Service\SettingsService;
use App\Repository\UserRepository;
use App\Form\Type\UserEdit;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class AppController extends AbstractController {

  public function index(Request $request, GoogleService $googleService, UserRepository $userRepo, LoggerInterface $logger): Response {
    $client = $googleService->getClient();

    if ($this->isGranted('ROLE_USER')) {
      $loggedin_user = $this->getUser();
      $output_data = [];
      $best = 0;
      $total_steps = 0;
      $this_user_steps = 0;

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

        if ($loggedin_user->getId() === $user->getId()) {
          $this_user_steps = $stepsTaken;
        }

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

      $profile_form = $this->createForm(UserEdit::class, $loggedin_user, [
        'action' => $this->generateUrl('save_profile'),
      ]);

      // Authenticated
      $this_user_steps = 0;
      $response = $this->render('home.html.twig', [
        'users' => $output_data,
        'total_steps' => number_format($total_steps, 0, ',', '.'),
        'total_distance' => number_format($total_steps * 0.0008, 0, ',', '.'),
        'profile_form' => $profile_form->createView(),
        'show_help' => $this_user_steps === 0,
      ]);
    }
    else {
      $code = $request->query->get('code');

      // Show login.
      $response = $this->render('login.html.twig', [
        'login_url' => $client->createAuthUrl(),
        'show_login' => TRUE, //$code === 'kg35',
      ]);
    }

    return $response;
  }

  public function authenticate(SessionInterface $session, Request $request, GoogleService $googleService) {
    // Logic is handled in the Authenticator.
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

  /**
   * @Route("/page/{slug}", name="page")
   */
  public function mdPage(string $slug, KernelInterface $appKernel, LoggerInterface $logger)
  {
    $projectRoot = $appKernel->getProjectDir();
    $finder = new Finder();

    $finder->in($projectRoot . '/pages')->files()->name('*.md');

    foreach ($finder as $file) {
      $name = $file->getFilename();
      if ($name == $slug . '.md') {
        $yamlObject = YamlFrontMatter::parse($file->getContents());

        $title = $yamlObject->matter('title');
        $content = $yamlObject->body();

        return $this->render('page.html.twig', [
          'page_title' => $title,
          'title' => $title,
          'content' => $content,
          'debug' => $content,
        ]);
      }
    }

    throw $this->createNotFoundException('The page does not exist');
  }


}
