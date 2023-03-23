<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Inter;
use App\Balance;
use App\InterProduct;
use Illuminate\Support\Facades\Validator;
use DB;
use App\Events\ActionLog;


class InterController extends Controller
{
    //
    public function getall(Request $request)
    {
        try {
            $filter_datas = explode("&", $request->filter_data);
            $from_id = $filter_datas[0];
            $to_id = $filter_datas[1];
            $item_id = $filter_datas[2];
            $start_date = (!empty($filter_datas[3])) ? $filter_datas[3] : date("Y-m-01", strtotime(""));
            $end_date = (!empty($filter_datas[4])) ? $filter_datas[4] : date("Y-m-d");
            $products = InterProduct::select(
                'inter_products.id',
                'inter_id',
                'item_id',
                'item_value',
                'supplier_id',
                'dcno',
                'bags',
                'avg_weight',
                DB::raw('items.name as item_name')
            )
                ->Join('items', function ($join) {
                    $join->on('items.id', '=', 'inter_products.item_id');
                })
                ->with(['inter' => function ($q) {
                    $q->select(
                        'id',
                        'from_id',
                        'to_id',
                        'date',
                        'inv_no',
                        'vehicle_no',
                        'remarks'
                    );
                }])
                ->orderBy('inter_products.id', 'DESC')
                ->get()
                ->toArray();

            if ($from_id != "") {
                $products = array_values(array_filter($products, function ($var) use ($from_id) {
                    return $var['inter']['from_id'] == $from_id;
                }));
            }

            if ($to_id != "") {
                $products = array_values(array_filter($products, function ($var) use ($to_id) {
                    return $var['inter']['to_id'] == $to_id;
                }));
            }

            if ($item_id != "") {
                $products = array_values(array_filter($products, function ($var) use ($item_id) {
                    return $var['item_id'] == $item_id;
                }));
            }


            $products = array_values(array_filter($products, function ($var) use ($start_date, $end_date) {
                return ($var['inter']['date'] >= $start_date) && ($var['inter']['date'] <= $end_date);
            }));

            $data['products'] = $products;

            $response = ['type' => "success", 'data' => $data, 'msg' => "Inter Data fetch successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            //dd($e->getMessage());
            $response = ['type' => "error", 'msg' => $e->getLine()];
            return response()->json($response, 422);
        }
    }

    public function create(Request $request)
    {
        DB::beginTransaction();
        try {
            //dd($request->all());
            $validator = Validator::make($request->all(), [
                'from_id' => 'required',
                'to_id' => 'required',
                'inv_no' => 'required',
            ]);

            if ($validator->fails()) :
                $errors = implode(" ", array_flatten(array_values($validator->errors()->getMessages())));
                $response = ['type' => "error", 'errors' => $errors];
                return response()->json($response, 200);
            endif;

            $from_id = $request->from_id;
            $to_id = $request->to_id;
            $data = $request->all();
            $data['created_by'] = 1;
            $data['updated_by'] = 1;
            $id = $request->id;
            if ($id) {
                $inter = Inter::findOrFail($id);
            } else {
                $inter = new Inter();
            }
            $inter->fill($data);
            $inter->save();

            if (empty($data['products'])) {
                $response = ['type' => "error", 'msg' => "Please fill item's fileds"];
                return response()->json($response, 422);
            }


            \Log::useDailyFiles(storage_path() . env('ACTION_LOG_FOLDER').env('ACTION_LOG_FILE_NAME'));
            \Log::info("Inter Update Start for ID:".$id);

            foreach ($data['products'] as $key => $value) {

                $old_product = InterProduct::where('id', $value['id'])->first();
                if ($old_product) {
                    $old_item_value = $old_product->item_value;
                    $old_bags = $old_product->bags;
                } else {
                    $old_item_value = 0;
                    $old_bags = 0;
                }
                $balance_from_check = Balance::where('location_id', $from_id)->where('item_id', $value['item_id'])->first();
                if ($balance_from_check) {
                    $total_inward = $balance_from_check->total_inward;
                    $total_inbag = $balance_from_check->total_inbag;
                    if ($id) {
                        $total_inward = $total_inward + $old_item_value;
                        $total_inbag = $total_inbag + $old_bags;
                    }

                    $item_value = $total_inward - $value['item_value'];
                    $bags = $total_inbag - $value['bags'];


                    $balance = Balance::findOrFail($balance_from_check->id);
                } else {
                    $balance = new Balance();
                    $item_value = $value['item_value'];
                    $bags = $value['bags'];
                }
                $balance_data = [
                    'location_id' => $from_id,
                    'item_id' => $value['item_id'],
                    'total_inward' => $item_value,
                    'total_inbag' => $bags,
                    'balance' => $item_value - $balance->total_outward,
                ];
                $balance->fill($balance_data);
                $balance->save();



                $balance_to_check = Balance::where('location_id', $to_id)->where('item_id', $value['item_id'])->first();
                if ($balance_to_check) {
                    $total_inward = $balance_to_check->total_inward;
                    $total_inbag = $balance_to_check->total_inbag;
                    if ($id) {
                        $total_inward = ($total_inward > $old_item_value) ?
                            $total_inward - $old_item_value : $old_item_value - $total_inward;
                        $total_inbag = ($old_bags > $total_inbag) ?
                            $old_bags - $total_inbag : $total_inbag - $old_bags;
                    }

                    $item_value = $total_inward + $value['item_value'];
                    $bags = $total_inbag + $value['bags'];


                    $balance = Balance::findOrFail($balance_to_check->id);
                } else {
                    $balance = new Balance();
                    $item_value = $value['item_value'];
                    $bags = $value['bags'];
                }
                $balance_data = [
                    'location_id' => $to_id,
                    'item_id' => $value['item_id'],
                    'total_inward' => $item_value,
                    'total_inbag' => $bags,
                    'balance' => $item_value - $balance->total_outward,
                ];
                $balance->fill($balance_data);
                $balance->save();

                if ($id && $value['id'] != "" && $value['id'] != null) {
                    $old_product_delete = $forProducts = InterProduct::findOrFail($value['id']);
                    $this->deleteLog(null, [$forProducts], "update");
                    $old_product_delete->forceDelete();
                }

                $value['inter_id'] = $inter->id;
                unset($value['id']);
                $product = new InterProduct();
                $product->fill($value);
                $product->save();
                if($id){
                    $this->deleteLog(null, [$product], "new");
                }
            }


            $deletedIds = (!empty($data['deletedIds'])) ? $data['deletedIds'] : [];
            if (!empty($deletedIds) && $id) {
                $delete_inter_products = $forProducts = InterProduct::whereIn('id', $deletedIds)->get();

                foreach ($delete_inter_products as $key => $product) {
                    $item_id = $product->item_id;
                    $item_value = $product->item_value;
                    $bags = $product->bags;
                    $balance_from = Balance::where('item_id', $item_id)->where('location_id', $from_id)->first();
                    $total_inward_from = $balance_from->total_inward + $item_value;
                    $total_bags_from = $balance_from->total_inbag + $bags;
                    $balance_from_update = Balance::where('item_id', $item_id)->where('location_id', $from_id)
                        ->update([
                            'total_inward' => $total_inward_from,
                            'total_inbag' => $total_bags_from,
                            'balance' => $total_inward_from - $balance_from->total_outward
                        ]);
                    $balance_to = Balance::where('item_id', $item_id)->where('location_id', $to_id)->first();
                    $total_inward_to = $balance_to->total_inward - $item_value;
                    $total_bags_to = $balance_to->total_inbag - $bags;
                    $balance_to_update = Balance::where('item_id', $item_id)->where('location_id', $to_id)
                        ->update([
                            'total_inward' => $total_inward_to,
                            'total_inbag' => $total_bags_to,
                            'balance' => $total_inward_to - $balance_to->total_outward
                        ]);
                }
                $delete = InterProduct::whereIn('id', $deletedIds)->delete();
                if ($delete) {
                    $this->deleteLog(null, $forProducts);
                }
            }

            // $inter->products()->attach($request->products);
            DB::commit();
            \Log::info("Inter Update End for ID:".$id);


            $response = ['type' => "success", 'result' => $inter, 'msg' => "Inter Data saved successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            //dd($e->getMessage());
            DB::rollback();
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }


    public function get(Request $request)
    {
        try {
            $id = $request->id;
            $data = Inter::select(
                'id',
                'from_id',
                'to_id',
                'date',
                'inv_no',
                'vehicle_no',
                'remarks'
            )->where('id', $id)->first();
            $data->products = InterProduct::where('inter_id', $id)->get();
            $response = ['type' => "success", 'data' => $data, 'msg' => "Inter Data fetch successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = ['type' => "error", 'msg' => "Something Went Wrong"];
            return response()->json($response, 422);
        }
    }


    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            \Log::useDailyFiles(storage_path() . env('ACTION_LOG_FOLDER').env('ACTION_LOG_FILE_NAME'));
            $id = $request->id;
            \Log::info("Inter Delete Start for ID:".$id);
            $inter = $forInter = Inter::findOrFail($id);
            $from_id = $inter->from_id;
            $to_id = $inter->to_id;
            $inter_products = $forProducts = InterProduct::where('inter_id', $id)->get();

            foreach ($inter_products as $key => $product) {
                $item_id = $product->item_id;
                $item_value = $product->item_value;
                $bags = $product->bags;
                $balance_from = Balance::where('item_id', $item_id)->where('location_id', $from_id)->first();
                $total_inward_from = $balance_from->total_inward + $item_value;
                $total_bags_from = $balance_from->total_inbag + $bags;
                $balance_from_update = Balance::where('item_id', $item_id)->where('location_id', $from_id)
                    ->update([
                        'total_inward' => $total_inward_from,
                        'total_inbag' => $total_bags_from,
                        'balance' => $total_inward_from - $balance_from->total_outward
                    ]);
                $balance_to = Balance::where('item_id', $item_id)->where('location_id', $to_id)->first();
                $total_inward_to = $balance_to->total_inward - $item_value;
                $total_bags_to = $balance_to->total_inbag - $bags;
                $balance_to_update = Balance::where('item_id', $item_id)->where('location_id', $to_id)
                    ->update([
                        'total_inward' => $total_inward_to,
                        'total_inbag' => $total_bags_to,
                        'balance' => $total_inward_to - $balance_to->total_outward
                    ]);
            }

            InterProduct::where('inter_id', $id)->delete();
            $delete = $inter->delete();
            DB::commit();
            if($delete){
                $this->deleteLog($forInter, $forProducts);
              }
            \Log::info("Inter Delete End for ID:".$id);

            $response = ['type' => "success", 'data' => $inter, 'msg' => "Inter Data delete successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollback();
            $response = ['type' => "error", 'msg' => $e->getLine() . "-" . $e->getMessage()];
            return response()->json($response, 422);
        }
    }

    public function deleteLog($forParent = null, $forChild = [], $action="delete")
    {
        $events = [];
        if ($forParent || $forParent!=null || $forParent!="") {
            $event['module'] = "Inter";
            $event['action'] = $action;
            $event['record'] = $forParent->toArray();
            $events[] = $event;
        }
        if (!empty($forChild)) {
            foreach ($forChild as $key => $value) {
                $event1['module'] = "Inter-Products";
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
                    $result[] = "[" . $key . ":" . $value . "]";
                }
                $msg .= $module . "-" . $action."\r".implode("-", $result);
            }
        }
        event(new ActionLog($msg));
    }
}
