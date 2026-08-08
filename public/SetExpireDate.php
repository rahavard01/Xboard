<?php
$servername = "localhost";
$username = "sql_best_gamesho";
$password = "DAxGF2tn3rWEDFyC";
$db="sql_best_gamesho";

$sqlconn = new mysqli($servername, $username, $password,$db);

$datenew=  strtotime(date('Y-m-d H:i:s', strtotime("+31 days"))); 
$result = $sqlconn ->  query("SELECT id,d,expired_at  FROM v2_user where expired_at is null and NOT id=147");
 
      foreach ($result as $empty_date_users) {
     
			if(  $empty_date_users['d']!=0) { 
        
				$newquery="UPDATE v2_user SET expired_at=".$datenew." WHERE id=".$empty_date_users['id'];
				$sqlconn ->query($newquery);
			}  
       
		}  
        
  
$sqlconn -> close();

?>