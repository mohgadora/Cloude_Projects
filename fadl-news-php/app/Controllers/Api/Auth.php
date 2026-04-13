<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;

class Auth extends BaseController
{
    public function login()
    {
        $body     = $this->request->getJSON(true) ?? $this->request->getPost();
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'اسم المستخدم وكلمة المرور مطلوبان']);
        }

        $user = (new UserModel())->findByLogin($username);
        if (! $user || ! password_verify($password, $user['password'])) {
            return $this->response->setStatusCode(401)
                ->setJSON(['error' => 'بيانات الدخول غير صحيحة']);
        }

        (new UserModel())->touchLogin((int) $user['id']);

        $publicUser = [
            'id'       => (int) $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'role'     => $user['role'],
            'avatar'   => $user['avatar'] ?? null,
        ];

        session()->regenerate();
        session()->set([
            'user_id'      => $publicUser['id'],
            'current_user' => $publicUser,
        ]);

        // Compatibility cookie read by the existing admin HTML pages (it only
        // checks for the PRESENCE of a `token` cookie to toggle the UI).
        $this->response->setCookie([
            'name'     => 'token',
            'value'    => '1',
            'expire'   => 7 * 24 * 60 * 60,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        return $this->response->setJSON([
            'success' => true,
            'user'    => $publicUser,
        ]);
    }

    public function logout()
    {
        session()->destroy();
        $this->response->deleteCookie('token');
        return $this->response->setJSON(['success' => true]);
    }

    public function me()
    {
        return $this->response->setJSON(['user' => $this->currentUser()]);
    }

    public function changePassword()
    {
        $body            = $this->request->getJSON(true) ?? $this->request->getPost();
        $currentPassword = (string) ($body['currentPassword'] ?? '');
        $newPassword     = (string) ($body['newPassword'] ?? '');

        if ($currentPassword === '' || $newPassword === '') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'جميع الحقول مطلوبة']);
        }
        if (strlen($newPassword) < 8) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل']);
        }

        $me   = $this->currentUser();
        $user = (new UserModel())->find($me['id']);

        if (! password_verify($currentPassword, $user['password'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'كلمة المرور الحالية غير صحيحة']);
        }

        (new UserModel())->update($me['id'], [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح',
        ]);
    }
}
