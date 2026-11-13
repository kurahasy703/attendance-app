<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\StampCorrectionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\BreakTime;
use App\Models\StampCorrectionRequestBreak;

class AdminStampCorrectionRequestTest extends TestCase
{
    use RefreshDatabase;

    // 承認済みの申請は再承認できない
    public function test_approved_request_cannot_be_approved_again(): void
    {
        // ① 管理者を作成
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // ② 一般ユーザーを作成
        $user = User::factory()->create();

        // ③ 勤怠記録を作成
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-13',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ④ 承認済みの修正申請を作成
        $application = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'status' => 'approved',
            'date' => '2026-11-13',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'comment' => '修正済み',
        ]);

        // ⑤ 管理者として承認処理を実行
        $response = $this->actingAs($admin)->post(
            '/stamp_correction_request/approve/' . $application->id
        );

        // ⑥ 409エラーになることを確認
        $response->assertStatus(409);

        // ⑦ 元の勤怠記録が変更されていないことを確認
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }
    public function test_general_user_cannot_approve_request(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-13',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $application = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'status' => 'pending',
            'date' => '2026-11-13',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'comment' => '修正希望',
        ]);

        $response = $this->actingAs($user)->post(
            '/stamp_correction_request/approve/' . $application->id
        );

        $response->assertStatus(403);

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'status' => 'pending',
        ]);
    }
    // 管理者が承認待ちの修正申請を正常に承認できる
    public function test_admin_can_approve_pending_request(): void
    {
        // ① 管理者と一般ユーザーを作成
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // ② 修正前の勤怠記録を作成
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-13',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前',
        ]);

        // ③ 承認待ちの修正申請を作成
        $application = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'status' => 'pending',
            'date' => '2026-11-13',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'comment' => '修正後',
        ]);

        // ④ 管理者として承認処理を実行
        $response = $this->actingAs($admin)->post(
            '/stamp_correction_request/approve/' . $application->id
        );
        $response->assertSessionHasNoErrors();

        // ⑤ 承認後は管理者の勤怠詳細画面へ移動する
        $response->assertRedirect(
            route('admin.stamp_correction_request.show', [
                'id' => $application->id,
            ])
        );

        // ⑥ 申請状態が承認済みになっていることを確認
        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'status' => 'approved',
        ]);

        // ⑦ 勤怠記録が修正内容に更新されたことを確認
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'comment' => '修正後',
        ]);
    }
    // 承認すると休憩時間が修正内容に更新される
    public function test_admin_approval_updates_break_times(): void
    {
        // ① 管理者と一般ユーザーを作成
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // ② 元の勤怠記録を作成
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-13',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ③ 修正前の休憩時間を作成
        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // ④ 承認待ちの修正申請を作成
        $application = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'status' => 'pending',
            'date' => '2026-11-13',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '休憩時間の修正',
        ]);

        // ⑤ 申請された新しい休憩時間を作成
        StampCorrectionRequestBreak::create([
            'stamp_correction_request_id' => $application->id,
            'break_in' => '12:30:00',
            'break_out' => '13:30:00',
        ]);

        // ⑥ 管理者として承認処理を実行
        $response = $this->actingAs($admin)->post(
            '/stamp_correction_request/approve/' . $application->id
        );

        // ⑦ 申請が承認済みになったことを確認
        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'status' => 'approved',
        ]);

        // ⑧ 新しい休憩時間が保存されていることを確認
        $this->assertDatabaseHas('breaks', [
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:30:00',
            'break_out' => '13:30:00',
        ]);

        // ⑨ 古い休憩時間が残っていないことを確認
        $this->assertDatabaseMissing('breaks', [
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }
}
