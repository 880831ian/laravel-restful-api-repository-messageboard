<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $rules = [
            'username' => 'required',
            'password' => 'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(["message" => "格式錯誤"], 400);
        }

        if (!Auth::attempt([
            'username' => $request->username,
            'password' => $request->password
        ])) {
            return response()->json(["message" => "登入失敗"], 401);
        }
        return response()->json(["message" => "登入成功"], 200);
    }

    public function logout()
    {
        Auth::logout();
        return response()->json(["message" => "登出成功"], 200);
    }
}
