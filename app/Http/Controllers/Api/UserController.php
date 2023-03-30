<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    //
    public function getall()
    {
        try {
            $result = User::select('id', 'name', 'login_id', 'role', 'password')->orderBy('id', 'DESC')->get();
            $response = ['type' => "success", 'data' => $result, 'msg' => "User Data fetch successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = ['type' => "error", 'msg' => "Something Went Wrong"];
            return response()->json($response, 422);
        }
    }

    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'login_id' => 'required',
                'password' => 'required',
            ]);

            if ($validator->fails()) :
                $errors = implode(" ", array_flatten(array_values($validator->errors()->getMessages())));
                $response = ['type' => "error", 'errors' => $errors];
                return response()->json($response, 200);
            endif;

            $data = $request->all();
            $id = $request->id;
            if ($id) {
                $user = User::findOrFail($id);
            } else {
                $user = new User();
            }
            if(!$request->password_request){
                unset($data['password']);
            }
            $user->fill($data);
            $user->save();
            $response = ['type' => "success", 'result' => $user, 'msg' => "User Data saved successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            dd($e->getMessage());
            $response = ['type' => "error", 'msg' => "Something Went Wrong"];
            return response()->json($response, 422);
        }
    }


    public function get(Request $request)
    {
        try {
            $id = $request->id;
            $data = User::select('id', 'name', 'login_id', 'password')->findOrFail($id);
            $response = ['type' => "success", 'data' => $data, 'msg' => "User Data fetch successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = ['type' => "error", 'msg' => "Something Went Wrong"];
            return response()->json($response, 422);
        }
    }


    public function delete(Request $request)
    {
        try {
            $id = $request->id;
            $data = User::findOrFail($id)->delete();
            $response = ['type' => "success", 'data' => $data, 'msg' => "User Data delete successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = ['type' => "error", 'msg' => "Something Went Wrong"];
            return response()->json($response, 422);
        }
    }
}
