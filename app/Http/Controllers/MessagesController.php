<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;

class MessagesController extends Controller
{
    public function index(Request $request){
        $response = ['data' => Message::where(['sender_id'=>auth()->user()->id,'receiver_id'=>$request->receiver_id])->orWhere(['receiver_id'=>auth()->user()->id,'sender_id'=>$request->receiver_id])->get()];
        return response($response, 200);
    }
}
