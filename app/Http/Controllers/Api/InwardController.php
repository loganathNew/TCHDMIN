<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Inward;
use App\Product;
use App\Balance;
use Illuminate\Support\Facades\Validator;
use DB;
use Auth;
use App\Events\ActionLog;


class InwardController extends Controller
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
            $supplier_id = $filter_datas[4];
            $ec = (int) $filter_datas[5];
            $products = Product::select(
                'products.id',
                'inward_id',
                'item_id',
                'item_value',
                'supplier_id',
                'dcno',
                'bags',
                DB::raw('items.name as item_name')
            )
                ->Join('items', function ($join) {
                    $join->on('items.id', '=', 'products.item_id');
                })
                ->with(['inward' => function ($q) use ($location_id) {
                    $q->select(
                        'id',
                        'location_id',
                        'r_date',
                        'week',
                        'in_time',
                        'out_time',
                        'duration',
                        'inv_no',
                        'inv_date',
                        'lwt',
                        'ewt',
                        'nwt',
                        'ecu',
                        'ecm',
                        'ecl',
                        'aec',
                        'm1',
                        'm2',
                        'm3',
                        'am',
                        'sand',
                        'fibre',
                        'a_bagwt',
                        'vehicle_no',
                        'freight',
                        'transporter',
                        'storage_location',
                        'qc_name',
                        'remarks'
                    )->orderBy('inwards.id', 'DESC');
                }])

                // $products = Inward::select(
                //     'id', 'location_id', 'r_date', 'week',
                //     'in_time', 'out_time', 'duration', 'inv_no', 'inv_date',
                //     'lwt', 'ewt', 'nwt', 'ecu', 'ecm', 'ecl', 'aec',
                //     'm1', 'm2', 'm3', 'am', 'sand', 'fibre', 'a_bagwt',
                //     'vehicle_no', 'freight', 'transporter', 'storage_location', 'qc_name', 'remarks')
                // ->with(['products'=>function($q){
                //     $q->select('id','inward_id', 'item_id', 'item_name', 'item_value', 'supplier_id', 'dcno', 'bags');
                // }])

                ->get()
                // ->get();
                // $products_r = $products->whereBetween('r_date', [$start_date, $end_date])->get();
                // dd($products_r)   ;
                //    ->toArray();
                // ->whereBetween('inward.r_date', [$start_date, $end_date])
                ->toArray();
            if ($location_id != "") {
                $products = array_values(array_filter($products, function ($var) use ($location_id) {
                    return $var['inward']['location_id'] == $location_id;
                }));
            }

            if ($item_id != "") {
                $products = array_values(array_filter($products, function ($var) use ($item_id) {
                    return $var['item_id'] == $item_id;
                }));
            }

            if ($supplier_id != "") {
                $products = array_values(array_filter($products, function ($var) use ($supplier_id) {
                    return $var['supplier_id'] == $supplier_id;
                }));
            }


            $products = array_values(array_filter($products, function ($var) use ($start_date, $end_date) {
                return ($var['inward']['r_date'] >= $start_date) && ($var['inward']['r_date'] <= $end_date);
            }));


            if ($ec != "") {
                if ($ec == 1) {
                    $products = array_values(array_filter($products, function ($var) use ($ec) {
                        return ($var['inward']['aec'] < 0.5);
                    }));
                } else if ($ec == 2) {
                    $products = array_values(array_filter($products, function ($var) use ($ec) {
                        return ($var['inward']['aec'] < 1);
                    }));
                } else if ($ec == 3) {
                    $products = array_values(array_filter($products, function ($var) use ($ec) {
                        return ($var['inward']['aec'] < 1.5);
                    }));
                } else if ($ec == 4) {
                    $products = array_values(array_filter($products, function ($var) use ($ec) {
                        return ($var['inward']['aec'] < 2);
                    }));
                } else if ($ec == 5) {
                    $products = array_values(array_filter($products, function ($var) use ($ec) {
                        return ($var['inward']['aec'] > 3);
                    }));
                }
            }

            $products_data = $products;
            $totalInwardNet = 0;
            $inward_id = "";
            if (!empty($products)) {
                foreach ($products as $key => $value) {
                    if ($inward_id != $value['inward']['id']) {
                        $inward_id = $value['inward']['id'];
                    }
                    $totalInwardNet += (int) $value['item_value'];
                }
            }

            $data['products'] = $products_data;
            $data['totalInwardNet'] = $totalInwardNet;

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
            $data = Inward::select(
                'id',
                'location_id',
                'r_date',
                'week',
                'in_time',
                'out_time',
                'duration',
                'inv_no',
                'inv_date',
                'lwt',
                'ewt',
                'nwt',
                'ecu',
                'ecm',
                'ecl',
                'aec',
                'm1',
                'm2',
                'm3',
                'am',
                'sand',
                'fibre',
                'a_bagwt',
                'vehicle_no',
                'freight',
                'transporter',
                'storage_location',
                'qc_name',
                'remarks'
            )->where('id', $id)->first();
            $data->products = Product::where('inward_id', $id)->get();
            $response = ['type' => "success", 'data' => $data, 'msg' => "Inward Data fetch successfully"];
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
                'lwt' => 'required',
                'ewt' => 'required',
            ]);

            if ($validator->fails()) :
                $errors = implode(" ", array_flatten(array_values($validator->errors()->getMessages())));
                $response = ['type' => "error", 'errors' => $errors];
                return response()->json($response, 200);
            endif;

            $location_id = $request->location_id;
            $data = $request->all();
            $id = $request->id;
            $user_id = Auth::user()->id;

            if ($id) {
                $inward = Inward::findOrFail($id);
            } else {
                $inward = new Inward();
                $data['created_by'] = $user_id;
            }
            $data['updated_by'] = $user_id;
            $inward->fill($data);
            $inward->save();

            if (empty($data['products'])) {
                $response = ['type' => "error", 'msg' => "Please fill item's fileds"];
                return response()->json($response, 422);
            }

            \Log::useDailyFiles(storage_path() . env('ACTION_LOG_FOLDER') . env('ACTION_LOG_FILE_NAME'));
            \Log::info("Inward Update Start for ID:" . $id);

            foreach ($data['products'] as $key => $value) {
                // if ($value[$key] == "" || $value[$key] == null) {
                //     $response = ['type' => "error", 'msg' => "Please fill item's fileds"];
                //     return response()->json($response, 422);
                // }
                $this->createBalance($location_id, $id, $value, true);

                $value['inward_id'] = $inward->id;
                unset($value['id']);
                $product = new Product();
                $product->fill($value);
                $product->save();
                if ($id) {
                    $this->deleteLog(null, [$product], "new");
                }
            }

            //Delete Process
            $deletedIds = (!empty($data['deletedIds'])) ? $data['deletedIds'] : [];
            if (!empty($deletedIds) && $id) {
                $old_all_products = $forProducts = Product::whereIn('id', $deletedIds)->get();
                foreach ($old_all_products->toArray() as $old_product) {
                    $old_product['item_value'] = 0;
                    $old_product['bags'] = 0;
                    $this->createBalance($location_id, $id, $old_product, false);
                }
                $delete = Product::whereIn('id', $deletedIds)->delete();
                if ($delete) {
                    $this->deleteLog(null, $forProducts);
                }
            }
            DB::commit();
            \Log::info("Inward Update End for ID:" . $id);

            $response = ['type' => "success", 'result' => $inward, 'msg' => "Inward Data saved successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            //dd($e->getMessage());
            DB::rollback();
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }

    public function createBalance($location_id, $id, $value, $forcedelete)
    {
        $balance_check = Balance::where('location_id', $location_id)->where('item_id', $value['item_id'])->first();
        if ($balance_check) {
            $total_inward = $balance_check->total_inward;
            $total_inbag = $balance_check->total_inbag;
            if ($id && $value['id'] != "" && $value['id'] != null) {
                $old_product = $forProducts = Product::findOrFail($value['id']);
                if ($old_product) {
                    $old_item_value = $old_product->item_value;
                    $old_bags = $old_product->bags;
                    $total_inward = ($total_inward > $old_item_value) ?
                        $total_inward - $old_item_value : $old_item_value - $total_inward;
                    $total_inbag = ($old_bags > $total_inbag) ?
                        $old_bags - $total_inbag : $total_inbag - $old_bags;
                    if ($forcedelete) {
                        $this->deleteLog(null, [$forProducts], "update");
                        $old_product->forcedelete();
                    }
                }
            }
            $item_value = $total_inward + $value['item_value'];
            $bags = $total_inbag + $value['bags'];
            $balance = Balance::findOrFail($balance_check->id);
        } else {
            $balance = new Balance();
            $item_value = $value['item_value'];
            $bags = $value['bags'];
        }
        $balance_data = [
            'location_id' => $location_id,
            'item_id' => $value['item_id'],
            'total_inward' => $item_value,
            'total_inbag' => $bags,
            'balance' => $item_value - $balance->total_outward,
            'balance_bag' => $bags - $balance->total_outbag,
        ];
        $balance->fill($balance_data);
        $balance->save();
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            \Log::useDailyFiles(storage_path() . env('ACTION_LOG_FOLDER') . env('ACTION_LOG_FILE_NAME'));
            $id = $request->id;
            \Log::info("Inward Delete Start for ID:" . $id);
            $user_id = Auth::user()->id;
            //Delete Process
            $inward = $forInward = Inward::findOrFail($id);
            $old_all_products = $forProducts =  Product::where('inward_id', $id)->get();
            foreach ($old_all_products->toArray() as $old_product) {
                $old_product['item_value'] = 0;
                $old_product['bags'] = 0;
                $this->createBalance($inward->location_id, $id, $old_product, false);
            }
            Product::where('inward_id', $id)->delete();
            $delete = $inward->delete();
            DB::commit();
            //Log
            if ($delete) {
                $this->deleteLog($forInward, $forProducts);
            }
            \Log::info("Inward Delete End for ID:" . $id);
            $response = ['type' => "success", 'data' => $inward, 'msg' => "Inward Data delete successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollback();
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }

    public function deleteLog($forParent = null, $forChild = [], $action = "delete")
    {
        $events = [];
        if ($forParent || $forParent != null || $forParent != "") {
            $event['module'] = "Inward";
            $event['action'] = $action;
            $event['record'] = $forParent->toArray();
            $events[] = $event;
        }
        if (!empty($forChild)) {
            foreach ($forChild as $key => $value) {
                $event1['module'] = "Inward-Products";
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
                $msg .= $module . "-" . $action . "\r" . implode("-", $result);
            }
        }
        event(new ActionLog($msg));
    }
}
