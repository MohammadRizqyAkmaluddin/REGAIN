<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordRelapseRequest;
use App\Http\Requests\UpdateSmokingProfileRequest;
use App\Services\SmokingTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmokingController extends Controller
{
    public function __construct(
        private readonly SmokingTrackerService $smokingTracker,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->smokingTracker->getSummary($request->user()));
    }

    public function updateProfile(UpdateSmokingProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->smokingTracker->getOrCreateProfile($user);
        $data = $request->validated();

        $profile->update([
            'quit_date' => $data['quit_date'],
            'packs_per_week' => number_format((float) $data['packs_per_week'], 2, '.', ''),
            'price_per_pack' => number_format((float) $data['price_per_pack'], 2, '.', ''),
            'cigarettes_per_pack' => (int) $data['cigarettes_per_pack'],
            'is_active' => $data['is_active'] ?? $profile->is_active,
        ]);

        return response()->json($this->smokingTracker->getSummary($user));
    }

    public function recordRelapse(RecordRelapseRequest $request): JsonResponse
    {
        $user = $request->user();
        $event = $this->smokingTracker->recordRelapse($user, $request->validated());

        return response()->json([
            'event' => $event,
            'message' => 'Tidak apa-apa, pemulihan adalah sebuah perjalanan. Progres historismu tetap tersimpan — mari lanjutkan lagi hari ini.',
            'summary' => $this->smokingTracker->getSummary($user),
        ], 201);
    }

    public function reports(Request $request): JsonResponse
    {
        return response()->json($this->smokingTracker->getReports($request->user()));
    }
}
