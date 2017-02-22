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
			if($key == "msg"){
				$this->out["msg"] = $this->out["msg"]."\n".$str;
			} else {
				if(is_array($this->out[$key])){
					$this->out[$key][] = $str;
				} else {
					$this->out[$key] = array($this->out[$key],$str);
				}
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
		$this->tournament_check_and_register_win($p1,$p2,$winner);
	}






	/***************
	 * Tournaments *
	 ***************/
	// Creates a new tournament if none is going on.
	function tournament_create($name, $creator) {
		if(!$this->db->player_exists($creator)){
			$this->add_out("I don't know you, $creator","msg","ERROR");
			return 0;
		}
		if($this->db->tournament_is_initialized()){
			$this->add_out("There is already a tournament in progress!","msg","ERROR");
			return 0;
		} else {
			$this->add_out("Tournament $name has been created!","msg","OK");
			return $this->db->tournament_create($name,$creator);
		}
	}
	function tournament_finish($winner = 0){
		return $this->db->tournament_finish($winner);
	}
	function tournament_cancel($owner, $reason=null){
		$real_owner = $this->db->tournament_owner();
		if(trim(strtolower($owner)) != $real_owner){
			if($this->db->tournament_owner_inactive()){
				$this->db->tournament_cancel("cancelled by $owner".($reason?", reason: $reason":""));
				$this->add_out("Tournament cancelled by $owner.","msg","OK");
				return 1;
			} else {
				$this->add_out("You are not the owner of the running tournament, $owner. Please ask $real_owner to cancel it for you.","msg","ERROR");
				return 0;
			}
		} else {
			$this->db->tournament_cancel("cancelled by $owner".($reason?", reason: $reason":""));
			$this->add_out("Tournament cancelled by $owner.","msg","OK");			
		}
	}
	function tournament_register($name){		
		$msg = $this->db->tournament_register_player($name);
		if($msg==0)
			$this->add_out("Failed to add player. Aready registered or no active tournament.","msg","ERROR");
		else 
			$this->add_out("Player $name signed up for the tournament!","msg","OK");
	}
	function tournament_start($user){
		$real_owner = $this->db->tournament_owner();
		if(trim(strtolower($user)) != $real_owner &&
			!$this->db->tournament_owner_inactive() &&
			!$this->db->is_admin($user)) {
			$this->add_out("You do not have the priviliges to do that, nor are you the owner of the tournament. The owner is $real_owner.","msg","OK");
			return 1;
		}

		if(!$this->db->tournament_is_initialized()){
			$this->add_out("Tournament has not been created yet.","msg","ERROR");
			return;
		}
		if($this->db->tournament_is_started()){
			$this->add_out("Tournament has already started","msg","ERROR");
			return;
		}
		$tournament_id = $this->db->tournament_get_active_id();
		$ret_players = $this->db->tournament_get_players($tournament_id);

		if(count($ret_players) < 4){
			$this->add_out("Tournament needs at least four players. Otherwise you would have no misery or tears to suckle and nourish yourself on.","msg","ERROR");
			return;
		}
		$players = $ret_players;
		$pairs = array();
		//$this->add_out(print_r($players,true),"msg","OK");
		$head_honcho = null;
		if(count($players)%2){ // If the amount of participants is uneven, we remove the top player who can skip first round. 
			$head_honcho = $players[0];
			unset($players[0]);
		}
		$players = $this->playershuffle($players);
		if($head_honcho)
			$players[] = $head_honcho;
		$tournament = $this->split_and_pair($players);
		$this->add_out(print_r($tournament,true),"msg","OK");
		$this->tournament_game_iter($tournament,0,$tournament_id);
		$this->db->tournament_start($tournament_id);
	}

	function tournament_check_and_register_win($p1,$p2,$winner) {
		if($this->tournament_test_players_should_play($p1,$p2)){
			$res = $this->tournament_register_win($p1,$p2,$winner);
			if($res==1)
				$this->add_out("Tournament game registered, $winner won a match!","msg","OK");
			if($res==-1){
				$this->add_out("We have a tournament winner! Three cheers for $winner!","msg","OK");
				$this->tournament_finish($winner);
			}
		}
	}
	function tournament_new_game($tournament_id,$parent_game=null,$player1=null,$player2=null){
		return $this->db->tournament_new_game($tournament_id,$parent_game,$player1,$player2);
	}

	function tournament_game_iter($pairs,$parent,$tournament_id){
		if(isset($pairs[0]["name"]) && isset($pairs[1]["name"])){
			$this->tournament_new_game($tournament_id,$parent,$pairs[0]["name"],$pairs[1]["name"]);
		} else {
			if(count($pairs) == 1){
				$this->tournament_game_iter($pairs[0],$parent,$tournament_id);
			} else {
				if(isset($pairs[0]["name"])){
					$parent = $this->tournament_new_game($tournament_id, $parent, $pairs[0]["name"]);
					$this->tournament_game_iter($pairs[1],$parent,$tournament_id);
				} else {
					$parent = $this->tournament_new_game($tournament_id,$parent);
					foreach($pairs as $match){
						$this->tournament_game_iter($match,$parent,$tournament_id);
					}
				}
			}
		}
	}

	function split_and_pair($players)
	{
		$keys = array_keys($players);
		if(count($players) == 2)
			return $players;
		if(count($players) == 3){
			return array($players[$keys[0]],array($players[$keys[1]],$players[$keys[2]]));
		}
		$split = array_chunk($players,ceil(count($players)/2));
		return array($this->split_and_pair($split[0]),$this->split_and_pair($split[1]));
	}

	function playershuffle($players)
	{
		$shuffled_players = array();
		while(count($players)) {
			$middleKey = array_keys($players)[ceil(count($players)/2)];
			$topKey = array_keys($players)[0];
			$shuffled_players[] = $players[$topKey];
			$shuffled_players[] = $players[$middleKey];
			unset($players[$topKey]);
			unset($players[$middleKey]);
		}
		return $shuffled_players;
	}

	function tournament_test_players_should_play($p1,$p2) {
		if(!$this->db->tournament_is_started()){
		//	$this->add_out("No tournament started.","msg","OK");
			return 0;
		}
		$tournament_id = $this->db->tournament_get_active_id();
		$tournament_players = $this->db->tournament_get_players($tournamnent_id);
		if(!$this->db->tournament_player_has_signed($p1)){
		//	$this->add_out("Player $p1 has not signed up for the tournament.");
			return 0;
		}
		if(!$this->db->tournament_player_has_signed($p2)){
		//	$this->add_out("Player $p2 has not signed up for the tournament.");
			return 0;
		}
		if($this->db->tournament_game_pending($p1,$p2)){
			//$this->add_out("Players have no game pending.","msg","OK");
			return 1;
		}
		return 0;
	}
	function tournament_fakewin($p1,$p2){
		return $this->tournament_check_and_register_win($p1,$p2,$p1);
	}

	function tournament_register_win($p1,$p2,$winner) {
		return $this->db->tournament_register_win($p1,$p2,$winner);
	}
/*	function tournament_forfeit($who)
	{
		if($this->db->)
	}
*/






	/******************
	 * Helper functions
	 ******************/
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
			$str.= "_".$player . ": ".round($arr["new"],0,PHP_ROUND_HALF_UP)."(".($diff > 0 ? "+" : "").(string)$diff.")  ";
		}
		return $str;
	}
	public function pretty_score($n) {
		$arr = $this->db->get_stats($n);
		$out = "Scoreboard".($n?" ($n)":"")."\n----------------------------------\n";
		$out.= "Player      Elo  Games Wins Losses\n";
		foreach($arr as $player){
			$out.=str_pad("_".$player["name"], 10).
			str_pad((string)$player["elo"],5," ",STR_PAD_LEFT).
			str_pad((string)$player["games"],6," ",STR_PAD_LEFT).
			str_pad((string)$player["wins"],6," ",STR_PAD_LEFT).
			str_pad((string)$player["losses"],6," ",STR_PAD_LEFT).
			"\n";
		}
		return $out;
	}
	function pretty_games($p1,$p2=null)
	{
		$games = $this->db->query_games($p1,$p2);
		return print_r($games,true);
	}

	function tournament_pretty()
	{
		if(!$this->db->tournament_is_started()){
			if($this->db->tournament_is_initialized()){
				$this->add_out("No tournament is going on at the moment, but one is waiting for players to sign.\nCurrently signed players:\n","msg","OK");
				$players = $this->db->tournament_get_players();
				if(!count($players))
					$this->add_out("No players have signed.","msg","OK");
				else foreach($this->db->tournament_get_players() as $player)
					$this->add_out("_".$player["name"],"msg","OK");
				return;
			}
			$this->add_out("No tournaments are active at the moment. You can create one if you wish.","msg","OK");
			return;
		}
		$tmp_tournament = $this->db->tournament_get_full_game_list();
		$tournament = array();
		foreach($tmp_tournament as $key => $val){ // put the game_id as key for each game in the array.
			$tournament[$val["game_id"]] = $val;
			if($val["winner"]){
				if($val["player1"] == $val["winner"])
					$tmp_tournament[$key]["player2"] = '~'.$tmp_tournament[$key]["player2"].'~';
				else if($val["player2"] == $val["winner"])
					$tmp_tournament[$key]["player1"] = '~'.$tmp_tournament[$key]["player1"].'~';
			}
		}

		$tournament = $this->buildTree($tournament); 
		//$this->add_out(print_r($tournament,true),"msg","OK");
		$out = "--------- ".$tmp_tournament[0]["tournament_name"]." ---------\n\n";
		$out.=$this->printTree($tournament);
		$this->add_out($out,"msg","OK");
	}

	function buildTree(array &$elements, $parentId = 0) 
	{
		$branch = array();

		foreach ($elements as $element) {
			if ($element['parent_game'] == $parentId) {
				$children = $this->buildTree($elements, $element['game_id']);
				if ($children) {
					$element['children'] = $children;
				}
				$branch[$element['game_id']] = $element;
				unset($elements[$element['game_id']]);
			}
		}
		return $branch;
	}

	function printTree($elements, $level=0)
	{
		//print_r($elements);
		$str = "";
		foreach($elements as $element){
			$extra = "";
			if(isset($element["winner"]) && $element["winner"])
				$extra="~";
			else if($element["player1"] && $element["player2"])
				$extra="*";
			$str.= str_pad( $extra.($level==0 ? "Final: ":($level==1 ? "Semifinal:":($level== 2 ? "Quarterfinal:":"Game:"))).$extra, ($level * 10) + 10, " ", STR_PAD_LEFT)." ";
			$str.= ($element["player1"] ? "_".$element["player1"] : "<unknown>")." vs ".($element["player2"] ? "_".$element["player2"] : "<unknown>")."\n";
			if(isset($element["children"])){
				$str.=$this->printTree($element["children"],$level+1);
			}

		}
		$str.="\n";
		return $str;
	}
	function tournament_log_pretty(){
		$tournaments = $this->db->tournaments_get();
		$out = "```";
		foreach($tournaments as $t){
			$out.=ucfirst($t["tournament_name"])." Started: ".$t["started"]."  Finished: ".($t["finished"] ? $t["finished"] : "Running. ")." Winner: ".$t["winner"]."\n";
		}
		$out.="```";
//		$this->add_out(print_r($tournaments,true),"msg","OK");
		$this->add_out($out,"msg","OK");
	}
	function query_games_pretty($p1,$p2){
		$games = $this->query_games($p1, $p2);
		return print_r($games,true);
	}
}
?>

