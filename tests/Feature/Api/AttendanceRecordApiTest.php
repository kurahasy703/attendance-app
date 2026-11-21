<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceRecord;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\BreakTime;
use App\Models\StampCorrectionRequest;
use App\Models\StampCorrectionRequestBreak;

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
    /**
     * 認証していないユーザーでも勤怠一覧を取得できる
     */
    public function test_guest_can_get_attendance_list(): void
    {
        // テスト用ユーザーを作成
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // テスト用勤怠記録を作成
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // ログインせずに勤怠一覧APIへアクセス
        $response = $this->getJson('/api/v1/attendance-records');

        // 正常に取得できることを確認
        $response->assertStatus(200);

        // 作成した勤怠記録が含まれていることを確認
        $response->assertJsonFragment([
            'id' => $attendance->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);

        $response->assertJsonMissingPath('data.0.user');

        // ページ分割に必要な項目があることを確認
        $response->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
    }
    /**
     * user_idで指定したユーザーの勤怠だけを取得できる
     */
    public function test_can_filter_attendance_by_user_id(): void
    {
        // 2人のユーザーを作成
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // それぞれの勤怠を作成
        $attendanceA = AttendanceRecord::create([
            'user_id' => $userA->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceRecord::create([
            'user_id' => $userB->id,
            'date' => '2026-11-18',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        // ユーザーAの勤怠だけを検索
        $response = $this->getJson(
            '/api/v1/attendance-records?user_id=' . $userA->id
        );

        // 正常に取得できることを確認
        $response->assertStatus(200);

        // ユーザーAの勤怠だけが返ることを確認
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $attendanceA->id);
        $response->assertJsonPath('data.0.user_id', $userA->id);
    }
    /**
     * dateで指定した日付の勤怠だけを取得できる
     */
    public function test_can_filter_attendance_by_date(): void
    {
        // テスト用ユーザーを作成
        $user = User::factory()->create();

        // 11月18日の勤怠を作成
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 11月19日の勤怠を作成
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-19',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 11月18日の勤怠だけを検索
        $response = $this->getJson(
            '/api/v1/attendance-records?date=2026-11-18'
        );

        // 正常に取得できることを確認
        $response->assertStatus(200);

        // 11月18日の勤怠だけが1件返ることを確認
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $attendance->id);
        $response->assertJsonPath('data.0.date', '2026-11-18');
    }
    /**
     * monthで指定した月の勤怠だけを取得できる
     */
    public function test_can_filter_attendance_by_month(): void
    {
        // テスト用ユーザーを作成
        $user = User::factory()->create();

        // 10月の勤怠を作成
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 11月の勤怠を作成
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 11月の勤怠だけを検索
        $response = $this->getJson(
            '/api/v1/attendance-records?month=2026-11'
        );

        // 正常に取得できることを確認
        $response->assertStatus(200);

        // 11月の勤怠だけが1件返ることを確認
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $attendance->id);
        $response->assertJsonPath('data.0.date', '2026-11-18');
    }
    /**
     * 勤怠一覧を指定件数でページ分割できる
     */
    public function test_can_paginate_attendance_records(): void
    {
        // テスト用ユーザーを作成
        $user = User::factory()->create();

        // 3日分の勤怠を作成
        foreach (['2026-11-16', '2026-11-17', '2026-11-18'] as $date) {
            AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => $date,
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);
        }

        // 1ページ目を取得（1ページ2件）
        $page1 = $this->getJson(
            '/api/v1/attendance-records?per_page=2&page=1'
        );

        // 2ページ目を取得
        $page2 = $this->getJson(
            '/api/v1/attendance-records?per_page=2&page=2'
        );

        // 両方とも正常に取得できる
        $page1->assertStatus(200);
        $page2->assertStatus(200);

        // 1ページ目は2件、2ページ目は1件
        $page1->assertJsonCount(2, 'data');
        $page2->assertJsonCount(1, 'data');

        // 全体の件数とページ情報を確認
        $page1->assertJsonPath('meta.total', 3);
        $page1->assertJsonPath('meta.current_page', 1);
        $page2->assertJsonPath('meta.current_page', 2);
    }
    /**
     * 一般ユーザーは他人の勤怠を更新できない
     */
    public function test_user_cannot_update_another_users_attendance(): void
    {
        // 勤怠の所有者
        $owner = User::factory()->create([
            'admin_status' => false,
        ]);

        // 別の一般ユーザー
        $otherUser = User::factory()->create([
            'admin_status' => false,
        ]);

        // 所有者の勤怠記録
        $attendance = AttendanceRecord::create([
            'user_id' => $owner->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '更新前',
        ]);

        // 別のユーザーとして認証
        Sanctum::actingAs($otherUser);

        // 他人の勤怠を更新しようとする
        $response = $this->patchJson(
            "/api/v1/attendance-records/{$attendance->id}",
            [
                'comment' => '不正な更新',
            ]
        );

        // 403エラーになることを確認
        $response->assertStatus(403);

        // エラーメッセージを確認
        $response->assertJson([
            'error' => 'この操作を実行する権限がありません。',
        ]);

        // データが変更されていないことを確認
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'comment' => '更新前',
        ]);
    }
    /**
     * 管理者は他のユーザーの勤怠を更新できる
     */
    public function test_admin_can_update_another_users_attendance(): void
    {
        // 一般ユーザーを作成
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // 管理者を作成
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーの勤怠を作成
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '更新前',
        ]);

        // 管理者として認証
        Sanctum::actingAs($admin);

        // 一般ユーザーの勤怠を更新
        $response = $this->patchJson(
            "/api/v1/attendance-records/{$attendance->id}",
            [
                'comment' => '管理者が更新',
            ]
        );

        // 更新成功を確認
        $response->assertStatus(200);
        $response->assertJsonPath('data.comment', '管理者が更新');

        // データベースにも反映されていることを確認
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'comment' => '管理者が更新',
        ]);
    }
    /**
     * 管理者は他のユーザーの勤怠を削除できる
     */
    public function test_admin_can_delete_another_users_attendance(): void
    {
        // 一般ユーザーを作成
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // 管理者を作成
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // 一般ユーザーの勤怠を作成
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 管理者として認証
        Sanctum::actingAs($admin);

        // 一般ユーザーの勤怠を削除
        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        // 削除成功（HTTP 204）を確認
        $response->assertStatus(204);

        // データベースから削除されていることを確認
        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendance->id,
        ]);
    }
    /**
     * 勤怠を削除すると関連する休憩記録も削除される
     */
    public function test_deleting_attendance_also_deletes_breaks(): void
    {
        // テスト用ユーザーを作成
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // 勤怠記録を作成
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 関連する休憩記録を作成
        $break = BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // 勤怠の所有者として認証
        Sanctum::actingAs($user);

        // 勤怠記録を削除
        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        // 削除成功を確認
        $response->assertStatus(204);

        // 勤怠記録が削除されたことを確認
        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendance->id,
        ]);

        // 関連する休憩記録も削除されたことを確認
        $this->assertDatabaseMissing('breaks', [
            'id' => $break->id,
        ]);
    }
    /**
     * 勤怠削除時に修正申請と申請用休憩記録も削除される
     */
    public function test_deleting_attendance_also_deletes_correction_requests_and_breaks(): void
    {
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $request = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正申請',
            'status' => 'pending',
        ]);

        $requestBreak = StampCorrectionRequestBreak::create([
            'stamp_correction_request_id' => $request->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        $response->assertStatus(204);

        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendance->id,
        ]);

        $this->assertDatabaseMissing('stamp_correction_requests', [
            'id' => $request->id,
        ]);

        $this->assertDatabaseMissing('stamp_correction_request_breaks', [
            'id' => $requestBreak->id,
        ]);
    }
    /**
     * 同じユーザーが同じ日付の勤怠を重複登録できない
     */
    public function test_user_cannot_store_duplicate_attendance_date(): void
    {
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-18',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date']);

        $response->assertJsonPath(
            'errors.date.0',
            'この日付の勤怠は既に登録されています。'
        );

        $this->assertDatabaseCount('attendance_records', 1);
    }
    /**
     * 出勤時刻が未入力の場合は422エラーになる
     */
    public function test_clock_in_is_required_when_storing_attendance(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-18',
            'clock_out' => '18:00:00',
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['clock_in']);

        $response->assertJsonPath(
            'errors.clock_in.0',
            '出勤時刻は必須です。'
        );

        $this->assertDatabaseCount('attendance_records', 0);
    }
    /**
     * 出勤時刻の形式が不正な場合は422エラーになる
     */
    public function test_clock_in_invalid_format_returns_422(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-18',
            'clock_in' => '09:00',
            'clock_out' => '18:00:00',
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['clock_in']);

        $response->assertJsonPath(
            'errors.clock_in.0',
            '出勤時刻は HH:MM:SS 形式で指定してください。'
        );

        $this->assertDatabaseCount('attendance_records', 0);
    }
    /**
     * 備考が255文字を超える場合は422エラーになる
     */
    public function test_comment_over_255_characters_returns_422(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => str_repeat('あ', 256),
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['comment']);

        $response->assertJsonPath(
            'errors.comment.0',
            '備考は 255 文字以内で入力してください。'
        );

        $this->assertDatabaseCount('attendance_records', 0);
    }
    /**
     * 勤怠更新時に同じユーザーの日付が重複すると422になる
     */
    public function test_update_duplicate_date_returns_422(): void
    {
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-19',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson(
            "/api/v1/attendance-records/{$attendance->id}",
            ['date' => '2026-11-19']
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['date']);

        $response->assertJsonPath(
            'errors.date.0',
            'この日付の勤怠は既に登録されています。'
        );

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'date' => '2026-11-18',
        ]);
    }
    /**
     * 未認証ユーザーは勤怠を削除できない
     */
    public function test_unauthenticated_user_cannot_delete_attendance(): void
    {
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-11-18',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        $response->assertStatus(401);

        $response->assertJsonPath(
            'message',
            'Unauthenticated.'
        );

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
        ]);
    }
}
