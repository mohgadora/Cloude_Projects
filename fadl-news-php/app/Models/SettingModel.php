<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'setting_key';
    protected $returnType    = 'array';
    protected $useAutoIncrement = false;
    protected $useTimestamps = false;
    protected $allowedFields = ['setting_key', 'value'];

    /**
     * Return all settings as an associative array.
     */
    public function asMap(): array
    {
        $rows = $this->findAll();
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['setting_key']] = $r['value'];
        }
        return $map;
    }

    public function upsert(string $key, ?string $value): void
    {
        $this->db->query(
            'INSERT INTO settings (setting_key, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, $value]
        );
    }
}
