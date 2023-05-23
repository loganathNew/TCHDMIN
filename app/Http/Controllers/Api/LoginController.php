<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use App\User;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;


class LoginController extends Controller
{
    //
    public function checkAuth(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'username' => 'required',
                'password' => 'required',
            ]);
            if ($validator->fails()) :
                $errors = implode(" ", array_flatten(array_values($validator->errors()->getMessages())));
                $response = ['type' => "error", 'errors' => $errors, 'msg' => "Please fill all fields"];
                return response()->json($response, 200);
            endif;

            $user = User::where('login_id', $request->username)->first();
            if ($user) {
                if (\Hash::check($request->password, $user->password)) {
                    $token = $user->createToken('Laravel Password Grant Client', ['*'])->accessToken;

                    $data['token'] = $token;
                    $data['user_name'] = $user->name;
                    $data['user_role'] = $user->role;
                    $data['user_id'] = $user->id;

                    $response = ['type' => "success", 'data' => $data, 'msg' => "Login successfully"];
                    return response($response, 200);
                } else {
                    $response = ['type' => "error", 'data' => [], 'msg' => "Credential not match"];
                    return response($response, 200);
                }
            } else {
                $response = ['type' => "error", 'data' => [], 'msg' => "User does not exist"];
                return response($response, 200);
            }
        } catch (\Exception $e) {
            $response = ['type' => "error", 'data' => [], 'msg' => "Something went wrong"];
            return response()->json($response, 422);
        }
    }

    public function logout(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required',
            ]);
            if ($validator->fails()) :
                $errors = implode(" ", array_flatten(array_values($validator->errors()->getMessages())));
                $response = ['type' => "error", 'errors' => $errors];
                return response()->json($response, 200);
            endif;

            $user = User::where('id', $request->user_id)->first();
            if (!$user) {
                $response = ['type' => "error", 'data' => [], 'msg' => "User does not exist"];
                return response($response, 200);
            }

            //dd($user->withAccessToken());
            $tokens =  $user->tokens->pluck('id');
            if (empty($tokens)) {
                $response = ['type' => "error", 'data' => [], 'msg' => "User does not logged in"];
                return response($response, 200);
            }

            // Token::whereIn('id', $tokens)->update(['revoked' => true]);
            Token::whereIn('id', $tokens)->delete();

            $data['token'] = '';
            $data['user_name'] = '';
            $data['user_role'] = '';
            $data['user_id'] = '';

            $response = ['type' => "success", 'data' => $data, 'msg' => "Logout successfully"];
            return response($response, 200);
        } catch (\Exception $e) {
            $response = ['type' => "error", 'data' => [], 'msg' => "Something went wrong"];
            return response()->json($response, 422);
        }
    }

    public function checking_authenticate(Request $request)
    {
        $user = null;
        if ($request->user_id != "") {
            $token = Token::where('user_id', $request->user_id)->first();
            if ($token) {
                $user = User::where('id', $request->user_id)->first();
            }
        }
        return response()->json(['user' => $user]);
    }
}
