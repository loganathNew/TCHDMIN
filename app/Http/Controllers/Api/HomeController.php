<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Product;
use App\OutwardProduct;
use App\Item;
use App\Balance;
use App\Location;
use DB;
use App\Events\ActionLog;
use App\Supplier;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    //
    // Not in use
    public function getAllOld(Request $request)
    {
        try {
            $filter_datas = explode("&", $request->filter_data);
            $location_id = $filter_datas[0];
            $filter_item_id = $filter_datas[1];
            $start_date = (!empty($filter_datas[2])) ? $filter_datas[2] : date("Y-m-01", strtotime(""));
            $end_date = (!empty($filter_datas[3])) ? $filter_datas[3] : date("Y-m-d");

            $inwards = Product::select(DB::raw('id,inward_id,item_id, item_name, item_value'))
                ->with(['inward' => function ($q) {
                    $q->select(
                        'id',
                        'location_id',
                        'r_date'
                    );
                }])->get()->toArray();

            if ($location_id != "") {
                $inwards = array_values(array_filter($inwards, function ($var) use ($location_id) {
                    return $var['inward']['location_id'] == $location_id;
                }));
            }

            $inwards = array_values(array_filter($inwards, function ($var) use ($start_date, $end_date) {
                return ($var['inward']['r_date'] >= $start_date) && ($var['inward']['r_date'] <= $end_date);
            }));

            $outwards = OutwardProduct::select(DB::raw('items,outward_id'))
                ->with(['outward' => function ($q) {
                    $q->select(
                        'id',
                        'location_id',
                        'date'
                    );
                }])->get()->toArray();

            if ($location_id != "") {
                $outwards = array_values(array_filter($outwards, function ($var) use ($location_id) {
                    return $var['outward']['location_id'] == $location_id;
                }));
            }


            $data = [];
            foreach ($inwards as $key => $inward) {
                $item_id = $inward['item_id'];
                $item_value = $inward['item_value'];
                $data[$item_id]['inward'][] = $item_value;
                $data[$item_id]['item_id'][] = $item_id;
                $data[$item_id]['location_id'][] = $inward['inward']['location_id'];
                $data[$item_id]['item_name'][] = $inward['item_name'];
                if (empty($data[$item_id]['outward'])) {
                    $data[$item_id]['outward'][] = 0;
                }
            }

            foreach ($outwards as $key => $outward) {
                foreach ($outward['items'] as $item_id => $item_value) {
                    $data[$item_id]['outward'][] = (int) $item_value;
                    $data[$item_id]['item_id'][] = $item_id;
                    $data[$item_id]['location_id'][] = $outward['outward']['location_id'];
                    $data[$item_id]['item_name'][] = Item::where('id', $item_id)->value("name");
                    if (empty($data[$item_id]['inward'])) {
                        $data[$item_id]['inward'][] = 0;
                    }
                }
            }

            if ($filter_item_id != "") {
                $data = (!empty($data[$filter_item_id])) ? $data[$filter_item_id] : $data;
            }

            $final_data = [];
            $items = Item::select('id', 'name')->pluck('name', 'id')->toArray();
            $locations = Location::select('id', 'name')->pluck('name', 'id')->toArray();
            foreach ($locations as $locationID => $locationName) {
                // if (!empty($final_data[$locationName]) && $final_data[$locationName] != $locationName) {
                foreach ($items as $itemID => $itemName) {
                    // if ($final_data[$locationName][$itemName]['item_id'] != $itemID) {
                    $final_data[$locationName][$itemName]['location_id'] = $locationID;
                    $final_data[$locationName][$itemName]['item_name'] = $itemName;
                    $final_data[$locationName][$itemName]['item_id'] = $itemID;
                    $final_data[$locationName][$itemName]['totalInwardNet'] = 0;
                    $final_data[$locationName][$itemName]['totalOutwardNet'] = 0;
                    // }
                }
                // }
            }


            foreach ($data as $key => $value) {
                $item_id = array_unique($value['item_id'])[0];
                $location_id = array_unique($value['location_id'])[0];
                $location_name = Location::where('id', $location_id)->value("name");
                $item_name = array_unique($value['item_name'])[0];
                $totalInwardNet = array_sum($value['inward']);
                $totalOutwardNet = array_sum($value['outward']);

                $final_data[$location_name][$item_name]['location_id'] = $location_id;
                $final_data[$location_name][$item_name]['item_name'] = $item_name;
                $final_data[$location_name][$item_name]['item_id'] = $item_id;
                $final_data[$location_name][$item_name]['totalInwardNet'] = $totalInwardNet;
                $final_data[$location_name][$item_name]['totalOutwardNet'] = $totalOutwardNet;
            }



            $response = ['type' => "success", 'data' => $final_data, 'msg' => "Dashboard Data fetch successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            //dd($e->getMessage());
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }



    public function getAll(Request $request)
    {
        try {
            $filter_datas = explode("&", $request->filter_data);
            $location_id = $filter_datas[0];
            $filter_item_id = $filter_datas[1];
            // $start_date = (!empty($filter_datas[2])) ? $filter_datas[2] : date("Y-m-01", strtotime(""));
            // $end_date = (!empty($filter_datas[3])) ? $filter_datas[3] : date("Y-m-d");

            $balances = Balance::get()->toArray();
            $items = Item::get()->toArray();
            $locations = Location::get()->toArray();

            if ($location_id != "") {
                $balances = array_values(array_filter($balances, function ($var) use ($location_id) {
                    return $var['location_id'] == $location_id;
                }));
            }

            $final_data = [];
            $duplicate_data = [];

            foreach ($items as $key1 => $item) {
                foreach ($locations as $key => $location) {
                    $item_id = $item['id'];
                    $location_id = $location['id'];

                    $balance = Balance::where('location_id', $location_id)->where('item_id', $item_id)->first();

                    if ($balance) {
                        $total_inward = $balance['total_inward'];
                        $total_outward = $balance['total_outward'];
                        $total_in_bag = $balance["total_inbag"];
                        $balance = $balance['balance'];
                    } else {
                        $total_inward = $total_outward = $balance = $total_in_bag = 0;
                    }

                    $location_name = Location::where('id', $location_id)->value("name");
                    $item_name = Item::where('id', $item['id'])->value("name");
                    $total_item_value = Balance::where('item_id', $item['id'])->sum("balance");
                    $tmp_total_bags = Balance::where('item_id', $item['id'])->sum("balance_bag");

                    $final_data[$location_id][$item_id]['location_id'] = $location_id;
                    $final_data[$location_id][$item_id]['location_name'] = $location_name;
                    $final_data[$location_id][$item_id]['item_name'] = $item_name;
                    $final_data[$location_id][$item_id]['total_item_value'] = $total_item_value;
                    $final_data[$location_id][$item_id]['tmp_total_bags'] = $tmp_total_bags;
                    $final_data[$location_id][$item_id]['item_id'] = $item['id'];
                    $final_data[$location_id][$item_id]['total_inward'] = $total_inward;
                    $final_data[$location_id][$item_id]['total_outward'] = $total_outward;
                    $final_data[$location_id][$item_id]['balance'] = $balance;
                    $final_data[$location_id][$item_id]['total_inbag'] = $total_in_bag;
                    $final_data[$location_id]['location_name'] = $location_name;
                }
            }


            // foreach ($items as $key1 => $item) {
            //     foreach ($balances as $key => $balance) {
            //         $item_id = $balance['item_id'];
            //         $location_id = $balance['location_id'];

            //         $total_inward = $balance['total_inward'];
            //         $total_outward = $balance['total_outward'];
            //         $total_in_bag = $balance["total_inbag"];
            //         $balance = $balance['balance'];

            //         echo $item['id'] . " - " . $item_id . "<br>";

            //         // if ($item['id'] != $item_id) {
            //         //     $total_inward = $total_outward = $balance = $total_in_bag = 0;
            //         // }

            //         $location_name = Location::where('id', $location_id)->value("name");

            //         if ($item['id'] != $item_id) {
            //             $item_name = Item::where('id', $item['id'])->value("name");
            //             $duplicate_data[$location_id][$item['id']]['location_id'] = $location_id;
            //             $duplicate_data[$location_id][$item['id']]['location_name'] = $location_name;
            //             $duplicate_data[$location_id][$item['id']]['item_name'] = $item_name;
            //             $duplicate_data[$location_id][$item['id']]['item_id'] = $item['id'];
            //             $duplicate_data[$location_id][$item['id']]['total_inward'] = 0;
            //             $duplicate_data[$location_id][$item['id']]['total_outward'] = 0;
            //             $duplicate_data[$location_id][$item['id']]['balance'] = 0;
            //             $duplicate_data[$location_id][$item['id']]['total_inbag'] = 0;
            //             $duplicate_data[$location_id]['location_name'] = $location_name;
            //         } else {
            //             $item_name = Item::where('id', $item['id'])->value("name");
            //             $final_data[$location_id][$item_id]['location_id'] = $location_id;
            //             $final_data[$location_id][$item_id]['location_name'] = $location_name;
            //             $final_data[$location_id][$item_id]['item_name'] = $item_name;
            //             $final_data[$location_id][$item_id]['item_id'] = $item['id'];
            //             $final_data[$location_id][$item_id]['total_inward'] = $total_inward;
            //             $final_data[$location_id][$item_id]['total_outward'] = $total_outward;
            //             $final_data[$location_id][$item_id]['balance'] = $balance;
            //             $final_data[$location_id][$item_id]['total_inbag'] = $total_in_bag;
            //             $final_data[$location_id]['location_name'] = $location_name;
            //         }
            //     }
            // }

            // $final_data = array_merge($duplicate_data,$final_data);
            // $final_data = array_merge($duplicate_data, $final_data);

            $response = ['type' => "success", 'data' => $final_data, 'msg' => "Dashboard Data fetch successfully"];
            return response()->json($response, 200);
        } catch (\Exception $e) {
            //dd($e->getMessage());
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }

    public function getSelectDatas($id)
    {
        try {
            // DB::raw("CAST(id as CHAR) as ids"),
            $tables = [
                'item' => 'App\Item', 'location' => "App\Location",
                'storage_location' => "App\StorageLocation", 'qc_name' => "App\QcName", 'supplier' => "App\Supplier"
            ];

            foreach ($tables as $table_key => $table) {
                $query = $table::select(DB::raw("CONVERT(id,CHAR) as ids"), 'name', 'des', 'value');
                if ($id != null && $id != 'null') {
                    $query->withTrashed();
                }
                $results = $query->get()->toArray();
                $data = [];
                foreach ($results as $key => $result) {
                    $result['id'] = $result['ids'];
                    unset($result['ids']);
                    $data[] = $result;
                }
                // [0 => ['id' => "", "name" => 'Please select Item', "des" => "", "value" => '0']
                $datas[$table_key] = array_merge([0 => ['id' => "", "name" => 'Please select', "des" => "", "value" => '0']], $data);
            }
            $ecs = [
                ['id' => "1", "name" => "below 0.5", "value" => "1"],
                ['id' => "2", "name" => "below 1", "value" => "2"],
                ['id' => "3", "name" => "below 1.5", "value" => "3"],
                ['id' => "4", "name" => "below 2", "value" => "4"],
                ['id' => "5", "name" => "above 3", "value" => "5"]
            ];
            $datas['ecs'] =  array_merge([0 => ['id' => "", "name" => 'Please select ecs', "value" => '']], $ecs);
            return response()->json($datas, 200);
        } catch (\Exception $e) {
            //dd($e->getMessage());
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }

    public function getLogfiles()
    {
        try {
            $directory = storage_path() . env('ACTION_LOG_FOLDER');
            $backendPath =  env('DOWNLOAD_BACKEND_PATH') . env('ACTION_LOG_FOLDER');
            $files = scandir($directory);
            $results = [];
            if (!empty($files)) {
                foreach ($files as $key => $file) {
                    if (str_contains($file, 'log')) {
                        $results[] = ['name' => $file, 'path' => $backendPath . $file];
                    }
                }
            }
            $data['results'] = $results;
            return response()->json($data, 200);
        } catch (\Exception $e) {
            $response = ['type' => "error", 'msg' => $e->getMessage()];
            return response()->json($response, 422);
        }
    }
}
