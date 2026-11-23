<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use App\Services\FaceSampleEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaceEnrollmentController extends Controller
{
    public function __construct(
        protected FaceSampleEnrollmentService $faceSamples
    ) {}

    public function index(): View
    {
        $personnel = Personnel::active()->orderBy('name')->get();

        return view('admin.hr.faces', compact('personnel'));
    }

    public function enroll(Personnel $personnel): View
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }
        abort_unless($personnel->is_active, 422, 'Only active personnel can be enrolled.');

        return view('admin.hr.face-enroll', [
            'personnel' => $personnel,
            'savedCount' => $personnel->faceSampleCount(),
        ]);
    }

    public function store(Request $request, Personnel $personnel): RedirectResponse|JsonResponse
    {
        if (! $this->adminUser()->canWrite()) {
            abort(403);
        }
        abort_unless($personnel->is_active, 422, 'Only active personnel can be enrolled.');

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
            $verification = $this->faceSamples->saveThreeSamples(
                $personnel,
                $request->file('images'),
                'face.camera_enrolled',
                "Admin captured and Python-verified three face samples for {$personnel->name}"
            );
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);

            return $this->enrollmentFailed($request, 'The face samples could not be saved because of a database error. Check storage/logs/laravel.log for details.');
        } catch (\RuntimeException $e) {
            return $this->enrollmentFailed($request, $e->getMessage());
        }

        $message = "{$personnel->name}'s three face samples were saved. The Raspberry Pi will sync them shortly.";
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'sample_count' => $personnel->fresh()->faceSampleCount(),
                'redirect' => route('admin.hr.faces'),
            ]);
        }

        return redirect()
            ->route('admin.hr.faces')
            ->with('success', $message);
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
