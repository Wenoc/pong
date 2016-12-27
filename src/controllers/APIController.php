<?php
namespace pong\controllers;
require __DIR__ . '/../../vendor/autoload.php';

class APIController extends GenericController
{
	public function post($post)
	{
		if(!isset($post["api-key"])){
			$this->add_out("No API key given.","errors","ERROR");
			return;
		} else if(!in_array($post["api-key"],unserialize(API_KEYS))){
			$this->add_out("Invalid API key","errors","ERROR");
			return;
		}

		switch ($post["command"]) {

			case 'new_game':
				if(isset($post["p1"]) && isset($post["p2"]) && isset($post["winner"])){
						$this->insert_new_game($post["p1"],$post["p2"],$post["winner"]);
				}
				break;
			case 'new_player':
				if(isset($post["name"])){
					$this->insert_new_player($post["name"]);
				} else {
					$this->add_out("No name given.","strout","ERROR");
				}
				break;
			case 'statistics': 
			default:
				$this->out["statistics"] = $this->db->get_stats( ((int)$post["lim"]>0 ? (int)$post["lim"] : 0) );
			break;
		}
		return ;
	}
}
?>

