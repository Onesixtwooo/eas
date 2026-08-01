<?php
namespace Database\Seeders;
use App\Models\{AcademicYear,Course,ExcuseRequest,Faculty,InstructorAssignment,ReasonCategory,Section,Semester,Student,Subject,User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder {
 public function run():void{
  $users=collect([
   
   'admin'=>User::create(['name'=>'System Administrator',
   'email'=>'admin@olshco.edu.ph','password'=>Hash::make('password'),'role'=>'admin'])
  ]);
  
}
}