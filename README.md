# pong

Elo-ranking system for simple scorekeeping.

Has a simple webpage for adding new players and games and API support for custom integrations like Slack.

Uses postgresql by default. You need to copy src/inc/db.inc.example to src/inc/db.inc and edit your database credentials.

License: MIT.

Author: Simon Cederqvist


**Install**

Have postgresql, php55, apache24, php-pgsql plugin.

Clone the repository.
Create a 'pong' datababase.
Create a database user that has access to said database, in the example I use 'webuser'.
Import pong.sql to set up your database.
Create a slack bot user, and copy the token into db.inc.

run *php composer.phar update* and *php composer.phar install*

To run the Slack bot, go to src/controllers/ and run bot.php. Run it in a screen so it's persistent.

**Keeping it alive**

It's enough to run it on a free tier AWS micro EC2 instance. It'll stay there for some time, but those instances tend to go to sleep and the bot will die. To overcome that, here's a simple script that will start the bot if it has stopped:

#!/bin/sh
if ps -ef | grep -v grep | grep bot.php ; then
        exit 0
else
    cd /var/www/pong/src/controllers
    screen -dmS pong php bot.php
    exit 0
fi

Put that in the crontab on some regular interval, for example:

0 * * * * sh /home/ec2-user/checkpong.sh

**API and Web usage**

It does have an API and a webpage but these should not be used by anyone unless you want to improve them first.
The Slackbot is probably all you want and the other interfaces just causes people to cheat.

GET or POST.

{
  command = {
   "new_game" 
     {
       "p1"=[name]
       "p2"=[name]
       "winner"=[winner] | "draw"
     }
   "new_player" 
     {
       "name"=[name]
     }
   "statistics" 
     {
       ("lim")=[limit]
     }
  }
  "api-key"=[api-key]
}

Example: 
http://localhost/pong/api.php?command=new_player&name=johndoe&api-key=ABCDEFGHIJKLMNO

**Requirements**
      "monolog/monolog": "1.0.*",
      "twbs/bootstrap": "^3.3",
      "components/jquery": "^3.1",
      "jclg/php-slack-bot": "dev-master"
    
    Special thanks to this project https://github.com/jclg/php-slack-bot for making this so easy. 
