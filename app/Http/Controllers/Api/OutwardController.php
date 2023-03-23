<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Outward;
use App\OutwardProduct;
use Illuminate\Support\Facades\Validator;
use DB;
use App\Balance;
use App\Events\ActionLog;

class OutwardController extends Controller
{
    //
    public function getall(Request $request)
    {
        try {
            $filter_datas = explode("&", $request->filter_data);
            $location_id = $filter_datas[0];
            $item_id = $filter_datas[1];
            $start_date = (!empty($filter_datas[2])) ? $filter_datas[2] : date("Y-m-01", strtotime(""));
            $end_date = (!empty($filter_datas[3])) ? $filter_datas[3] : date("Y-m-d");
            $products = OutwardProduct::select('id', 'items', 'gb_size', 'mixture', 'outward_id', 'quality', 'plant_hole', 'pcs_pallet', 'pallet', 'total_pcs', 'nwt', 'remarks')
                ->with(['outward' => function ($q) {
                    $q->select(
                        'id',
                        'location_id',
                        'date',
                        'inv_no',
                        'project_no',
                        'vehicle_no',
                        'container_no'
                    );
                }])

                ->orderBy('id', 'DESC')
                ->get()
                ->toArray();

            if ($location_id != "") {
                $products = array_values(array_filter($products, function ($var) use ($location_id) {
                    return $var['outward']['location_id'] == $location_id;
                }));
            }
            if ($item_id != "") {
                $products = array_values(array_filter($products, function ($var) use ($item_id) {
                    return (!empty($var['items'][$item_id])) ? true : false;
                }));
            }
            // dd($products);

            $products = array_values(array_filter($products, function ($var) use ($start_date, $end_date) {
                return ($var['outward']['date'] >= $start_date) && ($var['outward']['date'] <= $end_date);
            }));

            $products_data = $products;
            $totalOutwardNet = 0;
            $outward_id = "";
            if (!empty($products)) {
                foreach ($products as $key => $value) {
                    if ($outward_id != $value['id']) {
                        $totalOutwardNet += $value['nwt'];
                        $outward_id = $value['id'];
                    }
                }
            }

            $data['products'] = $products_data;
            $data['totalOutwardNet'] = $totalOutwardNet;

            $response = ['type' => "success", 'data' => $data, 'msg' => "Outward Data fetch successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            //dd($e->getMessage());
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }

    public function create(Request $request)
    {
        DB::beginTransaction();
        try {
            //dd($request->all());
            $validator = Validator::make($request->all(), [
                'location_id' => 'required',
                'date' => 'required',
                'inv_no' => 'required',
                'project_no' => 'required',
                'container_no' => 'required',
                'vehicle_no' => 'required',
            ]);

            if ($validator->fails()) :
                $errors = implode(" ", array_flatten(array_values($validator->errors()->getMessages())));
                $response = ['type' => "error", 'errors' => $errors];
                return response()->json($response, 200);
            endif;

            $data = $request->all();
            $location_id = $request->location_id;
            $data['created_by'] = 1;
            $data['updated_by'] = 1;
            $id = $request->id;
            if ($id) {
                $outward = Outward::findOrFail($id);
            } else {
                $outward = new Outward();
            }
            $outward->fill($data);
            $outward->save();

            if (empty($data['products'])) {
                $response = ['type' => "error", 'msg' => "Please fill item's fileds"];
                return response()->json($response, 422);
            }
            
            \Log::useDailyFiles(storage_path() . env('ACTION_LOG_FOLDER').env('ACTION_LOG_FILE_NAME'));
            \Log::info("Outward Update Start for ID:".$id);
            foreach ($data['products'] as $value) {
                $value['outward_id'] = $outward->id;
                $value['items'] = array_filter($value['items']);
                if (count($value['items']) == 0) {
                    $response = ['type' => "error", 'msg' => "Please fill item's fileds"];
                    return response()->json($response, 422);
                }
                $this->createBalance($location_id, $id, $value, true);
                if ($id && $value['id'] != "" && $value['id'] != null) {
                    $forProducts = OutwardProduct::findOrFail($value['id']);
                    $this->deleteLog(null, [$forProducts], "update");
                    $forProducts->forceDelete();
                }
                unset($value['id']);
                $product = new OutwardProduct();
                $product->fill($value);
                $product->save();
                if($id){
                    $this->deleteLog(null, [$product], "new");
                }
            }

            //Delete Process
            $deletedIds = (!empty($data['deletedIds'])) ? $data['deletedIds'] : [];
            if (!empty($deletedIds) && $id) {
                $old_all_products = $forProducts = OutwardProduct::whereIn('id', $deletedIds)->get();
                foreach ($old_all_products->toArray() as $old_products) {
                    $deleted_items = [];
                    $old_products_items = $old_products['items'];
                    foreach ($old_products_items as $itm_id => $itm_val) {
                        $deleted_items['items'][$itm_id] = 0;
                        $deleted_items['id'] = $old_products['id'];
                    }
                    $this->createBalance($location_id, $id, $deleted_items, false);
                }
                $delete = OutwardProduct::whereIn('id', $deletedIds)->delete();
                if ($delete) {
                    $this->deleteLog(null, $forProducts);
                }
            }
            DB::commit();
            \Log::info("Outward Update End for ID:".$id);

            $response = ['type' => "success", 'result' => $outward, 'msg' => "Outward Data saved successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            //dd($e->getMessage());
            DB::rollback();
            // $response = ['type' => "error", 'msg' => $e->getLine()];
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }


    public function get(Request $request)
    {
        try {
            $id = $request->id;
            $data = Outward::select(
                'id',
                'location_id',
                'date',
                'inv_no',
                'project_no',
                'vehicle_no',
                'container_no'
            )->where('id', $id)->first();
            $data->products = OutwardProduct::where('outward_id', $id)->get();
            $response = ['type' => "success", 'data' => $data, 'msg' => "Outward Data fetch successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }


    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            \Log::useDailyFiles(storage_path() . env('ACTION_LOG_FOLDER').env('ACTION_LOG_FILE_NAME'));
            $id = $request->id;
            \Log::info("Outward Delete Start for ID:".$id);
            $outward = $forOutward = Outward::findOrFail($id);
            $old_all_products = $forProducts = OutwardProduct::where('outward_id', $id)->get();
            foreach ($old_all_products->toArray() as $old_products) {
                $deleted_items = [];
                $old_products_items = $old_products['items'];
                foreach ($old_products_items as $itm_id => $itm_val) {
                    $deleted_items['items'][$itm_id] = 0;
                    $deleted_items['id'] = $old_products['id'];
                }
                $this->createBalance($outward->location_id, $id, $deleted_items, false);
            }
            OutwardProduct::where('outward_id', $id)->delete();
            $delete = $outward->delete();

            if($delete){
                $this->deleteLog($forOutward, $forProducts);
              }
            DB::commit();
            \Log::info("Outward Delete End for ID:".$id);
            $response = ['type' => "success", 'data' => $outward, 'msg' => "Outward Data delete successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollback();
            $response = ['type' => "error", 'msg' => "Something Went Wrong"];
            return response()->json($response, 422);
        }
    }


    public function createBalance($location_id, $id, $value, $forcedelete)
    {
        foreach ($value['items'] as $itm_id => $itm_val) {
            $balance_check = Balance::where('location_id', $location_id)->where('item_id', $itm_id)->first();
            if ($balance_check) {
                $total_outward = $balance_check->total_outward;
                if ($id && $value['id'] != "" && $value['id'] != null) {
                    $old_product = OutwardProduct::findOrFail($value['id']);
                    $old_item_value = $old_product->items[$itm_id];
                    $total_outward = ($total_outward > $old_item_value) ?
                        $total_outward - $old_item_value : $old_item_value - $total_outward;
                }
                $item_value = $total_outward + (float) $itm_val;
                $balance = Balance::findOrFail($balance_check->id);
            } else {
                $balance = new Balance();
                $item_value = (float) $itm_val;
            }
            $balance_data = [
                'location_id' => $location_id,
                'item_id' => $itm_id,
                'total_outward' => $item_value,
                'balance' => $balance->total_inward - $item_value,
            ];
            $balance->fill($balance_data);
            $balance->save();
        }
    }

    public function deleteLog($forParent = null, $forChild = [], $action="delete")
    {
        $events = [];
        if ($forParent || $forParent!=null || $forParent!="") {
            $event['module'] = "Outward";
            $event['action'] = $action;
            $event['record'] = $forParent->toArray();
            $events[] = $event;
        }
        if (!empty($forChild)) {
            foreach ($forChild as $key => $value) {
                $event1['module'] = "Outward-Products";
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
