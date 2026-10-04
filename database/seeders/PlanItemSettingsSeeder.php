<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlanItemCategory;
use App\Models\PlanItemEstimate;

class PlanItemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'name' => 'Digital Marketing Strategy Plan',
                'description' => 'خطة الاستراتيجية والتسويق الرقمي الشامل',
                'sort_order' => 1,
                'items' => []
            ],
            [
                'name' => 'Content Marketing Plan',
                'description' => 'خطة صناعة ونشر المحتوى المكتوب والمرئي الثابت',
                'sort_order' => 2,
                'items' => [
                    ['name' => 'Static Posts', 'estimated_hours' => 2.0],
                    ['name' => 'Whitepapers', 'estimated_hours' => 8.0],
                    ['name' => 'E-books', 'estimated_hours' => 12.0],
                    ['name' => 'Stories', 'estimated_hours' => 1.0],
                    ['name' => 'Lead Magnets', 'estimated_hours' => 5.0],
                    ['name' => 'Email Newsletters', 'estimated_hours' => 3.0],
                    ['name' => 'SEO Articles', 'estimated_hours' => 4.0],
                    ['name' => 'Blog Posts', 'estimated_hours' => 3.5],
                    ['name' => 'Multi-image', 'estimated_hours' => 3.0],
                    ['name' => 'Carousels', 'estimated_hours' => 3.0],
                    ['name' => 'Single Images', 'estimated_hours' => 1.5],
                ]
            ],
            [
                'name' => 'Media Production Plan',
                'description' => 'خطة إنتاج الفيديو والموشن جرافيك والوسائط التفاعلية',
                'sort_order' => 3,
                'items' => [
                    ['name' => '(Reels / TikToks / Shorts): عالي الجودة', 'estimated_hours' => 6.0],
                    ['name' => '(Reels / TikToks / Shorts): متوسط الجودة', 'estimated_hours' => 4.0],
                    ['name' => '(Reels / TikToks / Shorts): منخفض الجودة', 'estimated_hours' => 2.5],
                    ['name' => '(Long-form Videos / YouTube / Podcast)', 'estimated_hours' => 10.0],
                    ['name' => '(2D / 3D Motion Graphics / AI)', 'estimated_hours' => 8.0],
                ]
            ],
            [
                'name' => 'Media Buying Plan',
                'description' => 'خطة إدارة الحملات الإعلانية الممولة والميزانيات',
                'sort_order' => 4,
                'items' => []
            ],
            [
                'name' => 'SEO Plan',
                'description' => 'خطة تحسين محركات البحث والكلمات المفتاحية',
                'sort_order' => 5,
                'items' => []
            ],
            [
                'name' => 'Reporting & Analytics Plan',
                'description' => 'خطة التقارير الشهرية والتحليلات البيانية',
                'sort_order' => 6,
                'items' => []
            ],
        ];

        foreach ($data as $catIndex => $catData) {
            $category = PlanItemCategory::firstOrCreate(
                ['name' => $catData['name']],
                [
                    'description' => $catData['description'],
                    'sort_order' => $catData['sort_order'],
                ]
            );

            foreach ($catData['items'] as $itemIndex => $itemData) {
                PlanItemEstimate::firstOrCreate(
                    [
                        'category_id' => $category->id,
                        'name' => $itemData['name']
                    ],
                    [
                        'estimated_hours' => $itemData['estimated_hours'],
                        'unit' => 'hour',
                        'sort_order' => $itemIndex + 1,
                    ]
                );
            }
        }
    }
}
