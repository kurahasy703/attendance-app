<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use App\Models\BreakTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StampCorrectionRequestController extends Controller
{
    // 管理者の修正申請一覧を表示する
    public function index()
    {
        // 全スタッフの修正申請を取得する
        $applications = StampCorrectionRequest::with([
            'attendanceRecord.user',
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        // Bladeが必要とする表示用の情報を追加する
        $applications->each(function ($application) {

            // pending / approved を日本語に変換
            $application->approval_status =
                $application->status === 'approved'
                ? '承認済み'
                : '承認待ち';

            // 勤怠記録から申請者を取得
            $application->setRelation(
                'user',
                $application->attendanceRecord->user
            );

            // Bladeで使われている名前に合わせる
            $application->setRelation(
                'AttendanceRecord',
                $application->attendanceRecord
            );

            // 申請日時
            $application->application_date = $application->created_at;
        });

        return view('admin.admin-application-list', compact('applications'));
    }
    // 管理者：修正申請の詳細画面
    public function show($id)
    {
        // 修正申請と関連する勤怠・休憩データを取得
        $application = StampCorrectionRequest::with([
            'attendanceRecord.user',
            'stampCorrectionRequestBreaks',
        ])->findOrFail($id);

        // 申請者を取得
        $user = $application->attendanceRecord->user;

        // Bladeで必要な日付・時刻を用意
        $application->new_date = Carbon::parse($application->date);

        $application->new_clock_in = Carbon::parse(
            $application->clock_in
        )->format('H:i');

        $application->new_clock_out = Carbon::parse(
            $application->clock_out
        )->format('H:i');

        // 申請された休憩記録をBladeで参照できるようにする
        $application->setRelation(
            'proposalBreaks',
            $application->stampCorrectionRequestBreaks
        );

        // 承認状態を日本語に変換
        $application->approval_status =
            $application->status === 'approved'
            ? '承認済み'
            : '承認待ち';

        return view('admin.admin-application-detail', compact(
            'application',
            'user'
        ));
    }

    // 管理者：修正申請を承認する
    public function approve($id)
    {
        DB::transaction(function () use ($id) {

            // ① 申請を取得し、同時更新を防ぐ
            $application = StampCorrectionRequest::with([
                'attendanceRecord',
                'stampCorrectionRequestBreaks',
            ])->lockForUpdate()->findOrFail($id);

            // ② 承認待ちの申請だけ処理する
            abort_unless($application->status === 'pending', 409);

            // ③ 元の勤怠記録を取得する
            $attendance = $application->attendanceRecord;

            // ④ 申請された出勤・退勤時刻を反映
            $attendance->update([
                'clock_in' => $application->clock_in,
                'clock_out' => $application->clock_out,
                'comment' => $application->comment,
            ]);

            // ⑤ 既存の休憩記録を削除する
            BreakTime::where('attendance_record_id', $attendance->id)
                ->delete();

            // ⑥ 申請された休憩記録を保存する
            foreach ($application->stampCorrectionRequestBreaks as $break) {
                BreakTime::create([
                    'attendance_record_id' => $attendance->id,
                    'break_in' => $break->break_in,
                    'break_out' => $break->break_out,
                ]);
            }

            // ⑦ 申請を承認済みに変更する
            $application->update([
                'status' => 'approved',
            ]);
        });

        // 承認後は詳細画面に戻る
        return redirect()->route('admin.stamp_correction_request.show', [
            'id' => $id,
        ]);
    }
}
