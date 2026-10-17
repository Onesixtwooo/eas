<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Official Slip Verification</title>
<style>body{font-family:system-ui,sans-serif;background:#f1f5f9;color:#0f172a;padding:2rem}.card{max-width:42rem;margin:3rem auto;background:#fff;border:1px solid #cbd5e1;border-radius:1rem;padding:2rem}h1{color:#047857}.row{padding:.75rem 0;border-bottom:1px solid #e2e8f0}.label{display:block;color:#64748b;font-size:.85rem}</style></head><body><main class="card">
<h1>VALID OFFICIAL SLIP</h1><p>This approved request is preserved in the archive.</p>
<div class="row"><span class="label">Reference</span><strong>{{ $reference }}</strong></div>
<div class="row"><span class="label">Student</span><strong>{{ $archive->records['student_user']['name'] ?? 'Former student' }}</strong></div>
<div class="row"><span class="label">Student number</span>{{ $archive->records['student']['student_number'] ?? '' }}</div>
<div class="row"><span class="label">Subject</span>{{ $archive->records['subject']['name'] ?? '' }}</div>
<div class="row"><span class="label">Absence date</span>{{ $archive->records['request']['absence_date'] ?? '' }}</div>
<div class="row"><span class="label">Status</span>{{ ucfirst($archive->status) }}</div>
<div class="row"><span class="label">Official remarks</span>{{ $archive->records['request']['official_remarks'] ?? '' }}</div>
</main></body></html>
