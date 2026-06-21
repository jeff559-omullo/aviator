<?php

namespace App\Http\Controllers;

use App\Models\Gameresult;
use App\Models\Setting;
use App\Models\Userbit;
use Illuminate\Http\Request;
use Carbon\Carbon;

class Gamesetting extends Controller
{
    
    public function crash_plane()
    {
        return 1;
    }
    public function game_existence(Request $r)
    {
        $event = $r->event;
        if ($event == "check") {
            $new = Setting::where('category', 'game_status')->where('value', '0')->first();
            
            if ($new || (session()->has('gamegenerate') && session()->get('gamegenerate') == 1)) {
                return array('data'=>true);
            }else{
                return array('data'=>false);
            }
            return array('data'=>false);
        }
    }
    public function new_game_generated(Request $r)
    {
        Setting::updateOrInsert([
            'category' => 'game_status'
        ], [
            'value' => '0',
            'status' => '0'
        ]);
        $r->session()->put('gamegenerate','1');
        Setting::updateOrInsert([
            'category' => 'game_target'
        ], [
            'value' => '',
            'status' => '0'
        ]);
        Setting::updateOrInsert([
            'category' => 'game_start_time'
        ], [
            'value' => '',
            'status' => '0'
        ]);
        return response()->json(array("id" => currentid()));
    }
    
    public function increamentor(Request $r)
    {
        $gamestatusdata = Setting::firstOrCreate([
            'category' => 'game_status'
        ], [
            'value' => '0',
            'status' => '0'
        ]);
        $res = 0;

        $targetSetting = Setting::where('category', 'game_target')->first();
        $startTimeSetting = Setting::where('category', 'game_start_time')->first();

        if ($targetSetting && $targetSetting->value) {
            $res = floatval($targetSetting->value);
        } else {
            $totalbet = Userbit::where('gameid',currentid())->count();
            $totalamount = Userbit::where('gameid',currentid())->sum('amount');
            $u = rand(0, 10000) / 10000;
            $skewed = pow($u, 4);
            $res = 1 + ($skewed * 99);
            if ($totalbet > 0 && $totalamount > 0) {
                $riskFactor = min(0.9, 0.3 + min($totalamount / 100000, 0.6));
                $u = rand(0, 10000) / 10000;
                $skewed = pow($u, 4 + $riskFactor * 4);
                $res = 1 + ($skewed * 99);
            }
            $res = round($res, 2);
            Setting::updateOrInsert([
                'category' => 'game_target'
            ], [
                'value' => $res,
                'status' => '0'
            ]);
        }

        if ($startTimeSetting && $startTimeSetting->value) {
            $startTime = intval($startTimeSetting->value);
        } else {
            $startTime = Carbon::now()->timestamp * 1000 + 2000;
            Setting::updateOrInsert([
                'category' => 'game_start_time'
            ], [
                'value' => $startTime,
                'status' => '0'
            ]);
        }

                $status = true;
                $result = $res;
                $response = array('status'=>$status,'result'=>$result, 'start_time' => $startTime, 'server_time' => Carbon::now()->timestamp * 1000);
        return response()->json($response);
    }
    // public function increamentor(Request $r)
    // {
    //     // return 1.7;
    //     $totalbet = Userbit::where('gameid',currentid())->count();
    //     $totalamount = Userbit::where('gameid',currentid())->sum('amount');
    //     if ($totalbet == 0) {
    //         return rand(8,11);
    //     }else{
    //         $randomresult = array(1.1,1.1,1.2,1.3,1.4,1.5,1.6,1.7,1.8,1.9);
    //         $res = $randomresult[rand(0,8)];
    //         if (session()->has('result')) {
    //             return session()->get('result');
    //         }
    //         $r->session()->put('result',$res);
    //         return $res;
    //     }
    //     return rand(setting('start_range_game_timer')*10, setting('end_range_game_timer')*10) / 10;
    // }
    public function game_over(Request $r)
    {
        $r->session()->forget('result');
        $result = Gameresult::where('id', currentid())->update([
            "result" => number_format($r->last_time, 2),
        ]);
        $alluserbit = Userbit::where('gameid', currentid())->where('status', 0)->get();
        foreach ($alluserbit as $key) {
			if(floatval($r->last_time) <= 1.20){
			$result = 0;
		    }else{
			$result = $r->last_time;
			}
            $finalamount = floatval($key->amount) * floatval($result);
            Userbit::where('id', $key->id)->update(["status"=> 1]);
            addwallet($key->userid,$finalamount);
        }
        $new = Setting::where('category', 'game_status')->update(['value' => '0']);
        $r->session()->put('gamegenerate','0');
        $result = new Gameresult;
        $result->result = "pending";
        $result->save();

        // If this request was made by a logged-in user return their wallet,
        // otherwise return a simple ok response for admin-triggered calls.
        if (session()->has('userlogin')) {
            return wallet(user('id'));
        }
        return response()->json(['ok' => true]);
    }

    public function betNow(Request $r)
    {
        $status = false;
        $message = "Something went wrong!";
        $returnbets = array();
        for($i=0; $i < count($r->all_bets); $i++){
		$result = new Userbit;
        $result->userid = user('id');
        $result->amount = $r->all_bets[$i]['bet_amount'];
        $result->type = $r->all_bets[$i]['bet_type'];
        $result->gameid = currentid();
        $result->section_no = $r->all_bets[$i]['section_no'];
        if ($r->all_bets[$i]['bet_amount'] < wallet(user('id'), 'num')) {
            if ($result->save()) {
                $status = true;
                array_push($returnbets, [
                    "bet_id" => $result->id,
                ]);
				/*array_push($returnbets, [
                    "bet_id" => currentid(),
                ]);*/
                $exact_wallet_balance = addwallet(user('id'), floatval($r->all_bets[$i]['bet_amount']), "-");
                $data = array(
                    "wallet_balance" => wallet(user('id')),
                    "return_bets" => $returnbets
                );
                $message = "";
            }
        } else {
            $status = false;
            $data = array();
            $message = "Insufficient fund!!";
        }
		}
        $response = array("isSuccess" => $status, "data" => $data, "message" => $message);
        return response()->json($response);
    }
    public function currentlybet()
    {
        $allbets = Userbit::where("gameid", currentid())->join('users','users.id','=','userbits.userid')->get();
        $currentGameBet = $allbets;
        for ($i=0; $i < rand(400,900); $i++) { 
            $currentGameBet[]=array(
                "userid" => rand(10000,50000),
                "amount" => rand(999,9999),
				"image"  => "/images/avtar/av-".rand(1,72).".png"
            );
        }
        $currentGame = array("id"=>currentid());
        $currentGameBetCount = count($currentGameBet);
        $response = array("currentGame" => $currentGame, "currentGameBet" => $currentGameBet, "currentGameBetCount" => $currentGameBetCount);
        return response()->json($response);
    }
    public function my_bets_history(){
        $userid = user('id');
        $userbets = Userbit::where("userid", $userid)->where('status',1)->where('created_at', '>=', Carbon::today()->toDateString())->orderBy('id','desc')->get();
        return response()->json($userbets);
    }
	public function cashout(Request $r){
		$game_id = $r->game_id;
		if (!$game_id) {
			$game_id = currentid();
		}
		$bet_id = $r->bet_id;
		$win_multiplier = $r->win_multiplier;
		$cash_out_amount = 0;
		$status = false;
        $message = "";
        $data = array();

		$userbet = Userbit::where('id', $bet_id)->where('userid', user('id'))->first();
		if (!$userbet) {
			$message = 'Bet not found.';
			return response()->json(["isSuccess" => false, "data" => $data, "message" => $message]);
		}

		if (resultbyid($game_id) == 0) {
			$result = floatval($win_multiplier);
		} else {
			$result = floatval(resultbyid($game_id));
			if ($result <= 1.20) {
				$result = 0;
			}
		}

		$cash_out_amount = floatval($userbet->amount) * $result;
		if ($cash_out_amount > 0) {
			addwallet(user('id'), $cash_out_amount);
		}

		$data = array(
			"wallet_balance" => wallet(user('id'), "num"),
			"cash_out_amount" => $cash_out_amount
		);

        Userbit::where('id', $bet_id)->update(["status"=> 1, "cashout_multiplier" => $win_multiplier]);
        $status = true;
		$response = array("isSuccess" => $status, "data" => $data, "message" => $message);
        return response()->json($response);
	}
	
	public function cronjob(){
	    //0 = Game end & statrting soon
	    //1 = Game start & and is in proccess
	    $gamestatusdata = Setting::where('category', 'game_status')->first();
	    $game_status = 0;
	    if($gamestatusdata){
	        $game_status = $gamestatusdata->value;
	    }
	    if($game_status == 1){
	    $last_start_time = Setting::where('category', 'game_start_time')->first()->value;
	    $last_till_time = Setting::where('category', 'game_between_time')->first()->value;
	    $bothdifference = datealgebra($last_start_time, '+', ($last_till_time/1000).' seconds', $format = "Y-m-d h:i:s");
	    if(strtotime(date('Y-m-d h:i:s')) >= strtotime($bothdifference)){
	        $gamestatusdata = Setting::where('category', 'game_status')->update([
	             "value"  => 0
	             ]);
	    }
	    }elseif($game_status == 0){
	         $gamestatusdata = Setting::where('category', 'game_status')->update(["value"  => 1]);
	       //  $gamestatusdata = Setting::where('category', 'game_start_time')->update(["value"  => date('Y-m-d h:i:s')]);
	       //  $gamestatusdata = Setting::where('category', 'game_between_time')->update(["value"  => 5000]);
	    }else{}
	}
}























