<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;

class ExcuseRequestSettingController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'program_head_name' => ['required', 'string', 'max:255'],
        ]);

        SystemSetting::updateOrCreate(
            ['key' => 'program_head_name'],
            ['value' => trim($data['program_head_name'])]
        );

        return back()->with('success', 'Excuse slip settings updated.');
    }
}
