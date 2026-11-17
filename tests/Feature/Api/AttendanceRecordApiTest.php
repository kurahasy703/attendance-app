<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceRecord;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;


class AttendanceRecordApiTest extends TestCase
{
    use RefreshDatabase;
    /**
     * 存在しない勤怠IDを指定すると404が返る
     */
    public function test_attendance_record_not_found(): void
    {
        // 存在しない勤怠IDを指定してAPIにアクセスする
        $response = $this->getJson('/api/v1/attendance-records/999999');

        $response->assertStatus(404);

        $response->assertJson([
            'error' => '勤怠情報が見つかりませんでした。',
        ]);
    }
    /**
     * 認証していないユーザーは勤怠を登録できない
     */
    public function test_unauthenticated_user_cannot_store_attendance(): void
    {
        // トークンなしで勤怠登録APIにアクセスする
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // HTTP 401が返ることを確認する
        $response->assertStatus(401);

        // エラーメッセージを確認する
        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    }
    /**
     * 他人の勤怠記録は削除できない
     */
    public function test_user_cannot_delete_another_users_attendance(): void
    {
        // 勤怠の所有者を作成する
        $owner = User::factory()->create([
            'admin_status' => false,
        ]);

        // 別の一般ユーザーを作成する
        $otherUser = User::factory()->create([
            'admin_status' => false,
        ]);

        // 所有者の勤怠記録を作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $owner->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 別のユーザーとして認証する
        Sanctum::actingAs($otherUser);

        // 他人の勤怠記録を削除しようとする
        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        // 権限エラーが返ることを確認する
        $response->assertStatus(403);

        $response->assertJson([
            'error' => 'この操作を実行する権限がありません。',
        ]);

        // 勤怠記録が削除されていないことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
        ]);
    }
    /**
     * 退勤時刻が出勤時刻より前の場合は422を返す
     */
    public function test_clock_out_before_clock_in_returns_422(): void
    {
        // 一般ユーザーを作成する
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // そのユーザーの勤怠記録を作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 勤怠の所有者として認証する
        Sanctum::actingAs($user);

        // 出勤時刻より前の退勤時刻を送信する
        $response = $this->patchJson(
            "/api/v1/attendance-records/{$attendance->id}",
            [
                'clock_out' => '08:00:00',
            ]
        );

        // HTTP 422が返ることを確認する
        $response->assertStatus(422);

        // clock_outに入力エラーがあることを確認する
        $response->assertJsonValidationErrors(['clock_out']);

        // 不正な退勤時刻に更新されていないことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_out' => '18:00:00',
        ]);
    }
    /**
     * 勤怠詳細を正常に取得できる
     */
    public function test_can_show_attendance_record(): void
    {
        // テスト用ユーザーを作成する
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // テスト用の勤怠記録を作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'GETテスト',
        ]);

        // 勤怠詳細APIにアクセスする
        $response = $this->getJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        // HTTP 200が返ることを確認する
        $response->assertStatus(200);

        // 正しい勤怠情報が返ることを確認する
        $response->assertJsonPath('data.id', $attendance->id);
        $response->assertJsonPath('data.user_id', $user->id);
        $response->assertJsonPath('data.comment', 'GETテスト');

        // API共通の項目が含まれていることを確認する
        $response->assertJsonStructure([
            'data' => [
                'id',
                'user_id',
                'user',
                'date',
                'clock_in',
                'clock_out',
                'total_time',
                'total_break_time',
                'comment',
                'breaks',
                'applications',
            ],
        ]);
    }
    /**
     * 認証済みユーザーは勤怠を登録できる
     */
    public function test_authenticated_user_can_store_attendance(): void
    {
        // ① テスト用の一般ユーザーを作成する
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // ② このユーザーとして認証する
        Sanctum::actingAs($user);

        // ③ 新しい勤怠情報をAPIに送信する
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'POSTテスト',
        ]);

        // ④ 登録成功のHTTP 201が返ることを確認する
        $response->assertStatus(201);

        // ⑤ 登録した情報がJSONに含まれることを確認する
        $response->assertJsonPath('data.user_id', $user->id);
        $response->assertJsonPath('data.comment', 'POSTテスト');

        // ⑥ データベースに正しく保存されたことを確認する
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'POSTテスト',
        ]);
    }
    /**
     * 認証済みユーザーは自分の勤怠を更新できる
     */
    public function test_user_can_update_own_attendance(): void
    {
        // ① テスト用ユーザーを作成する
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // ② 更新前の勤怠記録を作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '更新前',
        ]);

        // ③ 勤怠記録の所有者として認証する
        Sanctum::actingAs($user);

        // ④ APIから備考を更新する
        $response = $this->patchJson(
            "/api/v1/attendance-records/{$attendance->id}",
            [
                'comment' => '更新後',
            ]
        );

        // ⑤ HTTP 200が返ることを確認する
        $response->assertStatus(200);

        // ⑥ APIの返却データが更新されていることを確認する
        $response->assertJsonPath('data.comment', '更新後');

        // ⑦ データベースにも反映されていることを確認する
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'comment' => '更新後',
        ]);
    }
    /**
     * 認証済みユーザーは自分の勤怠を削除できる
     */
    public function test_user_can_delete_own_attendance(): void
    {
        // ① テスト用ユーザーを作成する
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // ② 削除対象の勤怠記録を作成する
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ③ 勤怠記録の所有者として認証する
        Sanctum::actingAs($user);

        // ④ APIから削除を実行する
        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        // ⑤ HTTP 204が返ることを確認する
        $response->assertStatus(204);

        // ⑥ データベースから削除されたことを確認する
        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendance->id,
        ]);
    }
}
