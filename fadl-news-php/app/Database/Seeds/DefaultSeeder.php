<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DefaultSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();

        // Default categories
        if ((int) $db->table('categories')->countAllResults() === 0) {
            $db->table('categories')->insertBatch([
                ['name' => 'أخبار محلية',   'slug' => 'local',         'description' => 'أخبار السودان والشأن الداخلي',    'color' => '#1565C0', 'icon' => 'home',         'sort_order' => 1],
                ['name' => 'أخبار دولية',   'slug' => 'international', 'description' => 'أخبار العالم والأحداث الدولية',   'color' => '#2E7D32', 'icon' => 'globe',        'sort_order' => 2],
                ['name' => 'اقتصاد',         'slug' => 'economy',       'description' => 'الأخبار الاقتصادية والمالية',      'color' => '#E65100', 'icon' => 'trending-up',  'sort_order' => 3],
                ['name' => 'رياضة',          'slug' => 'sports',        'description' => 'أخبار الرياضة والمنافسات',          'color' => '#6A1B9A', 'icon' => 'activity',     'sort_order' => 4],
                ['name' => 'تقنية',          'slug' => 'tech',          'description' => 'أخبار التكنولوجيا والعلوم',         'color' => '#00838F', 'icon' => 'cpu',          'sort_order' => 5],
                ['name' => 'ثقافة وفنون',   'slug' => 'culture',       'description' => 'الثقافة والأدب والفنون',            'color' => '#AD1457', 'icon' => 'book-open',    'sort_order' => 6],
                ['name' => 'صحة',            'slug' => 'health',        'description' => 'أخبار الصحة والطب',                  'color' => '#1B5E20', 'icon' => 'heart',        'sort_order' => 7],
                ['name' => 'رأي',            'slug' => 'opinion',       'description' => 'مقالات الرأي والتحليل',              'color' => '#4E342E', 'icon' => 'edit-3',       'sort_order' => 8],
            ]);
        }

        // Default settings
        if ((int) $db->table('settings')->countAllResults() === 0) {
            $defaults = [
                ['setting_key' => 'site_name',          'value' => getenv('site.name') ?: 'مدونة فضل محمد خير'],
                ['setting_key' => 'site_tagline',       'value' => getenv('site.tagline') ?: 'الخبر الصادق والرأي الحر'],
                ['setting_key' => 'site_description',   'value' => 'موقع إخباري متخصص في أخبار السودان والعالم، يقدم الخبر الصادق والتحليل الموضوعي'],
                ['setting_key' => 'site_keywords',      'value' => 'أخبار السودان, فضل محمد خير, أخبار عربية, تحليلات'],
                ['setting_key' => 'contact_email',      'value' => 'info@fadlnews.com'],
                ['setting_key' => 'facebook_url',       'value' => ''],
                ['setting_key' => 'twitter_url',        'value' => ''],
                ['setting_key' => 'linkedin_url',       'value' => ''],
                ['setting_key' => 'logo_url',           'value' => ''],
                ['setting_key' => 'auto_post_facebook', 'value' => '0'],
                ['setting_key' => 'auto_post_twitter',  'value' => '0'],
                ['setting_key' => 'auto_post_linkedin', 'value' => '0'],
            ];
            $db->table('settings')->insertBatch($defaults);
        }

        // Default admin user
        if ((int) $db->table('users')->countAllResults() === 0) {
            $adminUsername = getenv('admin.username') ?: 'admin';
            $adminEmail    = getenv('admin.email') ?: 'admin@fadlnews.com';
            $adminPassword = getenv('admin.password') ?: 'Admin@123456';

            $db->table('users')->insert([
                'username' => $adminUsername,
                'email'    => $adminEmail,
                'password' => password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]),
                'role'     => 'admin',
            ]);
        }
    }
}
