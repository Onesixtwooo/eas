<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Registration — OLSHCO EAS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="min-h-screen bg-[#F5F7FA]">
<main class="grid min-h-screen lg:grid-cols-[.8fr_1.2fr]">
    <section class="sticky top-0 hidden h-screen flex-col justify-between bg-[#123A63] p-12 text-white lg:flex">
        <a href="{{ route('login') }}" class="flex items-center gap-3"><img src="{{ asset('images.jpg') }}" alt="OLSHCO logo" class="size-12 rounded-full bg-white object-cover"><span><b class="block">OLSHCO EAS</b><span class="text-xs text-blue-200">College Department</span></span></a>
        <div><p class="text-sm font-semibold uppercase tracking-[.25em] text-blue-200">Student Portal</p><h1 class="mt-4 text-4xl font-bold leading-tight">Create your student account.</h1><p class="mt-5 max-w-md leading-relaxed text-blue-100">Submit excuse requests, follow their progress, and access approved admission slips online.</p></div>
        <span></span>
    </section>
    <section class="flex items-center justify-center p-5 sm:p-8">
        <div class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-xl sm:p-9">
            <a href="{{ route('login') }}" class="text-sm font-semibold text-[#245B8E]">← Back to sign in</a>
            <h2 class="mt-4 text-3xl font-bold text-slate-900">Student registration</h2>
            <p class="mt-2 text-slate-500">Enter your official student information below. We will verify your email with a one-time code, then an administrator must approve your registration before you can sign in.</p>
            @if($errors->any())<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><b>Please correct the following:</b><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="post" action="{{ route('register.store') }}" enctype="multipart/form-data" class="mt-7 grid gap-5 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2">
                    <label for="email">Email address <span class="text-red-600">*</span></label>
                    <div class="mt-1 flex gap-3">
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="student@olshco.edu.ph" class="min-w-0 flex-1">
                        <button id="send-otp" type="button" class="shrink-0 rounded-xl bg-[#245B8E] px-5 font-semibold text-white hover:bg-[#123A63]">Send OTP</button>
                    </div>
                    <p id="otp-status" class="mt-2 hidden text-sm"></p>
                </div>
                <div class="sm:col-span-2">
                    <label for="otp">Email OTP <span class="text-red-600">*</span></label>
                    <input id="otp" name="otp" value="{{ old('otp') }}" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="Enter the 6-digit code">
                    @error('otp')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <fieldset class="sm:col-span-2">
                    <legend class="mb-2 text-sm font-semibold text-slate-700">Student type <span class="text-red-600">*</span></legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-300 p-4 font-normal"><input type="radio" name="student_type" value="regular" class="mt-1 size-4 w-auto" @checked(old('student_type', 'regular') === 'regular') required><span><strong class="block text-slate-900">Regular</strong><span class="text-sm text-slate-500">Subjects follow the selected year level and block.</span></span></label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-300 p-4 font-normal"><input type="radio" name="student_type" value="irregular" class="mt-1 size-4 w-auto" @checked(old('student_type') === 'irregular') required><span><strong class="block text-slate-900">Irregular</strong><span class="text-sm text-slate-500">Select currently enrolled subjects from all year levels.</span></span></label>
                    </div>
                </fieldset>
                <div id="irregular-subjects" class="sm:col-span-2 hidden rounded-2xl border border-blue-100 bg-blue-50/60 p-5">
                    <h3 class="font-bold text-slate-900">Currently enrolled subjects</h3>
                    <p class="mt-1 text-sm text-slate-500">Check every subject you are taking this term.</p>
                    @forelse($subjects as $year => $yearSubjects)
                        <div class="mt-5"><h4 class="text-sm font-bold text-[#123A63]">{{ $year ? 'Year '.$year : 'Other subjects' }}</h4><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($yearSubjects as $subject)<label class="flex items-start gap-2 rounded-lg bg-white p-3 font-normal"><input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}" class="mt-1 size-4 w-auto" @checked(in_array($subject->id, old('subject_ids', [])))><span><strong>{{ $subject->code }}</strong> — {{ $subject->name }}</span></label>@endforeach</div></div>
                    @empty
                        <p class="mt-4 text-sm text-red-600">No active subjects are available. Contact the administrator.</p>
                    @endforelse
                    @error('subject_ids')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div><label for="student_number">Student ID <span class="text-red-600">*</span></label><input id="student_number" name="student_number" value="{{ old('student_number') }}" required placeholder="e.g. 2026-0001"></div>
                <div><label for="first_name">First name <span class="text-red-600">*</span></label><input id="first_name" name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name" placeholder="First name"></div>
                <div><label for="middle_name">Middle name <span class="font-normal text-slate-400">(optional)</span></label><input id="middle_name" name="middle_name" value="{{ old('middle_name') }}" autocomplete="additional-name" placeholder="Middle name"></div>
                <div><label for="last_name">Last name <span class="text-red-600">*</span></label><input id="last_name" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name" placeholder="Last name"></div>
                <div class="sm:col-span-2"><label for="address">Home address <span class="text-red-600">*</span></label><textarea id="address" name="address" rows="3" required autocomplete="street-address" placeholder="House number, street, barangay, municipality, province">{{ old('address') }}</textarea></div>
                <div class="sm:col-span-2 rounded-2xl border border-blue-100 bg-blue-50/60 p-5">
                    <label for="assessment_form">Assessment form <span class="text-red-600">*</span></label>
                    <p class="mt-1 text-sm text-slate-500">Upload a clear photo or scan of your official assessment form so the school can verify your enrollment.</p>
                    <input id="assessment_form" type="file" name="assessment_form" accept="image/jpeg,image/png" required class="mt-3 bg-white">
                    <p class="mt-2 text-xs text-slate-400">Accepted formats: JPG or PNG. Maximum file size: 5 MB.</p>
                </div>
                <div><label for="year_level">Year level <span class="text-red-600">*</span></label><select id="year_level" name="year_level" required><option value="">Select year level</option>@foreach(range(1, 5) as $year)<option value="{{ $year }}" @selected(old('year_level') == $year)>{{ $year }}{{ $year === 1 ? 'st' : ($year === 2 ? 'nd' : ($year === 3 ? 'rd' : 'th')) }} Year</option>@endforeach</select></div>
                <div><label for="block">Block <span class="text-red-600">*</span></label><select id="block" name="block" required><option value="">Select block</option>@foreach(range('A', 'G') as $block)<option value="{{ $block }}" @selected(old('block') === $block)>Block {{ $block }}</option>@endforeach</select></div>
                <div><label for="password">Password <span class="text-red-600">*</span></label><input id="password" type="password" name="password" required autocomplete="new-password" placeholder="At least 8 characters"></div>
                <div><label for="password_confirmation">Confirm password <span class="text-red-600">*</span></label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repeat your password"></div>
                <div class="sm:col-span-2"><button class="w-full rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white hover:bg-[#245B8E]">Create Student Account</button><p class="mt-4 text-center text-sm text-slate-500">Already registered? <a href="{{ route('login') }}" class="font-semibold text-[#245B8E] hover:underline">Sign in</a></p></div>
            </form>
        </div>
    </section>
</main>
<script>
const sendOtpButton = document.getElementById('send-otp');
const emailInput = document.getElementById('email');
const otpStatus = document.getElementById('otp-status');
const irregularSubjects = document.getElementById('irregular-subjects');
const studentTypeInputs = document.querySelectorAll('input[name="student_type"]');

function updateStudentTypeFields() {
    const irregular = document.querySelector('input[name="student_type"]:checked')?.value === 'irregular';
    irregularSubjects.classList.toggle('hidden', !irregular);
    irregularSubjects.querySelectorAll('input[type="checkbox"]').forEach(input => input.disabled = !irregular);
}

studentTypeInputs.forEach(input => input.addEventListener('change', updateStudentTypeFields));
updateStudentTypeFields();

sendOtpButton.addEventListener('click', async () => {
    if (!emailInput.reportValidity()) return;
    sendOtpButton.disabled = true;
    sendOtpButton.textContent = 'Sending...';
    otpStatus.classList.add('hidden');

    try {
        const response = await fetch(@json(route('register.send-otp')), {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
            body: JSON.stringify({email: emailInput.value}),
        });
        const data = await response.json();
        otpStatus.textContent = response.ok ? data.message : (data.errors?.email?.[0] || data.message || 'Unable to send OTP.');
        otpStatus.className = `mt-2 text-sm ${response.ok ? 'text-emerald-700' : 'text-red-600'}`;
    } catch (error) {
        otpStatus.textContent = 'Unable to send OTP. Please try again.';
        otpStatus.className = 'mt-2 text-sm text-red-600';
    } finally {
        sendOtpButton.disabled = false;
        sendOtpButton.textContent = 'Send OTP';
    }
});
</script>
</body>
</html>
