<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BlogStatus;
use App\Enums\InquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\BlogResource;
use App\Models\ActivityLog;
use App\Models\Blog;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $counts = Blog::query()
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(views), 0) as views')
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($row) => $row->status->value);

        $published = $counts->get(BlogStatus::Published->value);
        $drafts = $counts->get(BlogStatus::Draft->value);

        return response()->json([
            'stats' => [
                'total' => (int) $counts->sum('total'),
                'published' => (int) ($published->total ?? 0),
                'drafts' => (int) ($drafts->total ?? 0),
                'views' => (int) ($published->views ?? 0),
            ],
            'inquiries' => [
                'new' => Inquiry::query()->where('status', InquiryStatus::New)->count(),
                'total' => Inquiry::query()->count(),
            ],
            'monthly' => $this->monthly(),
            'recent' => BlogResource::collection(
                Blog::query()->with(['category:id,name,slug', 'author:id,name,image_path'])->latest('updated_at')->limit(5)->get()
            ),
            'activity' => ActivityResource::collection(
                ActivityLog::query()->with('user:id,name')->latest('created_at')->latest('id')->limit(10)->get()
            ),
        ]);
    }

    /** Posts published (and their views) in each of the last 6 months. */
    private function monthly(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $start->copy()->addMonths($i);
            $months[$month->format('Y-m')] = ['month' => $month->format('Y-m'), 'label' => $month->format('M'), 'count' => 0, 'views' => 0];
        }

        Blog::query()
            ->published()
            ->where('published_at', '>=', $start)
            ->get(['published_at', 'views'])
            ->each(function (Blog $blog) use (&$months) {
                $key = $blog->published_at->format('Y-m');
                if (isset($months[$key])) {
                    $months[$key]['count']++;
                    $months[$key]['views'] += $blog->views;
                }
            });

        return array_values($months);
    }
}
