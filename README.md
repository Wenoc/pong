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

To run the Slack bot, go to src/controllers/ and run bot.php.

**API usage**

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
