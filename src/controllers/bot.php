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
			"stats","list","statistics","matches","top","undo",
			"admin","aliases","tournament","cup","tour");

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
				case "undo":
					$this->send($data["channel"],null,"Not implemented yet");	
					break;
				case "tournament":
				case "cup":
				case "tour":
				{
					if(!isset($msg[1])){
						$this->send($data["channel"],null,$ctrl->tournament_pretty()));
						break;
					}
					switch (trim(strtolower($msg[1]))) {
						case "cancel":
							$ctrl->tournament_cancel($username);
							$this->send($data["channel"],null,$ctrl->out["msg"]);
							break;
						case "create":
							$ctrl->tournament_create(implode(" ", array_slice(explode(" ", $data["text"]), 2)),$username);
							$this->send($data["channel"],null,$ctrl->out["msg"]);
							break;
						case "register":
						case "sign":
							$ctrl->tournament_register($username);
							$this->send($data["channel"],null,$ctrl->out["msg"]);
							break;
//						"forfeit":
//							$ctrl->tournament_forfeit($username);
//							$this->send($data["channel"],null,$ctrl->out["msg"]);
//							break;
						case "stats":
						case "top":
						case "show":
						case "status":
							$this->send($data["channel"],null,$ctrl->tournament_pretty());
							break;					
						default : 
							$this->send($data["channel"],null,$this->tournament_help());
							break;
					}
				}
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
		" admin                           - admin commands\n".		
		" aliases                         - prints all command aliases\n".
		" draw <player>                   - records a draw against <player>\n".		
		" loss <player>                   - records a loss against <player>\n".
		" match <winner> <loser> ('draw') - record a game\n".
		" register                        - register as a player\n".
		" stats (N)                       - prints vanity report\n".
		" undo                            - undoes the latest recorded loss (winner or loser can do this)\n".
		"\n".
		" tournament <cmd>                - Tournament commands.\n".
		'```'; 
	}
	protected function tournament_help(){
		return '```'.
		"    Tournaments are cup-format knockout games. Players can create and sign up for tournaments.\n".
		"    The creator of the tournament can decide to start it, after which no signups are possible.\n".
		"    Only one tournament can be active at one time, although the old tournaments are still accessible\n".
		"    for gloating purposes.\n".
		"\n".
		"    Players are paired up into matches according to elo-ranking in such a way that equal competitors\n".
		"    face off later in the tournament and worse players are picked off early. If the number of\n".
		"    participants is uneven, the top player skips a match. In case the number of participants is not\n".
		"    an factor of 2 (2,4,8,16,32..) some tomfoolery happens to make it as fair as possible.\n".
		"\n".
		"    Games are registered as normal. If you are scheduled to play Slartibartfast, and Slartibartfast\n".
		"    records a loss against you, it counts for the tournament as well as the normal ranking.\n".
		"    You have to be a registered player to sign up for a tournament.\n".
		"\n".
		"    Tournament commands are prefixed by 'tournament' or 'tour' or 'cup', and are as follows:\n".
		"    cancel             - Cancels the tournament. Only the creator can cancel a tournament,\n".
		"                         Except if the tournament was created more than a week ago.\n".
		"    create <name>      - Creates a new tournament. Only one can be active.\n".
//		"    forfeit            - Winners do not forfeit.\n".
		"    help               - Prints this.\n".
		"    register|sign      - Signs you up for the tournament.\n".
		"    start              - Starts the tournament. The roster will be created.\n".
		"                         No signpus after this. Only the creator can start the tournament.\n".
		"    stats|status       - Prints out the pretty cup tree.\n".
		"```";
	}
}

$bot = new Bot();
$bot->setToken(SLACK_BOT_TOKEN); // Get your token here https://my.slack.com/services/new/bot
$bot->loadCatchAllCommand(new SuperCommand());
$bot->run();

?>