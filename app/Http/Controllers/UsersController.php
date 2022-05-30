<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function index(Request $request){
        $response = ['data' => User::where('id','!=',auth()->user()->id)->get()];
        return response($response, 200);
    }
}
