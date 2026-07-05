<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            return redirect('/');
        }
        
        // Handle history deletion
        if ($request->has('delete_history_id')) {
            $historyId = (int)$request->query('delete_history_id');
            if ($historyId > 0) {
                DB::table('system_activity_logs')->where('id', $historyId)->delete();
                
                DB::table('system_activity_logs')->insert([
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'fullname' => $_SESSION['fullname'] ?? null,
                    'username' => $_SESSION['username'] ?? null,
                    'role' => 'admin',
                    'activity_type' => 'dashboard',
                    'activity' => 'Deleted one recent history entry (ID: ' . $historyId . ')',
                    'status' => 'success',
                    'created_at' => now()
                ]);
            }
            return redirect('/admin_dashboard.php?history=deleted');
        }

        $admin_id = $_SESSION['user_id'];
        $admin_data = DB::table('users')->where('id', $admin_id)->first();
        
        $school = DB::table('school_info')->first();
        $favicon = 'https://cdn-icons-png.flaticon.com/512/3064/3064197.png';
        if ($school && !empty($school->logo)) {
            $favicon = asset('uploads/logo/' . $school->logo);
        }

        $student_total = DB::table('students')->where('status', '!=', 'deleted')->count();
        $teacher_total = DB::table('teachers')->where('status', '!=', 'deleted')->count();
        
        $sms_sent_total = DB::table('system_activity_logs')
            ->where('activity_type', 'sms_broadcast')
            ->where('status', 'success')
            ->count();
            
        $review_total = DB::table('marks')->count();
        $pending_edit_requests = 0; // DB::table('marks_edit_requests')->where('status', 'pending')->count();
        
        $recent_history = DB::table('system_activity_logs')
            ->where('role', '!=', 'likindyadmin')
            ->orderBy('created_at', 'desc')
            ->limit(2)
            ->get();
            
        $today_income = DB::table('payments')
            ->whereRaw('paid_date = CURDATE()')
            ->sum('amount_paid');
            
        $month_income = DB::table('payments')
            ->whereRaw('MONTH(paid_date) = MONTH(CURDATE()) AND YEAR(paid_date) = YEAR(CURDATE())')
            ->sum('amount_paid');

        // Simple attendance aggregation
        $att_present = DB::table('student_attendance')->whereRaw('attendance_date = CURDATE()')->where('status', 'P')->count();
        $att_absent = DB::table('student_attendance')->whereRaw('attendance_date = CURDATE()')->where('status', 'A')->count();
        $att_sick = DB::table('student_attendance')->whereRaw('attendance_date = CURDATE()')->where('status', 'S')->count();

        // Convert object to array for legacy view compatibility
        if(is_object($admin_data)) {
            $admin_data = (array)$admin_data;
        }

        return view('admin_dashboard', compact(
            'favicon', 'admin_data', 'student_total', 'teacher_total',
            'sms_sent_total', 'review_total', 'pending_edit_requests',
            'recent_history', 'today_income', 'month_income',
            'att_present', 'att_absent', 'att_sick'
        ));
    }
}
