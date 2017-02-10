<?php
namespace pong\controllers;
require __DIR__ . '/../../vendor/autoload.php';
include("../inc/db.inc");

class DB
{
    private $db;

    function __construct(){
        $this->connect();
    }

    function __destruct(){

    }


    /***********
     * Private
     ***********/
    private function connect(){
    //        $this -> db = pg_connect("dbname=".DB_DATABASE." host=".DB_SERVER." user=".DB_USERNAME." password=".
        $this -> db = pg_connect("dbname=".DB_DATABASE." user=".DB_USERNAME." password=".
            DB_PASSWORD." options='--client_encoding=UTF8'");
    }

    private function sql($query)
    {
            // echo "QDEBUG: $query<br/>";
        $result = pg_query($this->db,$query);
            // echo "RDEBUG: ".print_r($result,true)."<br/>";
        return $result;
    }

    public function sql_result_array($query)
    {
        $result = $this -> sql($query);
        return pg_fetch_all($result);
    }

    private function sql_get_single($query)
    {
        $result = $this -> sql($query);
        if (!pg_num_rows($result)) {
            return 0;
        } else {
            return pg_fetch_result($result, 0, 0);
        }
    }

    private function sql_fetch_first_column($query)
    {
        $result = $this -> sql($query);
        if ($result)
            return pg_fetch_all_columns($result);
        else
            return 0;
    }

    /********************
     * Public interface 
     *******************/
    public function get_elo($player){
        $query = "SELECT elo from users where name='".$this->fix($player)."'";
        return $this->sql_get_single($query);
    }
    public function get_stats($lim = 0){
        if($lim)
            $sql = "SELECT name,elo,games,wins,losses from users order by elo desc limit ".(int)$lim;
        else
            $sql = "SELECT name,elo,games,wins,losses from users order by elo desc";
        return $this->sql_result_array($sql);
    }

    public function do_insert_game($record) 
    {        
        $query = pg_insert($this->db, "games", $record, PGSQL_DML_STRING);
        $result = $this->sql($query);
        return $result;
    }
    public function is_admin($player) 
    {
        $query = "SELECT admin FROM users WHERE name='".$this->fix($player)."'";
        $admin = $this->sql_get_single($query);
        return $admin=='t';
    }
    public function set_admin($player,$b)
    {
        $query = "UPDATE users SET admin='".($b?"t":"f")."' WHERE name='".$this->fix($player)."'";
        $this->sql($query);
        return;
    }
    public function player_exists($player)
    {
        $query = "SELECT count(*) from users where name='".$this->fix($player)."'";
        return $this->sql_get_single($query);
    }
    public function insert_new_player($player)
    {
        $query = "INSERT INTO users (name,elo,games) VALUES ('".$this->fix($player)."',0,0)";
        $res = $this->sql_get_single($query);
    }
    public function update_elo($player,$elo){
        if($player && isset($elo)){
            $query = "UPDATE users SET elo=$elo, games = games + 1 WHERE name='".pg_escape_string($player)."'";
            return $this->sql($query);
        }
    }
    public function add_win($player){
        $query = "UPDATE users SET wins = wins + 1 WHERE name='".$this->fix($player)."'";
        $this->sql($query);
    }
    public function add_loss($player){
        $query = "UPDATE users SET losses = losses + 1 WHERE name='".$this->fix($player)."'";
        $this->sql($query);
    }
    public function update_games(){
        $names = $this->sql_result_array("SELECT name FROM users");
        foreach ($names as $n)
        {
            $name=$n["name"];
            $wins = $this->sql_get_single("SELECT count(winner) FROM games WHERE winner='$name'");
            $total = $this->sql_get_single("SELECT count(*) FROM games WHERE player1='$name' OR player2='$name'");
            $losses = $total - $wins;
            $this->sql("UPDATE users SET games=$total,wins=$wins,losses=$losses WHERE name='$name'");
            echo "$name games:".print_r($total,true)." wins:$wins losses:$losses\n";
        }
    }
    function query_games($p1,$p2)
    {
        $p1 = trim(pg_escape_string($p1));
        $p2 = trim(pg_escape_string($p2));
        return $this->sql_result_array("SELECT * from games WHERE (player1=='$p1' AND player2=='$p2') OR (player1=='$p2' AND player2=='$p1')");
    }

    /**************
     * Tournaments
     **************/
    // tournaments
    public function tournaments_get()
    {   
        $query = "SELECT tournament_name,date(started) started,date(finished) finished,winner FROM tournaments ".
            " ORDER BY initialized ASC";
        return $this->sql_result_array($query);
    }
    public function tournament_owner_inactive()
    {
        return $this->sql_get_single("SELECT count(*) FROM tournaments WHERE active=true AND finished IS NULL AND (NOW() - interval '7 day') > initialized");
    }
    public function tournament_is_initialized(){
        return $this->sql_get_single("SELECT count(*) FROM tournaments WHERE active=true");
    }
    public function tournament_is_started(){
        return $this->sql_get_single("SELECT count(*) FROM tournaments WHERE active=true AND started IS NOT NULL AND finished IS NULL");
    }
    public function tournament_get_active_id()
    {
        return $this->sql_get_single("SELECT tournament_id FROM tournaments WHERE active = true");
    }
    public function tournament_create($desc, $creator)
    {
        return $this->sql_get_single("INSERT INTO tournaments (tournament_name,creator) VALUES ('".$this->fix($desc)."','".$this->fix($creator)."') RETURNING tournament_id");
    }
    public function tournament_start($tournament_id)
    {
        return $this->sql_get_single("UPDATE tournaments SET started=now() WHERE tournament_id=".(int)$tournament_id." RETURNING tournament_id");
    }
    public function tournament_finish($winner)
    {
        $tournament_id = $this->tournament_get_active_id();
        return $this->sql_get_single("UPDATE tournaments SET finished=now(),winner='".$this->fix($winner)."',active=false WHERE tournament_id=".(int)$tournament_id);
    }
    public function tournament_get_full_game_list()
    {
        $query = "SELECT tournament_name,player1,player2,tournament_games.winner,game_id,parent_game ".
        " FROM tournaments LEFT JOIN tournament_games USING (tournament_id) WHERE active=true ORDER BY parent_game ASC";
        return $this->sql_result_array($query);
    }
    public function tournament_cancel($winner){
        $this->sql("UPDATE tournaments SET winner='".$this->fix($winner)."',finished=NOW(),active=false WHERE active=true");
    }
    public function tournament_owner(){
        return $this->sql_get_single("SELECT creator from tournaments WHERE active=true");
    }

    // tournament_players
    public function tournament_get_players($tournament_id = 0)
    {
        if($tournament_id == 0)
            $tournament_id = $this->tournament_get_active_id();
        return $this->sql_result_array("SELECT name FROM tournament_players LEFT JOIN users USING (name) WHERE tournament_id=".(int)$tournament_id." ORDER BY elo DESC");
    }
    public function tournament_player_has_signed($name){
        $name = $this->fix($name);
        $tournament_id = $this->tournament_get_active_id();
        if(!$tournament_id){
            return 0;
        }
        if($this->sql_get_single("SELECT count(name) FROM tournament_players WHERE tournament_id=$tournament_id AND name='".$this->fix($name)."'"))
            return 1;
        return 0;
    }
    public function tournament_register_player($name){
        $name = $this->fix($name);
        $tournament_id = $this->tournament_get_active_id();
        if($this->tournament_player_has_signed($name) || !$tournament_id)
            return 0;
        $this->sql_get_single("INSERT INTO tournament_players (tournament_id,name) VALUES (".$tournament_id.",'".$name."')");
        return 1;
    }
    //tournament_games
    public function tournament_new_game($tournament_id,$parent_game=0,$player1=null,$player2=null)
    {
        $tournament_id=(int)$tournament_id;
        $parent_game=($parent_game?(int)$parent_game:0);
        $player1 = ($player1?$this->fix($player1):null);
        $player2 = ($player1?$this->fix($player2):null);
        return $this->sql_get_single("INSERT INTO tournament_games (tournament_id,parent_game,player1,player2) VALUES ($tournament_id,$parent_game,'$player1','$player2') RETURNING game_id");
    }
    public function tournament_update_game($game_id,$game){
        $query = pg_update($this->db, 'tournament_games', $game, array("game_id" => (int)$game_id));
        $this->sql_get_single($query);
    }
    public function tournament_game_pending($p1,$p2){
        $p1 = $this->fix($p1);
        $p2 = $this->fix($p2);
        $tournament_id = $this->tournament_get_active_id();
        return $this->sql_get_single("SELECT count(*) FROM tournament_games WHERE winner IS NULL AND tournament_id=$tournament_id AND".
            " ((player1='$p1' AND player2='$p2') OR (player2='$p1' AND player1='$p2'))");
    }
    public function tournament_register_win($p1,$p2,$winner){
        $p1 = $this->fix($p1);
        $p2 = $this->fix($p2);
        $winner = $this->fix($winner);
        $tournament_id = $this->tournament_get_active_id();
        $game_id = $this->sql_get_single("SELECT game_id FROM tournament_games WHERE winner IS NULL AND tournament_id=$tournament_id AND".
            " ((player1='$p1' AND player2='$p2') OR (player2='$p1' AND player1='$p2'))");

        $parent = $this->sql_get_single("UPDATE tournament_games SET winner='$winner' WHERE game_id=$game_id RETURNING parent_game");
        $parent_game = $this->sql_result_array("SELECT player1,player2,winner FROM tournament_games WHERE game_id=$parent");
        $tmp_p1 = $this->sql_get_single("SELECT player1 FROM tournament_games WHERE game_id=$parent");
        $tmp_p2 = $this->sql_get_single("SELECT player2 FROM tournament_games WHERE game_id=$parent");
        if($tmp_p1!=null && $tmp_p2!=null){
            return 0;
        }
        if($tmp_p1==null)
            $this->sql("UPDATE tournament_games SET player1='$winner' WHERE game_id=$parent");
        else
            $this->sql("UPDATE tournament_games SET player2='$winner' WHERE game_id=$parent");            
        if($parent == 0) // Last game.
            return -1;
        return 1;
    }
    public function fix($str){
        return trim(strtolower(pg_escape_string($str)));
    }
/* Don't even think about it */
    public function CLEAR_TOURNAMENTS(){
        $query = "DELETE FROM tournament_games;DELETE FROM tournament_players;DELETE FROM tournaments;";
        $this->sql($query);
    }
}

?>
