<?php
$servername = "localhost";
$username = "sql_best_gamesho";
$password = "DAxGF2tn3rWEDFyC";
$db="sql_best_gamesho";

$sqlconn = new mysqli($servername, $username, $password,$db);
$newquery="Update v2_user set d=0 , u=0  where plan_id IN (11,17,10,6,1)";
$sqlconn ->query($newquery);
      
   
        
  
$sqlconn -> close();

?>