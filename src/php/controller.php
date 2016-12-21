<?php
namespace pong;
//echo phpinfo();
require_once("../inc/db.php");
$db = new Db();
$errors = array();
$out = array();
$K = 32; // Magic variable
$DRIFT = 0; // Drift coefficient (losers will lose less than the winner gains)
$USEFLOOR=0; // Use floor system
$FLOOR=100; // Minimum amount of ELO ranking one can have.

// Insert new game.
if(isset($_POST["newgame"]) &&
	isset($_POST["winner"]) &&
	isset($_POST["loser"]))
{
	$p1 = (string)$_POST["winner"];
	$p2  = (string)$_POST["loser"];
	foreach(array($p1,$p2) as $player){
		if(!$db->player_exists($player)){
			$out[] ="Player_exists:".$db->player_exists($player);
			$errors[] = "Player $player does not exist.";
		}
	}
	if(isset($_POST["draw"]) && $_POST["draw"] == "draw")
		$winner = "draw";
	else 
		$winner = $p1;
	if(count($errors)){
		return array("error"=>$errors);
	} else {
		$out[] = insert_new_game($p1,$p2,$winner);
	}
}

// New player.
else if(isset($_POST["newplayer"]) && sizeof($_POST["player"]))
{
	$player = strtolower((string)$_POST["player"]);
	if($db->player_exists($player)){
		$errors[] = "Player $player already exists!";
	} else {
		$db->insert_new_player($player);
		$out[] = "New player $player entered with 0 points.";
	}
}

function insert_new_game($p1,$p2,$winner)
{
	global $db;
	$result = "0";
	$p1_elo = $db->get_elo($p1);
	$p2_elo = $db->get_elo($p2);
	$newelo = calc_elo($p1_elo,$p2_elo,($winner == "draw" ? 0 : ($winner==$p1 ? 1 : -1)));
    $record = array(
    		"player1" => $p1,
            "player2" => $p2,
            "winner" => $winner,
            "player1_old_elo"=>$p1_elo,
            "player2_old_elo"=>$p2_elo,
            "player1_new_elo"=>$newelo[0],
            "player2_new_elo"=>$newelo[1]);

	$result = $db->do_insert_game($record);
	$db->update_elo($p1,$newelo[0]);
	$db->update_elo($p2,$newelo[1]);
	return $newelo;
}

function calc_elo($p1,$p2,$winner)
{
	global $out;
	global $K;
	global $FLOOR;
	global $USEFLOOR;
	global $DRIFT;
	if($p1 < $FLOOR) $p1 = $FLOOR;
	if($p2 < $FLOOR) $p2 = $FLOOR;
	$R1 = pow(10, $p1/400);
	$R2 = pow(10, $p2/400);
	$E1 = $R1 / ($R1 + $R2);
	$E2 = $R2 / ($R1 + $R2);
	if($winner==0){
		$S1 = 0.5;
		$S2 = 0.5;
	} else {
		$S1 = ($winner==1 ? 1 : 0 + $DRIFT);
		$S2 = ($winner==-1 ? 1 : 0 + $DRIFT);
	}
	$r1 = $p1+($K*($S1-$E1));
	$r2 = $p2+($K*($S2-$E2));
	if($USEFLOOR){
		$r1 = (int)max($FLOOR,$r1);
		$r2 = (int)max($FLOOR,$r2);
	} 
	$out[] = "p1:$p1 p2:$p2 R1:$R1 R2:$R2 E1:$E1 E2:$E2 S1:$S1 S2:$S2 r1:$r1 r2:$r2 winner:($winner)";
	return array($r1,$r2);
}
?>

