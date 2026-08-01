<?php
namespace App\Http\Controllers;
use App\Models\ExcuseRequest;
class VerificationController extends Controller {public function show(string $reference){$item=ExcuseRequest::with(['student.user','subject','facilitator.user'])->where('reference_number',$reference)->first();return view('verify',compact('item','reference'));}}
