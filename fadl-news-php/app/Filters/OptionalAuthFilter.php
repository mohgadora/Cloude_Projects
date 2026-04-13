<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Populates `$_SESSION['user']` lookup if the session already contains a user id,
 * and makes the current user available globally via service('currentUser').
 */
class OptionalAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        $userId  = $session->get('user_id');

        if ($userId) {
            $user = (new UserModel())
                ->select('id, username, email, role, avatar, is_active')
                ->find($userId);

            if ($user && (int) $user['is_active'] === 1) {
                $session->set('current_user', $user);
                return;
            }

            // Clean up invalid session
            $session->remove(['user_id', 'current_user']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
