<?php

namespace Tests\Feature;

use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    // トップページから勤怠画面へ移動することを確認
    public function test_root_redirects_to_attendance(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/attendance');
    }
}
