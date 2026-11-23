<?php

namespace App\Services;

use App\Models\Personnel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class FaceSampleEnrollmentService
{
    public function __construct(
        protected PythonFaceEnrollmentValidator $faceValidator,
        protected AuditLogService $auditLog,
        protected NotificationService $notifications
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array{ok:bool,message:string,encodings?:int,max_distance?:float}
     */
    public function saveThreeSamples(Personnel $personnel, array $files, string $auditAction, string $auditDetail, bool $requireActive = true): array
    {
        if (count($files) !== 3) {
            throw new RuntimeException('Complete all three blink captures before saving.');
        }

        if ($requireActive && ! $personnel->is_active) {
            throw new RuntimeException('Only active personnel can be enrolled.');
        }

        set_time_limit(300);

        $dir = (string) $personnel->id;
        $temporaryDir = 'camera-enrollment-'.$personnel->id.'-'.bin2hex(random_bytes(6));
        Storage::disk('faces')->makeDirectory($temporaryDir);
        $verification = ['max_distance' => null];

        try {
            foreach ($files as $index => $file) {
                $filename = sprintf(
                    'capture_%d_%s_%s.jpg',
                    $index + 1,
                    now()->format('Ymd_His'),
                    bin2hex(random_bytes(3))
                );
                $file->storeAs($temporaryDir, $filename, 'faces');
            }

            $temporaryFiles = Storage::disk('faces')->files($temporaryDir);
            sort($temporaryFiles);

            // Hostinger / shared hosting usually has no dlib face_recognition venv.
            // Still keep the blink-verified photos so registration works online;
            // the school attendance camera can re-check faces later.
            if ($this->faceValidator->isAvailable()) {
                $verification = $this->faceValidator->validate(
                    array_map(
                        fn (string $file): string => Storage::disk('faces')->path($file),
                        $temporaryFiles
                    ),
                    Storage::disk('faces')->path(''),
                    $personnel->id,
                    Personnel::query()->pluck('name', 'id')->all(),
                    $personnel->name
                );
            } else {
                Log::warning('Face enrollment saved without Python validation', [
                    'personnel_id' => $personnel->id,
                ]);
                $verification = [
                    'ok' => true,
                    'message' => 'Face photos saved. Python face validation is not installed on this server.',
                    'encodings' => 0,
                    'max_distance' => null,
                ];
            }

            if (Storage::disk('faces')->exists($dir)) {
                Storage::disk('faces')->deleteDirectory($dir);
            }
            Storage::disk('faces')->makeDirectory($dir);

            foreach (Storage::disk('faces')->files($temporaryDir) as $temporaryFile) {
                $name = basename($temporaryFile);
                Storage::disk('faces')->move($temporaryFile, $dir.'/'.$name);
            }
        } catch (RuntimeException $e) {
            Log::warning('Face enrollment rejected', [
                'personnel_id' => $personnel->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw new RuntimeException('Saving failed while checking the photos. Wait a moment and try again.');
        } finally {
            if (Storage::disk('faces')->exists($temporaryDir)) {
                Storage::disk('faces')->deleteDirectory($temporaryDir);
            }
        }

        $personnel->update([
            'face_dataset_dir' => storage_path('app/known_faces/'.$personnel->id),
        ]);

        $distanceNote = array_key_exists('max_distance', $verification) && $verification['max_distance'] !== null
            ? ' (maximum distance '.$verification['max_distance'].')'
            : ' (Python face validation skipped on this server)';

        $this->auditLog->log(
            $auditAction,
            $auditDetail.$distanceNote,
            $personnel
        );

        return $verification;
    }

    /**
     * Registration stores samples on an inactive personnel record.
     * Approval turns that record on so the kiosk can download the photos.
     * Choosing a different personnel moves the photos across when that person has none yet.
     */
    public function publishPendingRegistration(?int $fromPersonnelId, ?int $toPersonnelId): void
    {
        if (! $toPersonnelId) {
            return;
        }

        $target = Personnel::find($toPersonnelId);
        if (! $target) {
            return;
        }

        if ($fromPersonnelId && $fromPersonnelId !== $toPersonnelId) {
            $this->moveSamplesIfDestinationEmpty($fromPersonnelId, $target);
            $this->discardInactivePersonnel($fromPersonnelId);
        }

        $target->update([
            'is_active' => true,
            'deactivated_at' => null,
        ]);
    }

    public function discardPendingRegistration(Personnel $personnel): void
    {
        if ($personnel->is_active) {
            return;
        }

        if ($personnel->attendance()->exists() || $personnel->attendanceLogs()->exists()) {
            return;
        }

        $this->discardInactivePersonnel($personnel->id);
    }

    protected function moveSamplesIfDestinationEmpty(int $fromPersonnelId, Personnel $target): void
    {
        $sourceDir = (string) $fromPersonnelId;
        $targetDir = (string) $target->id;
        if (! Storage::disk('faces')->exists($sourceDir)) {
            return;
        }

        $destinationFiles = Storage::disk('faces')->exists($targetDir)
            ? Storage::disk('faces')->files($targetDir)
            : [];

        if ($destinationFiles === []) {
            Storage::disk('faces')->makeDirectory($targetDir);
            foreach (Storage::disk('faces')->files($sourceDir) as $file) {
                Storage::disk('faces')->move($file, $targetDir.'/'.basename($file));
            }
            $target->update([
                'face_dataset_dir' => storage_path('app/known_faces/'.$target->id),
            ]);
        }
    }

    protected function discardInactivePersonnel(int $personnelId): void
    {
        Storage::disk('faces')->deleteDirectory((string) $personnelId);

        $personnel = Personnel::find($personnelId);
        if (! $personnel || $personnel->is_active) {
            return;
        }

        if ($personnel->attendance()->exists() || $personnel->attendanceLogs()->exists()) {
            return;
        }

        $personnel->delete();
    }
}
