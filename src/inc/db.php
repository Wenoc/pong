<?php
namespace pong;
define('DB_SERVER','localhost');
define('DB_USERNAME','webuser');
define('DB_PASSWORD','vrfaehinxh54wonghc7w459gh8cmw45px9gwx89gcmyo5w4hgeb5vsl8gbey4stve45ytdghrsfgcknfgy345ndgfi5ngcfierfxmi8');
define('DB_DATABASE','pong');

class Db
{
    private $db;

    function __construct(){
        $this -> db = pg_connect("dbname=".DB_DATABASE." host=".DB_SERVER." user=".DB_USERNAME." password=".DB_PASSWORD." options='--client_encoding=UTF8'");
    }
    function __destruct(){

    }


    private function sql($query)
    {
        $result = pg_query($this -> db, $query);
        return $result;
    }

    /*
     * @param string query A fully formatted sql query.
     * @return array Result as an array.
     */
    public function sql_result_array($query)
    {
        $result = $this -> sql($query);
        return pg_fetch_all($result);
    }

    /*
     * @param string $query
     * @return value
     */
    public function sql_get_single($query)
    {
        $result = $this -> sql($query);
        if (!pg_num_rows($result)) {
            return 0;
        } else {
            return pg_fetch_result($result, 0, 0);
        }
    }

    public function sql_fetch_first_column($query)
    {
        $result = $this -> sql($query);
        if ($result)
            return pg_fetch_all_columns($result);
        else
            return 0;
    }


        function get_stats(){
            $sql = "SELECT * from users order by elo desc";
            return $this->sql_result_array($sql);
        }
}

?>
