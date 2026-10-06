<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Client;
use App\Models\User;
use App\Models\Project;
use App\Models\ContentPlan;
use App\Models\Department;
use Spatie\Activitylog\Models\Activity;

class TrashController extends Controller
{
    /**
     * التحقق من صلاحية المدير
     */
    protected function checkManager(Request $request): ?JsonResponse
    {
        $role = $request->user()->role;
        $roleValue = is_object($role) && property_exists($role, 'value') ? $role->value : (string) $role;
        if ($roleValue !== 'manager') {
            return response()->json([
                'message' => 'غير مصرح لك بالوصول، سلة المهملات متاحة للمدير فقط.'
            ], 403);
        }
        return null;
    }

    /**
     * الحصول على الموديل المناسب وتسميته بالعربية
     */
    protected function getCategoryConfig(string $category): ?array
    {
        return match (strtolower(trim($category))) {
            'clients' => [
                'model' => Client::class,
                'singular' => 'عميل',
                'plural' => 'العملاء',
                'title_attr' => 'name',
            ],
            'users' => [
                'model' => User::class,
                'singular' => 'مستخدم',
                'plural' => 'المستخدمين',
                'title_attr' => 'name',
            ],
            'projects' => [
                'model' => Project::class,
                'singular' => 'مشروع',
                'plural' => 'المشاريع',
                'title_attr' => 'name',
            ],
            'plans', 'content_plans' => [
                'model' => ContentPlan::class,
                'singular' => 'خطة محتوى',
                'plural' => 'خطط المحتوى',
                'title_attr' => 'name',
            ],
            'departments' => [
                'model' => Department::class,
                'singular' => 'قسم',
                'plural' => 'الأقسام',
                'title_attr' => 'name',
            ],
            default => null,
        };
    }

    /**
     * إحصائيات العناصر المحذوفة في كل تصنيف
     */
    public function counts(Request $request): JsonResponse
    {
        if ($deny = $this->checkManager($request)) return $deny;

        $clients = Client::onlyTrashed()->count();
        $users = User::onlyTrashed()->count();
        $projects = Project::onlyTrashed()->count();
        $plans = ContentPlan::onlyTrashed()->count();
        $departments = Department::onlyTrashed()->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'clients' => $clients,
                'users' => $users,
                'projects' => $projects,
                'plans' => $plans,
                'departments' => $departments,
                'total' => $clients + $users + $projects + $plans + $departments,
            ]
        ]);
    }

    /**
     * جلب قائمة العناصر المحذوفة حسب التصنيف
     */
    public function index(Request $request, string $category): JsonResponse
    {
        if ($deny = $this->checkManager($request)) return $deny;

        $config = $this->getCategoryConfig($category);
        if (!$config) {
            return response()->json(['message' => 'التصنيف المطلوب غير موجود.'], 404);
        }

        $search = trim((string) $request->query('search', ''));
        $cat = strtolower(trim($category));

        if ($cat === 'clients') {
            $query = Client::onlyTrashed()->withCount(['contentPlans as plans_count']);
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phones', 'like', "%{$search}%")
                      ->orWhere('emails', 'like', "%{$search}%");
                });
            }
        } elseif ($cat === 'users') {
            $query = User::onlyTrashed()->with('departments');
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('job_title', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }
        } elseif ($cat === 'projects') {
            $query = Project::onlyTrashed()->with('departments')->withCount(['tasks']);
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
        } elseif ($cat === 'departments') {
            $query = Department::onlyTrashed()->withCount(['users', 'projects']);
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
        } else { // plans
            $query = ContentPlan::onlyTrashed()->with(['client'])->withCount(['posts']);
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('plan_type', 'like', "%{$search}%")
                      ->orWhereHas('client', function ($cq) use ($search) {
                          $cq->where('name', 'like', "%{$search}%");
                      });
                });
            }
        }

        $items = $query->orderByDesc('deleted_at')->paginate(25);

        return response()->json([
            'status' => 'success',
            'category' => $cat,
            'category_title' => $config['plural'],
            'data' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ]
        ]);
    }

    /**
     * استعادة عنصر محدد من سلة المهملات
     */
    public function restore(Request $request, string $category, int $id): JsonResponse
    {
        if ($deny = $this->checkManager($request)) return $deny;

        $config = $this->getCategoryConfig($category);
        if (!$config) {
            return response()->json(['message' => 'التصنيف المطلوب غير موجود.'], 404);
        }

        $modelClass = $config['model'];
        $item = $modelClass::onlyTrashed()->find($id);

        if (!$item) {
            return response()->json(['message' => 'العنصر غير موجود في سلة المهملات أو تم استعادته مسبقاً.'], 404);
        }

        $title = $item->{$config['title_attr']} ?? "#{$id}";
        $item->restore();

        // تسجيل في سجل الأنشطة
        activity()
            ->useLog('Trash')
            ->causedBy($request->user())
            ->performedOn($item)
            ->log("استعاد المدير {$config['singular']} ({$title}) من سلة المهملات.");

        return response()->json([
            'status' => 'success',
            'message' => "تمت استعادة {$config['singular']} ({$title}) بنجاح إلى النظام.",
            'data' => $item
        ]);
    }

    /**
     * حذف عنصر نهائياً من قاعدة البيانات (Force Delete)
     */
    public function forceDelete(Request $request, string $category, int $id): JsonResponse
    {
        if ($deny = $this->checkManager($request)) return $deny;

        $config = $this->getCategoryConfig($category);
        if (!$config) {
            return response()->json(['message' => 'التصنيف المطلوب غير موجود.'], 404);
        }

        $modelClass = $config['model'];
        $item = $modelClass::onlyTrashed()->find($id);

        if (!$item) {
            return response()->json(['message' => 'العنصر غير موجود في سلة المهملات.'], 404);
        }

        $title = $item->{$config['title_attr']} ?? "#{$id}";

        try {
            DB::transaction(function () use ($item) {
                $item->forceDelete();
            });

            // تسجيل في سجل الأنشطة
            activity()
                ->useLog('Trash')
                ->causedBy($request->user())
                ->log("حذف المدير {$config['singular']} ({$title}) نهائياً من قاعدة البيانات.");

            return response()->json([
                'status' => 'success',
                'message' => "تم حذف {$config['singular']} ({$title}) نهائياً من قاعدة البيانات ولا يمكن استعادته."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'تعذر الحذف النهائي لوجود بيانات وسجلات مرتبطة بهذا العنصر: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * استعادة جميع العناصر في تصنيف معين
     */
    public function restoreAll(Request $request, string $category): JsonResponse
    {
        if ($deny = $this->checkManager($request)) return $deny;

        $config = $this->getCategoryConfig($category);
        if (!$config) {
            return response()->json(['message' => 'التصنيف المطلوب غير موجود.'], 404);
        }

        $modelClass = $config['model'];
        $count = $modelClass::onlyTrashed()->count();

        if ($count === 0) {
            return response()->json(['message' => 'لا توجد عناصر محذوفة لاستعادتها في هذا القسم.'], 400);
        }

        $modelClass::onlyTrashed()->restore();

        activity()
            ->useLog('Trash')
            ->causedBy($request->user())
            ->log("استعاد المدير جميع عناصر {$config['plural']} ({$count} عنصر) من سلة المهملات.");

        return response()->json([
            'status' => 'success',
            'message' => "تمت استعادة جميع {$config['plural']} ({$count} عنصر) بنجاح.",
            'restored_count' => $count
        ]);
    }

    /**
     * تفريغ سلة المهملات لتصنيف معين نهائياً
     */
    public function emptyTrash(Request $request, string $category): JsonResponse
    {
        if ($deny = $this->checkManager($request)) return $deny;

        $config = $this->getCategoryConfig($category);
        if (!$config) {
            return response()->json(['message' => 'التصنيف المطلوب غير موجود.'], 404);
        }

        $modelClass = $config['model'];
        $items = $modelClass::onlyTrashed()->get();
        $count = $items->count();

        if ($count === 0) {
            return response()->json(['message' => 'سلة المهملات فارغة بالفعل لهذا القسم.'], 400);
        }

        try {
            DB::transaction(function () use ($items) {
                foreach ($items as $item) {
                    $item->forceDelete();
                }
            });

            activity()
                ->useLog('Trash')
                ->causedBy($request->user())
                ->log("أفرغ المدير سلة المهملات لقسم {$config['plural']} نهائياً ({$count} عنصر).");

            return response()->json([
                'status' => 'success',
                'message' => "تم تفريغ سلة المهملات لقسم {$config['plural']} نهائياً ({$count} عنصر).",
                'deleted_count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء التفريغ النهائي: ' . $e->getMessage()
            ], 422);
        }
    }
}
