<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreExcuseRequest extends FormRequest {
    public function authorize(): bool{return $this->user()?->role==='student';}
    public function rules(): array{return ['absence_date'=>'required|date|before_or_equal:today','subject_id'=>'required|exists:subjects,id','reason_category_id'=>'required|exists:reason_categories,id','explanation'=>'required|string|min:20|max:3000','start_time'=>'nullable|date_format:H:i','end_time'=>'nullable|date_format:H:i|after:start_time','guardian_name'=>'nullable|string|max:255','guardian_contact'=>'nullable|string|max:30','document'=>'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120','declaration'=>'required_if:intent,submit|accepted'];}
}
