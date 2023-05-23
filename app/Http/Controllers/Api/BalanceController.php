<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Balance;
use Illuminate\Support\Facades\Validator;
use DB;
use Auth;
use App\Events\ActionLog;


class BalanceController extends Controller
{
    //
    public function getall(Request $request)
    {
        try {
            $filter_datas = explode("&", $request->filter_data);
            $location_id = $filter_datas[0];
            $item_id = $filter_datas[1];
            $balances = Balance::select(
                'balances.id',
                DB::raw('locations.name as location_name'),
                DB::raw('items.name as item_name'),
                'total_inward',
                'total_outward',
                'balance',
                'total_inbag',
                'total_outbag',
                'balance_bag',
                'location_id',
                'item_id',
            )
                ->Join('items', function ($join) {
                    $join->on('items.id', '=', 'balances.item_id');
                })
                ->Join('locations', function ($join) {
                    $join->on('locations.id', '=', 'balances.location_id');
                })
                ->get()
                ->toArray();
            if ($location_id != "") {
                $balances = array_values(array_filter($balances, function ($var) use ($location_id) {
                    return $var['location_id'] == $location_id;
                }));
            }

            if ($item_id != "") {
                $balances = array_values(array_filter($balances, function ($var) use ($item_id) {
                    return $var['item_id'] == $item_id;
                }));
            }

            $data['balances'] = $balances;

            $response = ['type' => "success", 'data' => $data, 'msg' => Auth::user()->id];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            //dd($e->getMessage());
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }



    public function get(Request $request)
    {
        try {
            $id = $request->id;
            $data = Balance::select(
                'id',
                'location_id',
                'item_id',
                'total_inward',
                'total_outward',
                'balance',
                'total_inbag',
                'total_outbag',
                'balance_bag',
            )->where('id', $id)->first();
            $response = ['type' => "success", 'data' => $data, 'msg' => "Balance Data fetch successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = ['type' => "error", 'msg' => "Something Went Wrong"];
            return response()->json($response, 422);
        }
    }

    public function create(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'location_id' => 'required',
                'item_id' => 'required',
            ]);

            if ($validator->fails()) :
                $errors = implode(" ", array_flatten(array_values($validator->errors()->getMessages())));
                $response = ['type' => "error", 'errors' => $errors];
                return response()->json($response, 200);
            endif;

            $data = $request->all();
            $id = $request->id;
            $user_id = Auth::user()->id;

            if ($id) {
                $balance = Balance::findOrFail($id);
            } else {
                $check = Balance::where("location_id", $request->location_id)->where("item_id", $request->item_id)->first();
                if ($check) {
                    $balance = Balance::findOrFail($check->id);
                } else {
                    $balance = new Balance();
                    $data['created_by'] = $user_id;
                }
            }
            $data['updated_by'] = $user_id;
            $balance->fill($data);
            $balance->save();

            DB::commit();

            $response = ['type' => "success", 'result' => $balance, 'msg' => "Balance Data saved successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            //dd($e->getMessage());
            DB::rollback();
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            \Log::useDailyFiles(storage_path() . env('ACTION_LOG_FOLDER') . env('ACTION_LOG_FILE_NAME'));
            $id = $request->id;
            \Log::info("Balance Delete Start for ID:" . $id);
            $user_id = Auth::user()->id;
            $forBalance = Balance::findOrFail($id);
            //Delete Process
            $delete = Balance::where('id', $id)->delete();
            if($delete){
                $this->deleteLog($forBalance);
              }
            DB::commit();
            \Log::info("Balance Delete End for ID:" . $id);
            $response = ['type' => "success", 'data' => $delete, 'msg' => "Balance Data delete successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollback();
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }

    public function deleteLog($forParent = null, $action="delete")
    {
        $events = [];
        if ($forParent || $forParent!=null || $forParent!="") {
            $event['module'] = "Balance";
            $event['action'] = $action;
            $event['record'] = $forParent->toArray();
            $events[] = $event;
        }
        if (!empty($events)) {
            $msg = ""; 
            foreach ($events as $k => $r) {
                $result = [];
                $module = $r['module'];
                $action = $r['action'];
                $record = $r['record'];
                foreach ($record as $key => $value) {
                    $result[] = "[" . $key . ":" . $value . "]";
                }
                $msg .= $module . "-" . $action."\r".implode("-", $result);
            }
        }
        event(new ActionLog($msg));
    }
}
