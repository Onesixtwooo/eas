<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Official Slip Verification</title>
<style>body{font-family:system-ui,sans-serif;background:#f1f5f9;color:#0f172a;padding:2rem}.card{max-width:42rem;margin:3rem auto;background:#fff;border:1px solid #cbd5e1;border-radius:1rem;padding:2rem}h1{color:#047857}.row{padding:.75rem 0;border-bottom:1px solid #e2e8f0}.label{display:block;color:#64748b;font-size:.85rem}</style></head><body><main class="card">
@php($issued = json_decode($archive->records['slip_issuance']['snapshot'] ?? 'null', true))
@php($reconstructed = ($issued['snapshot_source'] ?? null) !== 'captured')
@php($valid = in_array($archive->status, ['approved', 'acknowledged', 'completed'], true))
@php($amendments = $archive->records['slip_amendments'] ?? [])
@php($latestAmendment = count($amendments) ? end($amendments) : null)
<h1 style="color:{{ $valid && ! $latestAmendment ? '#047857' : '#92400e' }}">{{ ! $valid ? 'REVOKED OFFICIAL SLIP' : ($latestAmendment ? 'AMENDED OFFICIAL SLIP' : 'VALID OFFICIAL SLIP') }}</h1><p>This request is preserved in the archive.</p>
@if($reconstructed)<p style="padding:1rem;background:#fffbeb;border:1px solid #fcd34d">Historical slip details were reconstructed from records available when history tracking began. They may differ from the original printed slip.</p>@endif
@if($latestAmendment)<div class="row"><span class="label">Latest official amendment</span><strong>{{ $latestAmendment['slip_remark'] }}</strong> {{ $latestAmendment['remarks'] }}</div>@endif
<div class="row"><span class="label">Reference</span><strong>{{ $reference }}</strong></div>
<div class="row"><span class="label">Student</span><strong>{{ $issued['student_name'] ?? $archive->records['student_user']['name'] ?? 'Former student' }}</strong></div>
<div class="row"><span class="label">Student number</span>{{ $issued['student_number'] ?? $archive->records['student']['student_number'] ?? '' }}</div>
<div class="row"><span class="label">Subject</span>{{ $issued ? implode(', ', $issued['subject_codes']) : ($archive->records['subject']['name'] ?? '') }}</div>
<div class="row"><span class="label">Attendance</span>{{ ucfirst($issued['attendance_type'] ?? $archive->records['request']['attendance_type'] ?? 'absent') }}</div>
<div class="row"><span class="label">Date</span>{{ $issued['absence_date'] ?? $archive->records['request']['absence_date'] ?? '' }}</div>
<div class="row"><span class="label">Status</span>{{ ucfirst($archive->status) }}</div>
<div class="row"><span class="label">{{ $reconstructed ? 'Recorded initial result' : 'Original issued result' }}</span>{{ $issued['slip_remark'] ?? $archive->records['request']['slip_remark'] ?? '' }}</div>
<div class="row"><span class="label">Current official result</span>{{ $valid ? ($latestAmendment['slip_remark'] ?? $issued['slip_remark'] ?? $archive->records['request']['slip_remark'] ?? '') : 'REVOKED' }}</div>
<div class="row"><span class="label">Official remarks</span>{{ $issued ? $issued['official_remarks'] : ($archive->records['request']['official_remarks'] ?? '') }}</div>
</main></body></html>
