<?php
/**
 * This software is Copyright (c) 2013 The Regents of the University of
 * California. All Rights Reserved. Permission to copy, modify, and distribute this
 * software and its documentation for academic research and education purposes,
 * without fee, and without a written agreement is hereby granted, provided that
 * the above copyright notice, this paragraph and the following three paragraphs
 * appear in all copies. Permission to make use of this software for other than
 * academic research and education purposes may be obtained by contacting:
 *
 * Office of Innovation and Commercialization
 * 9500 Gilman Drive, Mail Code 0910
 * University of California
 * La Jolla, CA 92093-0910
 * (858) 534-5815
 * invent@ucsd.edu
 *
 * This software program and documentation are copyrighted by The Regents of the
 * University of California. The software program and documentation are supplied
 * "as is", without any accompanying services from The Regents. The Regents does
 * not warrant that the operation of the program will be uninterrupted or
 * error-free. The end-user understands that the program was developed for research
 * purposes and is advised not to rely exclusively on the program for any reason.
 *
 * IN NO EVENT SHALL THE UNIVERSITY OF CALIFORNIA BE LIABLE TO ANY PARTY FOR
 * DIRECT, INDIRECT, SPECIAL, INCIDENTAL, OR CONSEQUENTIAL DAMAGES, INCLUDING LOST
 * PROFITS, ARISING OUT OF THE USE OF THIS SOFTWARE AND ITS DOCUMENTATION, EVEN IF
 * THE UNIVERSITY OF CALIFORNIA HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE. THE UNIVERSITY OF CALIFORNIA SPECIFICALLY DISCLAIMS ANY WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
 * FITNESS FOR A PARTICULAR PURPOSE. THE SOFTWARE PROVIDED HEREUNDER IS ON AN "AS
 * IS" BASIS, AND THE UNIVERSITY OF CALIFORNIA HAS NO OBLIGATIONS TO PROVIDE
 * MAINTENANCE, SUPPORT, UPDATES, ENHANCEMENTS, OR MODIFICATIONS.
 */

namespace App\Security;

use Auth0\JWTAuthBundle\Security\Auth0Service;
use Auth0\JWTAuthBundle\Security\Core\JWTUserProviderInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Security\Core\User\UserInterface;

class UserProvider implements JWTUserProviderInterface
{
    protected $auth0Service;
    protected $cache;

    public function __construct(Auth0Service $auth0Service)
    {
        $this->auth0Service = $auth0Service;
        // TODO: replace this with a better cache
        $this->cache = new FilesystemAdapter();
    }

    public function loadUserByJWT($jwt)
    {
        // check the cache first
        // TODO: replace this with local user profile, authorization info etc.
        $cachedProfile = $this->cache->getItem('A0Profile|'.$jwt->sub.md5($jwt->token));
        if (!$cachedProfile->isHit()) {
            $profile = $this->auth0Service->getUserProfileByA0UID($jwt->token, $jwt->sub);
            $cachedProfile->expiresAfter(300);
            $cachedProfile->set($profile);
            $this->cache->save($cachedProfile);
        } else {
            $profile = $cachedProfile->get();
        }

        // NOTE: What we (Auth0) call 'permissions', symfony calls 'roles'
        // also, Symfony requires roles start with ROLE_ (sigh)
        $roles = [];
        if (array_key_exists('https://hicube.caida.org/auth', $profile) &&
            array_key_exists('permissions', $profile['https://hicube.caida.org/auth'])) {
            $perms = $profile['https://hicube.caida.org/auth']['permissions'];
            foreach ($perms as $perm) {
                $roles[] = "ROLE_$perm";
            }
        }
        return new User($jwt, $roles);
    }

    public function getAnonymousUser()
    {
        return new AnonymousUser();
    }

    /**
     * Symfony calls this method if you use features like switch_user
     * or remember_me.
     *
     * @var $username
     *
     * @throws \Exception
     */
    public function loadUserByUsername($username)
    {
        // Load a User object from your data source or throw UsernameNotFoundException.
        // The $username argument may not actually be a username:
        // it is whatever value is being returned by the getUsername()
        // method in your User class.
        throw new \Exception('Unsupported method');
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called. Your job is to make sure the user's data is still fresh by,
     * for example, re-querying for fresh User data.
     *
     * If your firewall is "stateless: false" (for a pure API), this
     * method is not called.
     *
     * @var $user
     *
     * @throws \Exception
     */
    public function refreshUser(UserInterface $user)
    {
        throw new \Exception('Unsupported method');
    }

    /**
     * Tells Symfony to use this provider for this User class.
     *
     * @var $class
     * @return bool
     */
    public function supportsClass($class)
    {
        return User::class === $class;
    }
}
