<?php
namespace pong;
//echo phpinfo();
require_once("DB.php");

class GenericController
{
	public $db;
	public $out = array("ok"=>array(),"errors"=>array());
	public $K = 32; // Magic variable
	public $DRIFT = 0; // Drift coefficient (losers will lose less than the winner gains)
	public $USEFLOOR=0; // Use floor system
	public $FLOOR=100; // Minimum amount of ELO ranking one can have.

	function __construct(){
		$this->db = new DB();
	}
	public function get_out(){
		return $this->out;
	}
	public function add_out($wat,$type = "ok"){
		if($error)
			$this->out["errors"][] = $wat;
		$this->out["ok"][] = $wat;
		return $this->out;
	}

	function insert_new_player($player)
	{
		$player = strtolower((string)$_POST["player"]);
		if($this->db->player_exists($player)){
			$this->out["errors"][] = "Player $player already exists!";
		} else {
			$this->db->insert_new_player($player);
			$this->out["ok"][] = "New player $player entered with 0 points.";
		}
		return $this->out;
	}

	function insert_new_game($p1,$p2,$winner)
	{
		foreach(array($p1,$p2) as $player){
			if(!$this->db->player_exists($player)){
				return array("errors" => "Player $player does not exist!");
			}
		}
		$this->add_out("inserting new game with p1=$p1 p2=$p2 winner=$winner");
		$result = "0"; // In case we want to store the game result at some point in the future.
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
		$this->add_out("Game added.");
	}

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
}
?>

