<?php
namespace pong\controllers;
require __DIR__ . '/../../vendor/autoload.php';
use PhpSlackBot\Bot;
include("../inc/db.inc");

// This special command executes on all events
class SuperCommand extends \PhpSlackBot\Command\BaseCommand {

	public $commands = array("help","challenge","accept","refuse","decline","cancel",
			"register","sign",
			"draw","loss","lose","match","game",
			"stats","list","statistics","matches","top",
			"admin","aliases");

	protected function configure() {
        // We don't have to configure a command name in this case
	}

	protected function execute($data, $context) {
		if (isset($data['type']) && $data['type'] == 'message') {
			$msg = explode(" ", strtolower($data['text']));
			if(!count($msg) || !$this->validate_cmd($msg[0])){
				return; // We do not want to put any effort into non-commands.
			}
			$channel = $this->getChannelNameFromChannelId($data['channel']);
			$username = $this->getUserNameFromUserId($data['user']);
			echo $username.' from '.($channel ? $channel : 'DIRECT MESSAGE').' : '.$data['text'].PHP_EOL;

			//$db = new Db();
			$ctrl = new GenericController();

			switch($msg[0])
			{
				case "help":
				$this->send($data["channel"],null,$this->help());
				break;
				case "aliases":
				$this->send($data["channel"],null,"Full list of aliases: ".implode(", ", $this->commands));
				break;
				case "challenge":
				$this->send($data["channel"],null,"Not implemented yet");				
				break;
				case "accept":
				$this->send($data["channel"],null,"Not implemented yet");				
				break;
				case "decline":
				case "refuse":
				$this->send($data["channel"],null,"Not implemented yet");				
				break;
				case "cancel":
				$this->send($data["channel"],null,"Not implemented yet");				
				break;
				case "register":
				case "sign":
				$ctrl->insert_new_player($username);
				$this->send($data["channel"],null,$ctrl->out["msg"]);
				break;
				case "draw":
				if(!isset($msg[1])){
					$this->send($data["channel"],null,"Usage: draw <player>");
					break;
				}
				$ctrl->insert_new_game($msg[1],$username,"draw");
				$this->send($data["channel"],null,$ctrl->out["msg"]." ".$ctrl->pretty_elo());
				break;				
				case "loss":
				case "lose":
				case "lost":
				if(!isset($msg[1])){
					$this->send($data["channel"],null,"Usage: loss <winner>");
					break;
				}
				$ctrl->insert_new_game($msg[1],$username,$msg[1]);
				$this->send($data["channel"],null,$ctrl->out["msg"]." ".$ctrl->pretty_elo());				
				break;

				case "match":
				case "game":
				if(!isset($msg[1]) || !isset($msg[2]) || (isset($msg[3]) && $msg[3]!="draw")){
					$this->send($data["channel"],null,"Usage: game <winner> <loser> (draw)");
					break;
				}
				$ctrl = new GenericController();
				if($msg[2] != $username && !$ctrl->db->is_admin($username)) {
					$this->send($data["channel"],null,"Only the loser can record a game, $username.");
					break;
				}
				if(isset($msg[3])){
					$ctrl->insert_new_game($msg[1],$msg[2],"draw");
				} else {
					$ctrl->insert_new_game($msg[1],$msg[2],$msg[1]);
				}
				echo print_r($ctrl->out,true);
				break;


				case "stats":
				case "list":
				case "statistics":
				case "matches":
				case "top":
				$n = 0;
				if(isset($msg[1]))
					$n = (int)$msg[1];
				echo print_r($ctrl->pretty_score(0),true);
				$out= "```".$ctrl->pretty_score($n)."```";
				$this->send($data["channel"],null,$out);
				break;

				case "admin":
				if($ctrl->db->is_admin(trim(strtolower($username)))){
					if(!isset($msg[1]) || !isset($msg[2])){
						$this->send($data["channel"],null,"Usage: admin [add|del] <user>");
						break;
					}
					if(!$ctrl->db->player_exists($msg[2])){
						$this->send($data["channel"],null,"User ".$msg[2]." does not appear to exist.");	
						break;
					}
					if($msg[1]=="add"){
						$ctrl->db->set_admin($msg[2],1);
						$this->send($data["channel"],null,"User ".$msg[2]." is now an admin.");
					} else if($msg[1]=="del"){
						$ctrl->db->set_admin($msg[2],0);
						$this->send($data["channel"],null,"User ".$msg[2]." is no longer an admin.");
					} else {
						$this->send($data["channel"],null,"Something went wrong. msg1:".$msg[1]." msg2:".$msg[2]);
					}
				} else {
					$this->send($data["channel"],null,"You are not an admin, $username.");
				}
				break;
			}
		}
	}

	protected function validate_cmd($cmd){ // All the valid commands. 
		return in_array($cmd, $this->commands);
	}


	protected function help() {
		return  '```'.
		"Commands:\n".
		"---------\n".
		" register                        - register as a player\n".
		" match <winner> <loser> ('draw') - record a game\n".
		" loss <player>                   - records a loss against <player>\n".
		" draw <player>                   - records a draw against <player>\n".		
		" stats (N)                       - prints vanity report\n".
		" aliases                         - prints all command aliases\n".
		" admin                           - admin commands\n".		
		'```'; 
	}
}

$bot = new Bot();
$bot->setToken(SLACK_BOT_TOKEN); // Get your token here https://my.slack.com/services/new/bot
$bot->loadCatchAllCommand(new SuperCommand());
$bot->run();

?>