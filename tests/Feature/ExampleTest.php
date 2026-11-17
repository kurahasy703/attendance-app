<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_to_attendance(): void
    {
        // トップページにアクセスする
        $response = $this->get('/');

        // 勤怠登録画面へのリダイレクトを確認する
        $response->assertRedirect(route('attendance.index'));
    }
}
