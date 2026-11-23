<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Services\FaceSampleEnrollmentService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaceEnrollmentController extends Controller
{
    public function __construct(
        protected FaceSampleEnrollmentService $faceSamples,
        protected NotificationService $notifications
    ) {}

    public function show(): View
    {
        $staff = $this->staffUser();
        $personnel = $this->linkedPersonnel($staff);

        return view('staff.faces', [
            'staff' => $staff,
            'personnel' => $personnel,
            'savedCount' => $personnel?->faceSampleCount() ?? 0,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $staff = $this->staffUser();
        $personnel = $this->linkedPersonnel($staff);

        if (! $personnel) {
            $message = 'Your account is not linked to a personnel record yet. Ask HR to activate and link your account before registering your face.';

            return $this->enrollmentFailed($request, $message);
        }

        abort_unless($personnel->is_active, 422, 'Only active personnel can enroll a face.');

        $request->validate([
            'images' => ['required', 'array', 'size:3'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpeg,jpg', 'max:5120', 'dimensions:min_width=320,min_height=240'],
            'blink_verifications' => ['required', 'array', 'size:3'],
            'blink_verifications.*' => ['required', 'accepted'],
        ], [
            'images.size' => 'Complete all three blink captures before saving.',
            'images.required' => 'Complete all three blink captures before saving.',
            'blink_verifications.size' => 'Each of the three captures must have its own verified blink.',
            'blink_verifications.*.accepted' => 'A fresh blink is required for every capture.',
        ]);

        try {
            $this->faceSamples->saveThreeSamples(
                $personnel,
                $request->file('images'),
                'face.staff_self_enrolled',
                "{$staff->name} registered three blink-verified face samples from the Employee Portal"
            );
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);

            return $this->enrollmentFailed($request, 'Your face samples could not be saved because of a server problem. Please try again later or contact HR.');
        } catch (\RuntimeException $e) {
            return $this->enrollmentFailed($request, $e->getMessage());
        }

        $this->notifications->notifyAllAdmins(
            'Employee face registered',
            "{$staff->name} registered face samples for the attendance kiosk.",
            route('admin.hr.faces')
        );

        $message = 'Your face was registered. The Raspberry Pi attendance camera will recognize you shortly.';
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'sample_count' => $personnel->fresh()->faceSampleCount(),
                'redirect' => route('staff.faces'),
            ]);
        }

        return redirect()
            ->route('staff.faces')
            ->with('success', $message);
    }

    private function linkedPersonnel($staff): ?Personnel
    {
        if (! $staff->personnel_id) {
            return null;
        }

        return Personnel::find($staff->personnel_id);
    }

    private function enrollmentFailed(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 422);
        }

        return back()->with('error', $message);
    }
}
