# pong

Elo-ranking system for simple scorekeeping.

Has a simple webpage for adding new players and games and API support for custom integrations like Slack.

Uses postgresql by default. You need to copy src/inc/db.inc.example to src/inc/db.inc and edit your database credentials.

License: MIT.

Author: Simon Cederqvist


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
