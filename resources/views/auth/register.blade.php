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
    <section class="hidden flex-col justify-between bg-[#123A63] p-12 text-white lg:flex">
        <a href="{{ route('login') }}" class="flex items-center gap-3"><span class="grid size-12 place-items-center rounded-xl bg-white text-xl font-black text-[#123A63]">O</span><b>OLSHCO EAS</b></a>
        <div><p class="text-sm font-semibold uppercase tracking-[.25em] text-blue-200">Student Portal</p><h1 class="mt-4 text-4xl font-bold leading-tight">Create your student account.</h1><p class="mt-5 max-w-md leading-relaxed text-blue-100">Submit excuse requests, follow their progress, and access approved admission slips online.</p></div>
        <p class="text-sm text-blue-200">Our Lady of the Sacred Heart College of Guimba, Inc.</p>
    </section>
    <section class="flex items-center justify-center p-5 sm:p-8">
        <div class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-xl sm:p-9">
            <a href="{{ route('login') }}" class="text-sm font-semibold text-[#245B8E]">← Back to sign in</a>
            <h2 class="mt-4 text-3xl font-bold text-slate-900">Student registration</h2>
            <p class="mt-2 text-slate-500">Enter your official student information below.</p>
            @if($errors->any())<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><b>Please correct the following:</b><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="post" action="{{ route('register.store') }}" enctype="multipart/form-data" class="mt-7 grid gap-5 sm:grid-cols-2">
                @csrf
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
                <div><label for="email">Email address <span class="text-red-600">*</span></label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="student@olshco.edu.ph"></div>
                <div><label for="password">Password <span class="text-red-600">*</span></label><input id="password" type="password" name="password" required autocomplete="new-password" placeholder="At least 8 characters"></div>
                <div><label for="password_confirmation">Confirm password <span class="text-red-600">*</span></label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Repeat your password"></div>
                <div class="sm:col-span-2"><button class="w-full rounded-xl bg-[#123A63] px-5 py-3 font-semibold text-white hover:bg-[#245B8E]">Create Student Account</button><p class="mt-4 text-center text-sm text-slate-500">Already registered? <a href="{{ route('login') }}" class="font-semibold text-[#245B8E] hover:underline">Sign in</a></p></div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
