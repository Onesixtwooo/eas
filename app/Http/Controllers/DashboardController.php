<?php
namespace App\Http\Controllers;
use App\Models\ExcuseRequest;
use Illuminate\Support\Carbon;
class DashboardController extends Controller {
    public function __invoke()
    {
        $u = auth()->user();

        if ($u->role === 'student') {
            return redirect()->route('requests.index');
        }

        $query = ExcuseRequest::query();

        if ($u->role === 'faculty') $query->where('facilitator_id', $u->faculty->id);

        $requests = (clone $query)->with(['student.user', 'subject', 'facilitator.user'])->latest()->take(8)->get();
        $counts = (clone $query)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $totalRequests = (clone $query)->count();
        $excusedStudents = (clone $query)->where('slip_remark', 'EXCUSED')->whereIn('status', ['approved', 'acknowledged', 'completed'])->distinct()->count('student_id');
        $lateStudents = (clone $query)->whereNotNull('submitted_at')->whereRaw('DATE(submitted_at) > absence_date')->distinct()->count('student_id');

        $periods = [
            'day' => collect(range(6, 0))->map(fn ($daysAgo) => now()->startOfDay()->subDays($daysAgo)),
            'week' => collect(range(5, 0))->map(fn ($weeksAgo) => now()->startOfWeek()->subWeeks($weeksAgo)),
            'month' => collect(range(5, 0))->map(fn ($monthsAgo) => now()->startOfMonth()->subMonths($monthsAgo)),
        ];
        $analyticsRequests = (clone $query)
            ->where(function ($request) use ($periods) {
                $analyticsStart = $periods['month']->first()->copy()->startOfMonth();
                $request->where('submitted_at', '>=', $analyticsStart)
                    ->orWhere('approved_at', '>=', $analyticsStart);
            })
            ->get(['student_id', 'absence_date', 'submitted_at', 'approved_at', 'status', 'slip_remark']);
        $analyticsByPeriod = collect($periods)->map(function ($buckets, $period) use ($analyticsRequests) {
            return $buckets->map(function (Carbon $start) use ($analyticsRequests, $period) {
                $end = match ($period) {
                    'day' => $start->copy()->endOfDay(),
                    'week' => $start->copy()->endOfWeek(),
                    default => $start->copy()->endOfMonth(),
                };
                $late = $analyticsRequests
                    ->filter(fn ($request) => $request->submitted_at
                        && $request->submitted_at->betweenIncluded($start, $end)
                        && $request->submitted_at->toDateString() > $request->absence_date->toDateString())
                    ->unique('student_id')->count();
                $excused = $analyticsRequests
                    ->filter(fn ($request) => $request->approved_at
                        && $request->approved_at->betweenIncluded($start, $end)
                        && $request->slip_remark === 'EXCUSED'
                        && in_array($request->status, ['approved', 'acknowledged', 'completed'], true))
                    ->unique('student_id')->count();

                $label = match ($period) {
                    'day' => $start->format('D j'),
                    'week' => $start->format('M j'),
                    default => $start->format('M'),
                };

                return ['label' => $label, 'late' => $late, 'excused' => $excused];
            })->values();
        });
        // Keep the monthly variables available for existing integrations.
        $analytics = $analyticsByPeriod['month'];
        $analyticsMax = max(1, (int) $analytics->flatMap(fn ($bucket) => [$bucket['late'], $bucket['excused']])->max());

        return view('dashboard', compact('requests', 'counts', 'totalRequests', 'excusedStudents', 'lateStudents', 'analytics', 'analyticsByPeriod', 'analyticsMax', 'u'));
    }
}
