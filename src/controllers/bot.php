<?php
require 'vendor/autoload.php';
use PhpSlackBot\Bot;
include("../inc/db.inc");

// Custom command
class PongBot extends \PhpSlackBot\Command\BaseCommand {

    protected function configure() {
        $this->setName('pong');
    }

    protected function execute($message, $context) {
        $this->send($this->getCurrentChannel(), null, 'Hello !');
    }

}

$bot = new Bot();
$bot->setToken(SLACK_BOT_TOKEN); // Get your token here https://my.slack.com/services/new/bot
$bot->loadCommand(new MyCommand());
$bot->loadInternalCommands(); // This loads example commands
$bot->run();

?>