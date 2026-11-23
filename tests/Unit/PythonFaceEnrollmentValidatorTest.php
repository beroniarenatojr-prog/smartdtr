<?php

namespace Tests\Unit;

use App\Services\PythonFaceEnrollmentValidator;
use Tests\TestCase;

class PythonFaceEnrollmentValidatorTest extends TestCase
{
    public function test_is_available_is_false_when_python_binary_is_missing(): void
    {
        config(['smartdtr.face_python_path' => base_path('.venv-face-missing/bin/python')]);

        $validator = new PythonFaceEnrollmentValidator;

        $this->assertFalse($validator->isAvailable());
    }
}
