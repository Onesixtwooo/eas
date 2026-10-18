<?php
namespace App\Http\Controllers;
use App\Models\ExcuseRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
class DashboardController extends Controller {
    public function __invoke()
    {
        $u = auth()->user();

        if ($u->role === 'student') {
            return redirect()->route('requests.index');
        }

        if ($u->role === 'adviser') {
            return redirect()->route('advisory.index');
        }

        $query = ExcuseRequest::query();

        if ($u->role === 'faculty') {
            abort_unless($u->faculty, 403, 'This faculty account is not linked to a faculty profile.');
            $query->where(fn ($request) => $request
                ->where('facilitator_id', $u->faculty->id)
                ->orWhereHas('facilitators', fn ($faculty) => $faculty->whereKey($u->faculty->id)))
                ->whereIn('status', ['approved', 'acknowledged', 'completed']);
        }

        $recentRequestsQuery = clone $query;
        if ($u->role === 'faculty') {
            $recentRequestsQuery->where('status', 'approved');
        }
        $rankedRequests = $recentRequestsQuery->select('excuse_requests.id', 'excuse_requests.created_at')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY excuse_requests.student_id ORDER BY excuse_requests.created_at DESC, excuse_requests.id DESC) as student_rank');
        $recentIds = DB::query()->fromSub($rankedRequests, 'ranked_requests')
            ->where('student_rank', 1)->orderByDesc('created_at')->orderByDesc('id')
            ->limit(8)->pluck('id');
        $requests = ExcuseRequest::with(['student.user', 'subject', 'facilitator.user'])
            ->whereIn('id', $recentIds)->get()
            ->sortBy(fn ($request) => $recentIds->search($request->id))->values();
        $counts = (clone $query)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $totalRequests = (clone $query)->count();
        $assignedStudents = (clone $query)->distinct()->count('student_id');
        $currentSlipRemark = 'COALESCE((SELECT slip_amendments.slip_remark FROM slip_amendments WHERE slip_amendments.excuse_request_id = excuse_requests.id ORDER BY slip_amendments.id DESC LIMIT 1), excuse_requests.slip_remark)';
        $excusedStudents = (clone $query)->whereRaw($currentSlipRemark.' = ?', ['EXCUSED'])->whereIn('status', ['approved', 'acknowledged', 'completed'])->distinct()->count('student_id');
        $lateStudents = (clone $query)->whereNotNull('submitted_at')->whereRaw('DATE(submitted_at) > absence_date')->distinct()->count('student_id');

        $periods = [
            'day' => collect(range(6, 0))->map(fn ($daysAgo) => now()->startOfDay()->subDays($daysAgo)),
            'week' => collect(range(5, 0))->map(fn ($weeksAgo) => now()->startOfWeek()->subWeeks($weeksAgo)),
            'month' => collect(range(5, 0))->map(fn ($monthsAgo) => now()->startOfMonth()->subMonths($monthsAgo)),
        ];
        $expressions = [];
        $bindings = [];
        $rangeStart = null;
        $rangeEnd = null;
        foreach ($periods as $period => $buckets) {
            foreach ($buckets as $index => $start) {
                $end = match ($period) {
                    'day' => $start->copy()->endOfDay(),
                    'week' => $start->copy()->endOfWeek(),
                    default => $start->copy()->endOfMonth(),
                };
                $bounds = [$start->toDateTimeString(), $end->toDateTimeString()];
                $rangeStart = min($rangeStart ?? $bounds[0], $bounds[0]);
                $rangeEnd = max($rangeEnd ?? $bounds[1], $bounds[1]);
                $prefix = "{$period}_{$index}";
                $expressions[] = "COUNT(DISTINCT CASE WHEN submitted_at BETWEEN ? AND ? AND DATE(submitted_at) > absence_date THEN student_id END) as {$prefix}_late";
                array_push($bindings, ...$bounds);
                $expressions[] = "COUNT(DISTINCT CASE WHEN approved_at BETWEEN ? AND ? AND {$currentSlipRemark} = ? AND status IN (?, ?, ?) THEN student_id END) as {$prefix}_excused";
                array_push($bindings, $bounds[0], $bounds[1], 'EXCUSED', 'approved', 'acknowledged', 'completed');
                $expressions[] = "COUNT(CASE WHEN approved_at BETWEEN ? AND ? THEN 1 END) as {$prefix}_confirmed";
                array_push($bindings, ...$bounds);
                $expressions[] = "COUNT(CASE WHEN completed_at BETWEEN ? AND ? THEN 1 END) as {$prefix}_completed";
                array_push($bindings, ...$bounds);
            }
        }
        $metrics = (clone $query)->where(fn ($requests) => $requests
            ->whereBetween('submitted_at', [$rangeStart, $rangeEnd])
            ->orWhereBetween('approved_at', [$rangeStart, $rangeEnd])
            ->orWhereBetween('completed_at', [$rangeStart, $rangeEnd]))
            ->toBase()->selectRaw(implode(', ', $expressions), $bindings)->first();
        $analyticsByPeriod = collect($periods)->map(fn ($buckets, $period) => $buckets
            ->map(function (Carbon $start, int $index) use ($metrics, $period) {
                $prefix = "{$period}_{$index}";
                $label = match ($period) {
                    'day' => $start->format('D j'),
                    'week' => $start->format('M j'),
                    default => $start->format('M'),
                };

                return ['label' => $label, 'late' => (int) $metrics->{"{$prefix}_late"},
                    'excused' => (int) $metrics->{"{$prefix}_excused"},
                    'confirmed' => (int) $metrics->{"{$prefix}_confirmed"},
                    'completed' => (int) $metrics->{"{$prefix}_completed"}];
            })->values());
        // Keep the monthly variables available for existing integrations.
        $analytics = $analyticsByPeriod['month'];
        $analyticsMax = max(1, (int) $analytics->flatMap(fn ($bucket) => [$bucket['late'], $bucket['excused']])->max());

        return view('dashboard', compact('requests', 'counts', 'totalRequests', 'assignedStudents', 'excusedStudents', 'lateStudents', 'analytics', 'analyticsByPeriod', 'analyticsMax', 'u'));
    }
}
