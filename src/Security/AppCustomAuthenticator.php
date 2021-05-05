<?php

namespace App\Security;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use App\Service\GoogleService;
use App\Entity\User;

class AppCustomAuthenticator extends AbstractGuardAuthenticator
{
    public const LOGIN_ROUTE = 'authenticate';

    private $entityManager;
    private $googleService;
    private $urlGenerator;

    public function __construct(EntityManagerInterface $entityManager, GoogleService $googleService, UrlGeneratorInterface $urlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->googleService = $googleService;
        $this->urlGenerator = $urlGenerator;
    }

    public function supports(Request $request): bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route');
    }

    public function getCredentials(Request $request)
    {
        $code = $request->query->get('code');

        return [
            'code' => $code
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $client = $this->googleService->getClient();

        $token = $client->fetchAccessTokenWithAuthCode($credentials['code']);
        $client->setAccessToken($token);
        $refresh_token = $client->getRefreshToken();
        $token_data = $client->verifyIdToken();

        $repository = $this->entityManager->getRepository(User::class);
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
            $user->setUsername($token_data['email']);
            $user->setGoogleId($token_data['sub']);
            $user->setMail($token_data['email']);
            $user->setRefreshToken($refresh_token);

            // tell Doctrine you want to (eventually) save the Product (no queries yet)
            $this->entityManager->persist($user);
        }
        
        // actually executes the queries (i.e. the INSERT query)
        $this->entityManager->flush();
        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // TODO Could check for a contest secret to limit registrations.
        return true;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        // todo
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey)
    {
        return new RedirectResponse($this->urlGenerator->generate('index'));
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        // todo
    }

    public function supportsRememberMe()
    {
        // todo
    }
}
