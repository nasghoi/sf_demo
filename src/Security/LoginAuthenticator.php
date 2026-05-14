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
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
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
        private string $ldapAdminDn,
        private string $ldapAdminPassword,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && $request->attributes->get('_route') === 'app_login';
    }

    public function authenticate(Request $request): Passport
    {
        // Step 1: Get credentials from form
        $userInput = trim($request->request->get('_username', ''));
        $password  = $request->request->get('_password', '');
        $csrfToken = $request->request->get('_csrf_token', '');

        // Step 2: Strip domain — keep the full email as the LDAP search value
        // but normalise: if the user typed only a username, keep as-is.
        $mailIdentifier = $userInput; // e.g. "ali@example.com" or plain "ali"

        // Shared flag so CustomCredentials skips the local hash check after a
        // successful LDAP user-bind.
        $ldapAuthenticated = false;

        return new Passport(
            new UserBadge($mailIdentifier, function (string $userIdentifier) use ($password, &$ldapAuthenticated) {
                // ----------------------------------------------------------
                // Step 3: Admin bind → CN=intranet,DC=sentra,DC=gov
                // ----------------------------------------------------------
                try {
                    $this->ldap->bind($this->ldapAdminDn, $this->ldapAdminPassword);
                } catch (ConnectionException | \Exception $e) {
                    // Cannot reach LDAP at all — fall through to local DB
                    return $this->localUserProvider->loadUserByIdentifier($userIdentifier);
                }

                // ----------------------------------------------------------
                // Step 4: Search for the user by mail attribute
                //         (mail=ali@example.com)
                // ----------------------------------------------------------
                $query = $this->ldap->query(
                    $this->ldapBaseDn,
                    sprintf('(mail=%s)', ldap_escape($userIdentifier, '', LDAP_ESCAPE_FILTER))
                );
                $results = $query->execute()->toArray();

                if (count($results) === 0) {
                    // No LDAP user found — fall back to local DB
                    return $this->localUserProvider->loadUserByIdentifier($userIdentifier);
                }

                // ----------------------------------------------------------
                // Step 5: Get the found user's DN, then re-bind with
                //         user DN + password to verify credentials
                // ----------------------------------------------------------
                $userEntry = $results[0];
                $userDn    = $userEntry->getDn();

                try {
                    $this->ldap->bind($userDn, $password);
                } catch (ConnectionException | \Exception $e) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                }

                // ----------------------------------------------------------
                // Step 6: Bind succeeded — provision or load local entity
                // ----------------------------------------------------------
                $ldapAuthenticated = true;

                return $this->userRepository->findOrCreateByUsername($userIdentifier);
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
        // Step 6: Redirect to dashboard on success
        // TODO: change 'app_staff_index' to your dashboard route once created
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
