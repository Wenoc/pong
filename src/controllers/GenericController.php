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
	//	print_r($this->out);
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
		if($winner != "draw"){
			$this->db->add_win(($p1==$winner?$p1:$p2));
			$this->db->add_loss(($p1==$winner?$p2:$p1));
		}
		$result = $this->db->do_insert_game($record);
		$this->db->update_elo($p1,$newelo[0]);
		$this->db->update_elo($p2,$newelo[1]);
		$this->add_out("New game added.","msg");
		$this->add_out(array($p1 => array("old"=>$p1_elo, "new"=>$newelo[0]), 
			$p2 => array("old"=>$p2_elo,"new"=>$newelo[1])),
		"elo","OK");
		if($this->tournament_test_players_should_play($p1,$p2)){
			$res = $this->tournament_register_win($p1,$p2,$winner);
			if($res==1)
				$this->add_out("Tournament win registered, $winner won!"."msg","OK");
			if($res==-1){
				$this->add_out("We have a tournament winner! Three cheers for $winner!","msg","OK");
				$this->tournament_finish($winner);
			}
		} else {
			$this->add_out("No tournament game pending.","msg","OK");
		}
	}
	// Creates a new tournament if none is going on.
	function tournament_init($name) {
		if($this->db->tournament_is_initialized()){
			$this->add_out("There is already a tournament in progress!","msg","ERROR");
			return;
		} else {
	 		$this->add_out("Tournament $name has been created!","msg","OK");
	 		return $this->db->tournament_init($name);
	 	}
	}
	function tournament_finish($winner = 0){
		return $this->db->tournament_finish($winner);
	}
	function tournament_register($name){		
		$msg = $this->db->tournament_register_player($name);
		if($msg)
			$this->add_out($msg,"msg","ERROR");
	}
	function tournament_start(){
		if(!$this->db->tournament_is_initialized()){
			$this->add_out("Tournament has not been created yet.","msg","ERROR");
			return;
		}
		if($this->db->tournament_is_started()){
			$this->add_out("Tournament has already started","msg","ERROR");
			return;
		}
		$tournament_id = $this->db->tournament_get_active_id();
		$players = $this->db->tournament_get_players($tournament_id);

		if(count($players) < 2){
			$this->add_out("Tournament needs at least two players.","msg","ERROR");
			return;
		}


		$pairs = array();		
		$head_honcho = null;
		if(count($players)%2){ // If the amount of participants is uneven, we remove the top player who can skip first round. 
//			echo "WE HAVE A HEAD HONCHO\n";
			$head_honcho = $players[0];
//			$pairs[] = array($players[0],null);
			unset($players[0]);
		}
		$players = array_reverse($players); // This is because if the amount of players isn't a factor of 2^N, the last group will be the smallest
											// and the worst players would get to skip a tier. Doing it reversed means the best players will get to skip.
		while(count($players)){
			$middleElem = ceil(count($players)/2);
			$keys = array_keys($players);
			$middleKey = $keys[$middleElem];
			$topKey = $keys[0];
			$pairs[] = array($players[$topKey],$players[$middleKey]);
			unset($players[$topKey]);
			unset($players[$middleKey]);
		}
		
//		echo "BEFORE head_honcho:".$head_honcho."\n";
//		echo print_r($pairs,true);
//		$pairs = $array_chunk($pairs,2);
		if($head_honcho){
			$spot = ceil(count($pairs)/2);
			$key = array_keys($pairs)[$spot];
			$pairs[$key] = array($head_honcho,$pairs[$key]);
		}
		while(count($pairs) > 2)
		{
			$pairs = array_chunk($pairs,2);
		}
		echo print_r($pairs,true);
//		$final_id = $this->tournament_new_game($tournament_id,0,null,null); 
		$this->tournament_game_iter($pairs,0,$tournament_id);
		$this->db->tournament_start($tournament_id);
		$this->add_out("Tournament has started!","msg","OK");			
		return $pairs;
	}

	function tournament_new_game($tournament_id,$parent_game=null,$player1=null,$player2=null){
		return $this->db->tournament_new_game($tournament_id,$parent_game,$player1,$player2);
	}

	function tournament_game_iter($pairs,$parent,$tournament_id){
		echo "Inserting : ".print_r($pairs,true)."\n";
		if(isset($pairs[0]["name"])){
			$this->tournament_new_game($tournament_id,$parent,$pairs[0]["name"],$pairs[1]["name"]);
		} else {
			if(count($pairs) > 1)
				$parent = $this->tournament_new_game($tournament_id,$parent);
			foreach($pairs as $match){
				$this->tournament_game_iter($match,$parent,$tournament_id);
			}
		}
	}

/*	function tournament_game_iter($pairs, $parent, $tournament_id)
	{
		if(!isset($pairs[0]["name"]) && isset($pairs[1])) {
			$this->tournament_new_game($tournament_id,$this->tournament_game_iter($pairs,$parent,$tournament_id));
			$this->
		}
	}
*/
	function tournament_test_players_should_play($p1,$p2) {
		if(!$this->db->tournament_is_started()){
			$this->add_out("No tournament started.","msg","OK");
			return 0;
		}
		$tournament_id = $this->db->tournament_get_active_id_in_progress();
		$tournament_players = $this->db->tournament_get_players($tournamnent_id);
		if(!$this->db->tournament_player_has_signed($p1)){
			$this->add_out("Player $p1 has not signed up for the tournament.");
			return 0;
		}
		if(!$this->db->tournament_player_has_signed($p2)){
			$this->add_out("Player $p2 has not signed up for the tournament.");
			return 0;
		}
		if($this->db->tournament_game_pending($p1,$p2)){
			$this->add_out("Players have no game pending.","msg","OK");
			return 1;
		}
		return 0;
	}
	function tournament_register_win($p1,$p2,$winner) {
		return $this->db->tournament_register_win($p1,$p2,$winner);
	}
/*
	function divide_and_conquer($pairs)
	{
			if(count($pairs)<3)
			return $pairs;
		if(count($pairs) == 3){
			$keys = array_keys($pairs);
			return array(array($pairs[$keys[1]],$pairs[$keys[2]]),$pairs[$keys[0]]);
		}
		return array_chunk($pairs,2);
	}
*/
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
		return array($r1,$r2);
	}


	/******************/
	/* Visual outputs */
	/******************/
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
		$out = "Scoreboard".($n?" ($n)":"")."\n----------------------------------\n";
		$out.= "Player      Elo  Games Wins Losses\n";
		foreach($arr as $player){
			$out.=str_pad($player["name"], 10).
			str_pad((string)$player["elo"],5," ",STR_PAD_LEFT).
			str_pad((string)$player["games"],6," ",STR_PAD_LEFT).
			str_pad((string)$player["wins"],6," ",STR_PAD_LEFT).
			str_pad((string)$player["losses"],6," ",STR_PAD_LEFT).
			"\n";
		}
		return $out;
	}
	function tournament_pretty(){
		$tournament = $this->db->tournament_get_active_id("all");	
//		print_r($tournament);
		return $tournament;
	}
}
?>

