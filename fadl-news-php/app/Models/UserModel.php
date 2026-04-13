<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'username', 'email', 'password', 'role', 'avatar', 'is_active', 'last_login',
    ];

    public function findByLogin(string $login): ?array
    {
        return $this->where('is_active', 1)
            ->groupStart()
                ->where('username', $login)
                ->orWhere('email', $login)
            ->groupEnd()
            ->first();
    }

    public function touchLogin(int $id): void
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->update(['last_login' => date('Y-m-d H:i:s')]);
    }
}
