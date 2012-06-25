<?php
/////////////////////
// MySQL variables //
/////////////////////

$mysql_host = 'localhost';
$mysql_user = 'root';
$mysql_pass = 'ju44rff';
$mysql_db   = 'game';

////////////////////////
// Database structure //
////////////////////////

//database game:
//   scu_users:
//      SID varchar(20)
//      Name varchar(100)
//
//   scu_games:
//      GameName varchar(50)
//      Players varchar(269)
//      Turns varchar(300) default(NULL)
//      Rules varchar(50)
//      Marks varchar(19) default(X)
//      Sequence varchar(29) default(1)



//////////////////////////////
// DO NOT CHANGE BELOW THIS //
//////////////////////////////

define("MYSQL_TABLE", 4, true); // Determine if a single row should be an array or a table (x2 array)
$mysql_wait = 0;

// p:
//    true            ->   return rowCount
//    MYSQL_TABLE     ->   return a table array (x2 array) even if there is only one row
//    MYSQL_ASSOC     ->   return array with only alphanumeric indexes
//    MYSQL_NUM       ->   return array with only numeric indexes
//    MYSQL_BOTH      ->   (default) return array with both numeric and alphanumeric indexes


function mysql_($query, $p=false){
   global $mysql_host, $mysql_user, $mysql_pass, $mysql_db, $mysql_wait;

   $s = 0;
   while(!mysql_connect($mysql_host, $mysql_user, $mysql_pass) && ($s++)<$mysql_wait)
      sleep(1);
   if(!mysql_select_db($mysql_db))
      return false;


   $q = mysql_query($query);

   if(is_bool($q))
      return $q;


   if(is_bool($p))
      if($p)
         return mysql_num_rows($q);
      else
         $p = MYSQL_BOTH;

   if($p >= MYSQL_TABLE){
      $strict = true;
      $p = (($p>4)? $p^MYSQL_TABLE : MYSQL_BOTH);
   }else
      $strict = false;

   if(mysql_num_rows($q)>0)
      if(mysql_num_rows($q)==1)
         if(mysql_num_fields($q)==1)
            return
               $strict?
                  array(array( mysql_result($q, 0, 0) ))
                  :            mysql_result($q, 0, 0);
         else
            return
               $strict?
                  array( mysql_fetch_array($q, $p) )
                  :      mysql_fetch_array($q, $p);
      else{
         while($r = mysql_fetch_array($q, $p))
            $a[] = $r;

         return $a;
      }
   else
      return false;
}

?>