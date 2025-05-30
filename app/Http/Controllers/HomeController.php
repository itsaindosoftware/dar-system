<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Role;
use App\Permission;
use App\Module;
use App\User;
use App\Requestdar;
use DB;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        if (\Auth::user()->hasRole('admin')) {
            $role = Role::count();
            $permission = Permission::count();
            $module = Module::count();
            $user = User::count();
            return view('admin-dashboard.home', [
                'user' => $user,
                'role' => $role,
                'permission' => $permission,
                'module' => $module
            ]);

        } elseif (\Auth::user()->hasRole('user-employee')) {
            $getData = Requestdar::where('nik_req', \Auth::user()->nik)->count();

            // Get user information
            $usersInfo = DB::connection('dar-system')
                ->table('users')
                ->leftJoin('companys', 'users.company_id', '=', 'companys.id')
                ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                ->leftJoin('positions', 'users.position_id', '=', 'positions.id')
                ->where('nik', \Auth::user()->nik)->first();

            // Get monthly requests
            $monthlyRequests = Requestdar::where('nik_req', \Auth::user()->nik)
                ->whereMonth('created_date', date('m'))
                ->whereYear('created_date', date('Y'))
                ->count();


            return view('users-dashboard.user-employe.home', [
                'data' => $getData,
                'users' => $usersInfo,
                'monthlyRequests' => $monthlyRequests
            ]);

        } elseif (\Auth::user()->hasRole('manager')) {
            $usersInfo = DB::connection('dar-system')
                ->table('users')
                ->leftJoin('companys', 'users.company_id', '=', 'companys.id')
                ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                ->leftJoin('positions', 'users.position_id', '=', 'positions.id')
                ->where('nik', Auth::user()->nik)->first();
            $getData = Requestdar::where('nik_atasan', Auth::user()->nik)->count();
            $pendingCount = Requestdar::where('nik_atasan', Auth::user()->nik)->where('approval_status1', '0')->count();
            $approvedCount = Requestdar::where('nik_atasan', Auth::user()->nik)->where('approval_status1', '1')->count();
            $rejectedCount = Requestdar::where('nik_atasan', Auth::user()->nik)->where('approval_status1', '2')->count();
            return view('users-dashboard.user-manager.home', [
                'totalDar' => $getData,
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
                'users' => $usersInfo
            ]);
        } elseif (Auth::user()->hasRole('sysdev')) {
            $getData = Requestdar::get()->count();
            $pendingCount = Requestdar::where('approval_status1', '0')->count();
            $approvedCount = Requestdar::where('approval_status1', '1')->count();
            $rejectedCount = Requestdar::where('approval_status1', '2')->count();
            $monthlyTrend = [];
            $monthLabels = [];

            // Get the current date
            $now = Carbon::now();

            // Loop through the last 6 months
            for ($i = 5; $i >= 0; $i--) {
                $month = $now->copy()->subMonths($i);
                $monthLabels[] = $month->format('M Y');

                $count = Requestdar::whereYear('created_date', $month->year)
                    ->whereMonth('created_date', $month->month)
                    ->count();

                $monthlyTrend[] = $count;
            }

            // Get department distribution data
            $departmentDistribution = DB::connection('dar-system')
                ->table('request_dar')
                ->join('users', 'request_dar.nik_req', '=', 'users.nik')
                ->join('departments', 'users.department_id', '=', 'departments.id')
                ->select('departments.description', DB::raw('count(*) as total'))
                ->groupBy('departments.description')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            $pendingRequestsList = DB::connection('dar-system')
                ->table('request_dar')
                ->join('users as requester', 'request_dar.nik_req', '=', 'requester.nik')
                ->join('departments', 'requester.department_id', '=', 'departments.id')
                ->select(
                    'request_dar.id',
                    'request_dar.number_dar as request_id',
                    'requester.name as requester_name',
                    'departments.description as department',
                    'request_dar.created_date as created_at',
                    'request_dar.approval_status2'
                )
                ->where('request_dar.approval_status2', '0')
                ->orderBy('request_dar.created_date', 'desc')
                ->limit(5)
                ->get();


            return view('users-dashboard.sysdev.home', [
                'totalDar' => $getData,
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
                'monthLabels' => json_encode($monthLabels),
                'monthlyTrend' => json_encode($monthlyTrend),
                'departmentDistribution' => $departmentDistribution,
                'pendingRequest' => $pendingRequestsList
            ]);
        } else {
            return view('error.403');
        }


    }
    public function home_()
    {
        return view('layouts.dashboard');
    }
}
