<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminApiFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = session()->get('current_user');
        if (! $user) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['error' => 'غير مصرح']);
        }
        if (($user['role'] ?? null) !== 'admin') {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['error' => 'صلاحيات المدير مطلوبة']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
