<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\SettingModel;
use App\Models\UserModel;

class Users extends BaseController
{
    public function index()
    {
        $users = (new UserModel())
            ->select('id, username, email, role, is_active, created_at, last_login')
            ->orderBy('created_at', 'DESC')
            ->findAll();
        return $this->response->setJSON(['users' => $users]);
    }

    public function create()
    {
        $body     = $this->request->getJSON(true) ?? $this->request->getPost();
        $username = trim((string) ($body['username'] ?? ''));
        $email    = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $role     = (string) ($body['role'] ?? 'editor');

        if ($username === '' || $email === '' || $password === '') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'جميع الحقول مطلوبة']);
        }
        if (strlen($password) < 8) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل']);
        }

        $um = new UserModel();
        try {
            $um->insert([
                'username' => $username,
                'email'    => $email,
                'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'role'     => $role,
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '1062') || stripos($e->getMessage(), 'Duplicate') !== false) {
                return $this->response->setStatusCode(400)
                    ->setJSON(['error' => 'اسم المستخدم أو البريد الإلكتروني موجود مسبقاً']);
            }
            throw $e;
        }

        $user = $um->select('id, username, email, role, is_active, created_at')->find($um->getInsertID());
        return $this->response->setJSON(['success' => true, 'user' => $user]);
    }

    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();
        $um   = new UserModel();
        $user = $um->find($id);
        if (! $user) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'المستخدم غير موجود']);
        }

        $data = [
            'username'  => $body['username'] ?? $user['username'],
            'email'     => $body['email']    ?? $user['email'],
            'role'      => $body['role']     ?? $user['role'],
            'is_active' => isset($body['is_active']) ? (int) $body['is_active'] : (int) $user['is_active'],
        ];

        if (! empty($body['password']) && strlen($body['password']) >= 8) {
            $data['password'] = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        try {
            $um->update($id, $data);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '1062') || stripos($e->getMessage(), 'Duplicate') !== false) {
                return $this->response->setStatusCode(400)
                    ->setJSON(['error' => 'اسم المستخدم أو البريد الإلكتروني موجود مسبقاً']);
            }
            throw $e;
        }

        $updated = $um->select('id, username, email, role, is_active, created_at')->find($id);
        return $this->response->setJSON(['success' => true, 'user' => $updated]);
    }

    public function delete(int $id)
    {
        $me = $this->currentUser();
        if ((int) $id === (int) $me['id']) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'لا يمكنك حذف حسابك الخاص']);
        }
        (new UserModel())->delete($id);
        return $this->response->setJSON(['success' => true]);
    }

    public function allCategories()
    {
        $rows = (new CategoryModel())
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
        return $this->response->setJSON(['categories' => $rows]);
    }

    public function getSettings()
    {
        return $this->response->setJSON(['settings' => (new SettingModel())->asMap()]);
    }

    public function saveSettings()
    {
        $body = $this->request->getJSON(true) ?? $this->request->getPost();
        $sm   = new SettingModel();
        $db   = \Config\Database::connect();
        $db->transStart();
        foreach ((array) $body as $k => $v) {
            $sm->upsert((string) $k, is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
        }
        $db->transComplete();
        return $this->response->setJSON(['success' => true]);
    }
}
