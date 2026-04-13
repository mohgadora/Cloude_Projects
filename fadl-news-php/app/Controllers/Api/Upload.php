<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

class Upload extends BaseController
{
    public function image()
    {
        $file = $this->request->getFile('image');
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'لم يتم رفع ملف']);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext     = strtolower($file->getExtension());
        if (! in_array($ext, $allowed, true)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'نوع الملف غير مسموح. يُقبل: JPG, PNG, WEBP, GIF']);
        }

        $name = 'img_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $file->move(FCPATH . 'uploads', $name);

        return $this->response->setJSON([
            'success' => true,
            'url'     => '/uploads/' . $name,
        ]);
    }
}
