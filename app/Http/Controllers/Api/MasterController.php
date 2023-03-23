<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use DB;
use App\Events\ActionLog;


class MasterController extends Controller
{

    public function __construct(Request $request)
    {
        $master = $request->master;
        if ($master == "item") {
            $master = 'App\Item';
        } else if ($master == "location") {
            $master = "App\Location";
        } else if ($master == "storage_location") {
            $master = "App\StorageLocation";
        } else if ($master == "qc_name") {
            $master = "App\QcName";
        } else {
            $master = "App\Supplier";
        }
        $this->master = $master;
    }
    //
    public function getall()
    {
        try {
            $result = $this->master::select('id', 'name', 'des', 'value', 
            DB::raw('IF(deleted_at,"Deleted","Active") as status'))
            ->withTrashed()->orderBy('id', 'DESC')->get();
            $response = ['type' => "success", 'data' => $result, 'msg' => "Master Data fetch successfully"];
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
            ]);
            $name = $request->name;
            $id = $request->id;
            if ($validator->fails()) :
                $errors = implode(" ", array_flatten(array_values($validator->errors()->getMessages())));
                $response = ['type' => "error", 'errors' => $errors];
                return response()->json($response, 200);
            endif;

            $data = $request->all();
            if ($id) {
                \Log::useDailyFiles(storage_path() . env('ACTION_LOG_FOLDER').env('ACTION_LOG_FILE_NAME'));
                \Log::info($this->master." Update Start for ID:".$id);
                $user = $forProducts = $this->master::withTrashed()->findOrFail($id);
                $data['updated_by'] = 2;
                $nameCheck = $this->master::where('name', $name)->withTrashed()->where('id', '!=', $id)->first();
                $this->deleteLog(null, [$forProducts], "update");

            } else {
                $user = new $this->master();
                $data['created_by'] = 1;
                $data['updated_by'] = 1;
                $nameCheck = $this->master::where('name', $name)->withTrashed()->first();
            }

            if ($nameCheck) {
                $response = ['type' => "error", 'errors' => "Name already exist"];
                return response()->json($response, 200);
            }

            $user->fill($data);
            $user->save();
            if($id){
                $this->deleteLog(null, [$user], "new");
                \Log::info($this->master." Update End for ID:".$id);
            }
            $response = ['type' => "success", 'result' => $user, 'msg' => "Master Data saved successfully"];
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
            $data = $this->master::select('id', 'name', 'des', 'value', 'deleted_at')->withTrashed()->findOrFail($id);
            $response = ['type' => "success", 'data' => $data, 'msg' => "Master Data fetch successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = ['type' => "error", 'msg' => "Something Went Wrong"];
            return response()->json($response, 422);
        }
    }


    public function delete(Request $request)
    {
        try {

            \Log::useDailyFiles(storage_path() . env('ACTION_LOG_FOLDER').env('ACTION_LOG_FILE_NAME'));
            $id = $request->id;

            $updated_by = 1;
            $checkWithTrashed = $forRetain = $this->master::withTrashed()->where('id', $id)->whereNotNull('deleted_at')->first();
            if ($checkWithTrashed) {
                \Log::info($this->master." Retain Start for ID:".$id);
                
                $delete = $this->master::withTrashed()->where('id', $id)->update(['updated_by' => $updated_by, 'deleted_at' => null]);
                if($delete){
                    $this->deleteLog($forRetain, [],'Retain');
                }
                $msg = "Master data Retain successfully";
                $response = ['type' => "success", 'data' => $delete, 'msg' => $msg];
                \Log::info($this->master." Retain End for ID:".$id);
                return response()->json($response, 200);
            }

            $checkTrashed = $formaster = $this->master::findOrFail($id);
            if ($checkTrashed) {
                \Log::info($this->master." Detele End for ID:".$id);
                $data = $this->master::where('id', $id)->update(['updated_by' => $updated_by]);
                $delete = $this->master::findOrFail($id)->delete();
                if($delete){
                    $this->deleteLog($formaster, []);
                }
                $msg = "Master Data delete successfully";
                $response = ['type' => "success", 'data' => $delete, 'msg' => $msg];
                \Log::info($this->master." Delete End for ID:".$id);
                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }


    public function deleteLog($forParent = null, $forChild = [], $action="delete")
    {
        $events = [];
        if ($forParent || $forParent!=null || $forParent!="") {
            $event['module'] = $this->master;
            $event['action'] = $action;
            $event['record'] = $forParent->toArray();
            $events[] = $event;
        }
        if (!empty($forChild)) {
            foreach ($forChild as $key => $value) {
                $event1['module'] = $this->master."-Products";
                $event1['action'] = $action;
                $event1['record'] = $value->toArray();
                $events[] = $event1;
            }
        }
        if (!empty($events)) {
            $msg = ""; 
            foreach ($events as $k => $r) {
                $result = [];
                $module = $r['module'];
                $action = $r['action'];
                $record = $r['record'];
                foreach ($record as $key => $value) {
                    if(is_array($value)){
                        $value = json_encode($value);
                    }
                    $result[] = "[" . $key . ":" . $value . "]";
                }
                $msg .= $module . "-" . $action."\r".implode("-", $result);
            }
        }
        event(new ActionLog($msg));
    }
}
