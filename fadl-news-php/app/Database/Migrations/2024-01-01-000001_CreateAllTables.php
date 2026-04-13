<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAllTables extends Migration
{
    public function up()
    {
        // users
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 190],
            'password'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'editor'],
            'avatar'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP')],
            'last_login' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // categories
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'color'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => '#1565C0'],
            'icon'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'newspaper'],
            'sort_order'  => ['type' => 'INT', 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP')],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->createTable('categories', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // articles
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'title'           => ['type' => 'VARCHAR', 'constraint' => 500],
            'slug'            => ['type' => 'VARCHAR', 'constraint' => 255],
            'content'         => ['type' => 'MEDIUMTEXT'],
            'excerpt'         => ['type' => 'TEXT', 'null' => true],
            'image_url'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'image_caption'   => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'category_id'     => ['type' => 'INT', 'null' => true],
            'tags'            => ['type' => 'TEXT', 'null' => true],
            'author_id'       => ['type' => 'INT'],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'published'],
            'featured'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'breaking'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'views'           => ['type' => 'INT', 'default' => 0],
            'seo_title'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'seo_description' => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP')],
            'updated_at'      => ['type' => 'DATETIME', 'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP')],
            'published_at'    => ['type' => 'DATETIME', 'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP')],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey('status');
        $this->forge->addKey('category_id');
        $this->forge->addKey('featured');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('category_id', 'categories', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('author_id', 'users', 'id', '', 'RESTRICT');
        $this->forge->createTable('articles', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // settings
        $this->forge->addField([
            'setting_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'value'       => ['type' => 'TEXT', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'), 'on update' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP')],
        ]);
        $this->forge->addPrimaryKey('setting_key');
        $this->forge->createTable('settings', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);

        // newsletters
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'auto_increment' => true],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 500],
            'articles_ids' => ['type' => 'TEXT', 'null' => true],
            'template'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'classic'],
            'created_by'   => ['type' => 'INT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP')],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('newsletters', true, ['ENGINE' => 'InnoDB', 'DEFAULT CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci']);
    }

    public function down()
    {
        $this->forge->dropTable('newsletters', true);
        $this->forge->dropTable('settings', true);
        $this->forge->dropTable('articles', true);
        $this->forge->dropTable('categories', true);
        $this->forge->dropTable('users', true);
    }
}
