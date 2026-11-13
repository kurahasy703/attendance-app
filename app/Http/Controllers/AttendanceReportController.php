<?php

namespace App\Http\Controllers;

class AttendanceReportController extends Controller
{
    // PG14：マイ勤怠レポート画面を表示する
    public function index()
    {
        return view('user.user-attendance-report');
    }
}
