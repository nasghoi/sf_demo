<?php

namespace App\Security;

use App\Entity\User as LocalUser;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class LoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private LdapInterface $ldap,
        private UserRepository $userRepository,
        private UserProviderInterface $localUserProvider,
        private UserPasswordHasherInterface $passwordHasher,
        private RouterInterface $router,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private string $ldapBaseDn,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && $request->attributes->get('_route') === 'app_login';
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('_username', '');
        $password = $request->request->get('_password', '');
        $csrfToken = $request->request->get('_csrf_token', '');

        // Shared flag: tracks whether LDAP bind succeeded so that
        // CustomCredentials knows NOT to re-verify the password hash.
        $ldapAuthenticated = false;

        return new Passport(
            new UserBadge($username, function (string $userIdentifier) use ($password, &$ldapAuthenticated) {
                // 1. Try LDAP first
                try {
                    $dn = sprintf('uid=%s,%s', $userIdentifier, $this->ldapBaseDn);
                    $this->ldap->bind($dn, $password);

                    // LDAP bind succeeded — provision or load the matching local entity
                    $ldapAuthenticated = true;
                    return $this->userRepository->findOrCreateByUsername($userIdentifier);
                } catch (ConnectionException | \Exception $e) {
                    // LDAP failed — fall through to local DB
                }

                // 2. Fall back to local DB user
                return $this->localUserProvider->loadUserByIdentifier($userIdentifier);
            }),
            new CustomCredentials(function (string $password, $user) use (&$ldapAuthenticated) {
                // LDAP bind already verified credentials — skip hash check
                if ($ldapAuthenticated) {
                    return true;
                }

                // Local DB user — verify stored password hash
                if ($user instanceof LocalUser) {
                    return $this->passwordHasher->isPasswordValid($user, $password);
                }

                return false;
            }, $password),
            [new CsrfTokenBadge('authenticate', $csrfToken)]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('app_staff_index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set('_security.last_error', $exception);
        $request->getSession()->set('_security.last_username', $request->request->get('_username', ''));

        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
