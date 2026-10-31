<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsCampaign;
use App\Services\SmsCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SmsCampaignController extends Controller
{
    public function index(): JsonResponse
    {
        $campaigns = SmsCampaign::with('creator:id,name')->latest('id')->paginate(20);

        return response()->json([
            'campaigns' => $campaigns->getCollection()->map(fn (SmsCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'message' => $c->message,
                'segment_type' => $c->segment_type,
                'segment_label' => $c->segment_label,
                'status' => $c->status,
                'status_label' => $c->status_label,
                'total_recipients' => $c->total_recipients,
                'sent_count' => $c->sent_count,
                'failed_count' => $c->failed_count,
                'skipped_count' => $c->skipped_count,
                'created_by' => $c->creator?->name,
                'created_at' => $c->created_at->format('Y-m-d H:i'),
                'completed_at' => $c->completed_at?->format('Y-m-d H:i'),
            ])->values(),
            'pagination' => [
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'total' => $campaigns->total(),
            ],
            'daily_limit' => (int) config('sms.campaign_daily_limit'),
        ]);
    }

    public function preview(Request $request, SmsCampaignService $service): JsonResponse
    {
        $segment = $this->validateSegment($request);

        return response()->json([
            'count' => $service->count($segment['type'], $segment['days'], $segment['min_total']),
        ]);
    }

    public function store(Request $request, SmsCampaignService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'message' => 'required|string|max:400',
        ] + $this->segmentRules());

        $segment = $this->validateSegment($request);

        $count = $service->count($segment['type'], $segment['days'], $segment['min_total']);

        if ($count === 0) {
            return response()->json([
                'message' => 'هیچ مشتری واجد شرایطی برای این بخش‌بندی وجود ندارد.',
            ], 422);
        }

        $campaign = $service->create([
            'name' => $data['name'],
            'message' => $data['message'],
            'segment_type' => $segment['type'],
            'segment_days' => $segment['days'],
            'segment_min_total' => $segment['min_total'],
        ], $request->user());

        return response()->json([
            'message' => "کمپین ثبت شد و برای {$campaign->total_recipients} مشتری در صف ارسال است.",
            'campaign_id' => $campaign->id,
        ], 201);
    }

    public function cancel(SmsCampaign $campaign, SmsCampaignService $service): JsonResponse
    {
        if ($campaign->status !== 'active') {
            return response()->json(['message' => 'فقط کمپین در حال ارسال قابل لغو است.'], 422);
        }

        $service->cancel($campaign);

        app(\App\Services\AuditLogger::class)->record('campaign_cancelled', $campaign, 'لغو کمپین پیامکی');

        return response()->json(['message' => 'کمپین لغو شد.']);
    }

    private function segmentRules(): array
    {
        return [
            'segment_type' => ['required', Rule::in(['all', 'inactive', 'big_spenders'])],
            'segment_days' => 'required_if:segment_type,inactive|nullable|integer|min:1|max:3650',
            'segment_min_total' => 'required_if:segment_type,big_spenders|nullable|numeric|min:0',
        ];
    }

    /**
     * @return array{type: string, days: ?int, min_total: ?float}
     */
    private function validateSegment(Request $request): array
    {
        $data = $request->validate($this->segmentRules());

        return [
            'type' => $data['segment_type'],
            'days' => $data['segment_type'] === 'inactive' ? (int) $data['segment_days'] : null,
            'min_total' => $data['segment_type'] === 'big_spenders' ? (float) $data['segment_min_total'] : null,
        ];
    }
}
