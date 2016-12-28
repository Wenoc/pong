<?php
namespace pong\controllers;
require __DIR__ . '/../../vendor/autoload.php';

class GenericController
{
	public $db;
	public $out = array();
	public $K = 32; // Magic variable
	public $DRIFT = 0; // Drift coefficient (losers will lose less than the winner gains)
	public $USEFLOOR=0; // Use floor system
	public $FLOOR=100; // Minimum amount of ELO ranking one can have.

	function __construct(){
		$this->db = new \pong\controllers\DB();
	}

	// We have to have this function, obviously.
	public function get_out(){
		return $this->out;
	}

	// Makes an output array thing. To support all kinds of interfaces.
	public function add_out($str,$key,$status=null){
		if(isset($this->out[$key])){
			if(is_array($this->out[$key])){
				$this->out[$key][] = $str;
			} else {
				$this->out[$key] = array($this->out[$key],$str);
			}
		} else {
			$this->out[$key] = $str;
		}
		if($status !== null){
			$this->out["status"] = $status;
		}
		print_r($this->out);
	}

	// Inserts new player into db.
	function insert_new_player($player)
	{
		$player = strtolower($player);
		if($this->db->player_exists($player)){
			$this->add_out("Player '$player' already exists.","msg","ERROR");
		} else {
			$this->db->insert_new_player($player);
			$this->add_out("New player $player entered with 0 points.","msg","OK");
		}
		return;
	}

	// Inserts new game and calculates ELO.
	function insert_new_game($p1,$p2,$winner)
	{
		$p1 = strtolower($p1);
		$p2 = strtolower($p2);
		foreach(array($p1,$p2) as $player){
			if(!$this->db->player_exists($player)){
				$this->add_out("Player '$player' does not exist. Please register.","msg","ERROR");
				return;
			}
		}
		$result = "0"; // In case we want to store the game result at some point in the future.
		$p1_elo = $this->db->get_elo($p1);
		$p2_elo = $this->db->get_elo($p2);
		$newelo = $this->calc_elo($p1,$p2,$winner);
		$record = array(
			"player1" => $p1,
			"player2" => $p2,
			"winner" => $winner,
			"player1_old_elo"=>$p1_elo,
			"player2_old_elo"=>$p2_elo,
			"player1_new_elo"=>$newelo[0],
			"player2_new_elo"=>$newelo[1],
			"match_result" => $result);

		$result = $this->db->do_insert_game($record);
		$this->db->update_elo($p1,$newelo[0]);
		$this->db->update_elo($p2,$newelo[1]);
		$this->add_out("New game added.","msg");
		$this->add_out(array($p1 => array("old"=>$p1_elo, "new"=>$newelo[0]), 
			$p2 => array("old"=>$p2_elo,"new"=>$newelo[1])),
		"elo","OK");
	}


	// Calculates new ELO rank
	function calc_elo($p1,$p2,$winner)
	{
		$p1 = trim(strtolower($p1));
		$p2 = trim(strtolower($p2));
		$p1_elo = $this->db->get_elo($p1);
		$p2_elo = $this->db->get_elo($p2);
		if($this->USEFLOOR){
			if($p1_elo < $this->FLOOR) $p1_elo = $this->FLOOR;
			if($p2_elo < $this->FLOOR) $p2_elo = $this->FLOOR;
		}
		$R1 = pow(10, $p1_elo/400);
		$R2 = pow(10, $p2_elo/400);
		$E1 = $R1 / ($R1 + $R2);
		$E2 = $R2 / ($R1 + $R2);
		if($winner=="draw"){
			$S1 = 0.5;
			$S2 = 0.5;
		} else {
			$S1 = ($winner==$p1 ? 1 : 0 + $this->DRIFT);
			$S2 = ($winner==$p2 ? 1 : 0 + $this->DRIFT);
		}
		$r1 = $p1_elo+($this->K*($S1-$E1));
		$r2 = $p2_elo+($this->K*($S2-$E2));
		if($this->USEFLOOR){
			$r1 = (int)max($this->FLOOR,$r1);
			$r2 = (int)max($this->FLOOR,$r2);
		} 
//		$this->add_out("p1:$p1 p2:$p2 p1_elo:$p1_elo p2_elo:$p2_elo R1:$R1 R2:$R2 E1:$E1 E2:$E2 S1:$S1 S2:$S2 r1:$r1 r2:$r2 winner:($winner)");
		return array($r1,$r2);
	}
	public function pretty_elo(){
		if(!$this->out["elo"])
			return "";
		$str = "";
		foreach($this->out["elo"] as $player => $arr){
			$diff = round($arr["new"]-$arr["old"],1,PHP_ROUND_HALF_UP);
			$str.= $player . ": ".round($arr["new"],0,PHP_ROUND_HALF_UP)."(".($diff > 0 ? "+" : "").(string)$diff.")  ";
		}
		return $str;
	}
	public function pretty_score($n) {
		$arr = $this->db->get_stats($n);
		$out = "Scoreboard".($n?" ($n)":"")."\n-------------------------------------\n";
		$out.= "Player                  Elo  Games\n";
		foreach($arr as $player){
			$out.=str_pad($player["name"], 21).
			str_pad((string)$player["elo"],6," ",STR_PAD_LEFT).
			str_pad((string)$player["games"],6," ",STR_PAD_LEFT)."\n";
		}
		return $out;
	}

}
?>

