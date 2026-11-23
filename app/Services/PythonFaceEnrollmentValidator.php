<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class PythonFaceEnrollmentValidator
{
    public function isAvailable(): bool
    {
        return is_file($this->pythonBinary()) && is_file($this->scriptPath());
    }

    public function pythonBinary(): string
    {
        $configured = trim((string) config('smartdtr.face_python_path', ''));
        $candidates = array_values(array_unique(array_filter([
            $configured !== '' ? $configured : null,
            base_path('.venv-face/bin/python'),
            base_path('.venv-face/bin/python3'),
            base_path('.venv-face/Scripts/python.exe'),
        ])));

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $configured !== '' ? $configured : base_path('.venv-face/Scripts/python.exe');
    }

    public function scriptPath(): string
    {
        return base_path('scripts/validate_face_enrollment.py');
    }

    /**
     * @param  list<string>  $imagePaths
     * @param  array<int|string, string>  $personnelNames
     * @return array{ok:bool,message:string,encodings?:int,max_distance?:float|null}
     */
    public function validate(
        array $imagePaths,
        ?string $knownFacesDir = null,
        ?int $excludePersonnelId = null,
        array $personnelNames = [],
        ?string $enrollingName = null
    ): array
    {
        if (count($imagePaths) !== 3) {
            throw new RuntimeException('Exactly three face captures are required.');
        }

        $python = $this->pythonBinary();
        $script = $this->scriptPath();

        if (! is_file($python)) {
            throw new RuntimeException('The Python face-recognition environment is not installed.');
        }
        if (! is_file($script)) {
            throw new RuntimeException('The face enrollment validation script is missing.');
        }

        $process = new Process([$python, $script, ...$imagePaths], base_path());
        $process->setEnv([
            'FACE_ENROLLMENT_TOLERANCE' => (string) config('smartdtr.face_enrollment_tolerance', 0.55),
            'FACE_ENROLLMENT_CONFLICT_TOLERANCE' => (string) config('smartdtr.face_enrollment_conflict_tolerance', 0.38),
            'FACE_ENROLLMENT_DETECTOR' => (string) config('smartdtr.face_enrollment_detector', 'cnn'),
            'FACE_KNOWN_DIR' => (string) ($knownFacesDir ?: ''),
            'FACE_EXCLUDE_ID' => $excludePersonnelId !== null ? (string) $excludePersonnelId : '',
            'FACE_ENROLLING_NAME' => (string) ($enrollingName ?: ''),
            'FACE_KNOWN_NAMES_JSON' => json_encode($personnelNames, JSON_UNESCAPED_UNICODE) ?: '{}',
        ]);
        $process->setTimeout(300);
        $process->run();

        $output = trim($process->getOutput());
        $result = json_decode($output, true);
        if (! is_array($result) || ! isset($result['ok'])) {
            throw new RuntimeException(
                'Python face validation failed. '.trim($process->getErrorOutput())
            );
        }
        if (! $process->isSuccessful() || ! $result['ok']) {
            throw new RuntimeException((string) ($result['message'] ?? 'Face validation failed.'));
        }

        return $result;
    }
}
