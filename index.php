<?php
session_start();
include "mysql.php";

// Scheduled Tasks
if(PHP_SAPI === 'cli'){
   if(in_array('-clean', $argv)){
      $mysql_wait = 120;

      if(mysql_("DELETE FROM scu_users WHERE SID not like 'record %' AND SID not like 'AI:%'"))
         echo "Abrupt users cleaned: ".mysql_affected_rows()."\n";
      else
         echo mysql_error()."\n";

      if(mysql_("DELETE FROM scu_games WHERE Players not like 'record %'"))
         echo "Abrupt games cleaned: ".mysql_affected_rows()."\n";
      else
         echo mysql_error()."\n";

      sleep(5);
   }

   exit;
}//End of Scheduled Tasks

// Session Management - Start
session:{
   if( isset($_REQUEST['clear'])){
      mysql_("DELETE FROM scu_users WHERE SID='".session_id()."'");

      if(isset($_REQUEST['game'])){
         mysql_("UPDATE scu_games SET Players=REPLACE(REPLACE(Players, ',".session_id()."', ''), '".session_id().",', ' ') WHERE Players like '%".session_id()."%'");
         mysql_("DELETE FROM scu_games WHERE Players='".session_id()."'");
         mysql_("DELETE FROM scu_games WHERE REPLACE(REPLACE(REPLACE(REPLACE(Players, 'AI:Hard', ''), 'AI:Normal', ''), 'AI:Easy', ''), ',', '')=''");
      }

      exit("Success");
   }
   if(!isset($_COOKIE['username'])){
      echo '<script type="text/javascript">'."\n";
      echo '   var nickname = prompt("Would you like to specify a nickname?");'."\n";
      echo '   if(nickname==null) nickname = "Guest";'."\n";
      echo "\n";
      echo '   //Set Cookie'."\n";
      echo '   var exdate = new Date();'."\n";
      echo '   exdate.setDate(exdate.getDate() + 7);'."\n";
      echo '   document.cookie= "username=" + nickname + "; expires="+exdate.toUTCString();'."\n";
      echo '   window.location.reload();'."\n";
      echo '</script>'."\n";
      exit;
   }
   mysql_("INSERT INTO scu_users (SID, Name) VALUES ('".session_id()."', '".$_COOKIE['username']."')");
}// Session Management - End

// JS->PHP->MySQL
js_php_mysql:{
if(isset($_REQUEST['logout'])){
   setcookie("username", "", time()-1);
   echo '<script type="text/javascript">window.location.href="'.($_REQUEST['logout']==""? "?":$_REQUEST['logout']).'";</script>'."\n";
   exit;
}
if(isset($_REQUEST['create'], $_REQUEST['game'])){
   $game  = preg_replace("/[^a-zA-Zà-ÿÀ-ß0-9_ ]/", "", $_REQUEST['game']);
   $rules = preg_replace("/[^a-zA-Zà-ÿÀ-ß0-9_ <>\=!]/", "", $_REQUEST['rules']);

   if(mysql_("SELECT GameName FROM scu_games WHERE Players LIKE '%".session_id()."%'", true)==0)
      mysql_("INSERT INTO scu_games (GameName, Rules, Players) VALUES('".$game."', '".$rules."', '".session_id()."')");

   setcookie("gamename", $game,  time()+ 30*24*60*60);
   setcookie("rules",    $rules, time()+ 30*24*60*60);

   echo '<script type="text/javascript">window.location.href="?"</script>';
   exit;
}
if(isset($_REQUEST['availableGames'])){
   $arr = array();
   $games = mysql_("SELECT GameName,Players FROM scu_games WHERE Private=0 AND (Turns is NULL OR Turns not like '0:0%')",MYSQL_ASSOC|MYSQL_TABLE);
   if(!$games) exit;

   foreach($games as $k=>$g){
      $players = explode(",", $g['Players']);
      $pl      = array();

      foreach($players as $p)
         $pl[] = $p . "(" . mysql_("SELECT Name FROM scu_users WHERE SID='".$p."'") . ")";

      $arr[]  = $g['GameName'] . "/" . join(",", $pl);
   }

   echo join("\n", $arr);
   exit;
}
if(isset($_REQUEST['playedGames'])){
   $arr = array();
   $games = mysql_("SELECT GameName,Players FROM scu_games WHERE Private=0 AND (Turns is not NULL AND Turns like '0:0%')",MYSQL_ASSOC|MYSQL_TABLE);
   if(!$games) exit;

   foreach($games as $k=>$g){
      $players = explode(",", $g['Players']);
      $pl      = array();

      foreach($players as $p)
         $pl[] = $p . "(" . mysql_("SELECT Name FROM scu_users WHERE SID='".$p."'") . ")";

      $arr[]  = $g['GameName'] . "/" . join(",", $pl);
   }

   echo join("\n", $arr);
   exit;
}
if(isset($_REQUEST['join'])){
   $join  = preg_replace("/[^a-zA-Z0-9,-]/" , "", $_REQUEST['join']);
   $add   = (!isset($_REQUEST['add']))? session_id() : preg_replace("/[^a-zA-Z0-9,-:]/", "", $_REQUEST['add']);

   $rules = mysql_("SELECT Rules FROM scu_games WHERE Players LIKE '".$join."%'");
   $rule  = explode(" ", $rules);

   $allowed = true;
   foreach($rule as $specified){
      if(strlen($specified)==0)
         continue;

      $sign = substr($specified, 0, 1);

      // If Slots are Players-Specific
      if(!in_array($sign, array("!", "<", "=")))
         $allowed = false;

      // If You have a Player_Specific Slot
      if($specified == $_COOKIE['username']){
         $allowed = true;
         break;
      }

      // If You are explicitly banned
      if($specified == "!".$_COOKIE['username']){
         $allowed = false;
         break;
      }

      // If there is a player limit on the game
      if( in_array(substr($specified, 0, 1), array("<", "=")) ){
         $cur  = count(explode(",", mysql_("SELECT Players FROM scu_games WHERE Players LIKE '".$join."%'")));
         $goal = (int)substr($specified, 1);

         if( ($sign=="<" && $cur+1 >= $goal) || ($sign=="=" && $cur >= $goal) ){
            $allowed = false;
            break;
         }
      }
   }

   if(stripos(" ".$add, "AI:")){
      if(session_id() != $join)
         exit("Only the Host may add AI Players");
      else
         if(stripos(" ".$add, "AI:Easy"))
            $add = "AI:Easy";
         else
         if(stripos(" ".$add, "AI:Normal"))
            $add = "AI:Normal";
         else
            $add = "AI:Hard";
   }

   $marks = array("X", "O", "Z", "V", "A", "H", "N", "P", "I", "J", "Y", "T", "D", "W", "S", "E", "K", "M", "R", "F", "Q", "B", "G", "L", "U", "C");

   if(!$allowed)
      exit('<script type="text/javascript">alert("Sorry, you are not allowed to this game."); window.location.href="?";</script>');

   mysql_("UPDATE scu_games SET".
      " Players =CONCAT(Players, ',".$add."'),".
      " Marks   =CONCAT(Marks, ',".$marks[count(explode(",", mysql_("SELECT Players FROM scu_games WHERE Players like '%".$join."%'")))]."'), ".
      " Sequence=CONCAT(Sequence, ',".(max(explode(",", mysql_("SELECT Sequence FROM scu_games WHERE Players like '%".$join."%'") ))+1)."') ".
      "WHERE Players LIKE '".$join."%' AND (Turns is NULL OR Turns not like '0:0%')"
   ) or die(mysql_error());

   echo '<script type="text/javascript">window.location.href="?";</script>';
   exit;
}
if(isset($_REQUEST['status'])){
   //Check if the game is still opened
   if(mysql_("SELECT GameName FROM scu_games WHERE Players like '%".session_id()."%'", true)==0)
      exit("dead");

   //Check if such game is initiated / game has started
   if(mysql_("SELECT GameName FROM scu_games WHERE Turns LIKE '0:0%' AND Players LIKE '%".session_id()."%'", true))
      exit("game started");

   //Check if there is a new host
   if($_REQUEST['status']=="notHost" && mysql_("Select GameName FROM scu_games WHERE Players like '".session_id()."%'", true))
      exit("new host");

   exit; // exit("normal");
}
if(isset($_REQUEST['players'])){
   $players = mysql_("SELECT Players,Marks,Sequence FROM scu_games WHERE Players like '%".session_id()."%'", MYSQL_NUM) or die(mysql_error());

   $player  = explode(",", $players[0]);
   $marks   = explode(",", $players[1]);
   $turns   = explode(",", $players[2]);
   $players = array();

   foreach($player as $i=>$sid)
      $players[] = $sid
                       ."(".mysql_("SELECT Name FROM scu_users WHERE SID='".$sid."'").")"
                       ."[".$marks[$i]."]"
                       ."{".$turns[$i]."}";

   echo join(",", $players);
   exit;
}
if(isset($_REQUEST['update'])){
   if(isset($_REQUEST['game'])){
      $game = preg_replace("/[^a-zA-Zà-ÿÀ-ß0-9_ ]/", "", $_REQUEST['game']);
      mysql_("UPDATE scu_games SET GameName='".$game."' WHERE Players LIKE '".session_id()."%'");
      setcookie("gamename", $game,  time()+ 30*24*60*60);
   }
   if(isset($_REQUEST['rules'])){
      $rules = preg_replace("/[^a-zA-Zà-ÿÀ-ß0-9_ <>\=!]/", "", $_REQUEST['rules']);
      mysql_("UPDATE scu_games SET Rules='".$rules."' WHERE Players LIKE '".session_id()."%'");
      setcookie("rules", $rules,  time()+ 30*24*60*60);
   }
   if(isset($_REQUEST['private'])){
      $private = (int)( (bool)$_REQUEST['private'] );
      mysql_("UPDATE scu_games SET Private=".$private." WHERE Players LIKE '".session_id()."%'");
   }
   if(isset($_REQUEST['sequence'])){
      $seq = explode(",", mysql_("SELECT Sequence FROM scu_games WHERE Players LIKE '".session_id()."%'"));
      $seq[((int)$_REQUEST['set'])-1] = (int)$_REQUEST['to'];
      mysql_("UPDATE scu_games SET Sequence='".join(",", $seq)."' WHERE Players LIKE '".session_id()."%'");
   }
   exit;
}
if(isset($_REQUEST['kick'])){
   if(!is_numeric($_REQUEST['kick']))
      mysql_("UPDATE scu_games SET Players=REPLACE(Players, ',".mysql_real_escape_string($_REQUEST['kick'])."', ''), Marks=LEFT(Marks, LENGTH(Marks)-2), Sequence=LEFT(Sequence, LENGTH(Sequence)-2) WHERE Players LIKE '".session_id()."%'");
   else{
      $players = explode(",", mysql_("SELECT Players FROM scu_games WHERE Players LIKE '".session_id()."%'"));
      unset($players[(int)$_REQUEST['kick']]);
      $players = join(",", $players);

      mysql_("UPDATE scu_games SET Players='".$players."', Marks=LEFT(Marks, LENGTH(Marks)-2) WHERE Players LIKE '".session_id()."%'");
   }
   exit;
}
if(isset($_REQUEST['promote'])){
   $_REQUEST['promote'] = mysql_real_escape_string($_REQUEST['promote']);
   mysql_("UPDATE scu_games SET Players=CONCAT('".$_REQUEST['promote'].",', REPLACE(Players, ',".$_REQUEST['promote']."', '')) WHERE Players like '".session_id()."%'");
   exit;
}
if(isset($_REQUEST['start'])){
   $players = mysql_("SELECT Players FROM scu_games WHERE Players LIKE '".session_id().",%' AND Turns IS NULL");

   if(isset($_REQUEST['t']) && is_array($_REQUEST['t'])){
      $plyrs   = explode(',', $players);
      $turns   = $_REQUEST['t'];
      $players = array();

      asort($turns);
      foreach($turns as $t=>$v)
         $players[] = $plyrs[$t];
      $players = join(",", $players);
   }

   mysql_("UPDATE scu_games SET Turns='0:0', Players='".$players."' WHERE Players LIKE '".session_id().",%' AND Turns IS NULL");

   //If it's AI's turn next, perform the move
   $plyrs = explode(",", mysql_("SELECT Players FROM scu_games WHERE Players LIKE '".session_id()."%'"));
   $game['Turns'] = '0:0';
   $game['Marks'] = mysql_("SELECT Marks FROM scu_games WHERE Players LIKE '".session_id()."%'");
   $onTurn  = (count(explode(",", $game['Turns'])) % count(explode(",", $game['Marks'])));

   while( ($next = next($plyrs)) && stripos(" ".$next, "AI:")){
      $GLOBALS['ai'] = $onTurn;
      $_REQUEST['m'] = $game['Turns'];
      $_REQUEST['d'] = (stripos(" ".$next, "AI:Easy")? 0 : (stripos(" ".$next, "AI:Hard")? 2 : 1));

      include "ai.php";
      mysql_("UPDATE scu_games SET Turns=CONCAT(Turns, ',', '".$chosen."') WHERE Players like '%".session_id()."%' AND Turns not like '%,".$chosen."%'");

      $onTurn = ($onTurn+1)%count($plyrs);
      $game['Turns'] .= ",".$chosen;
   }

   echo '<script type="text/javascript">window.location.href="?";</script>';
   exit;
}
if(isset($_REQUEST['mark'])){
   if($_REQUEST['mark']=='0:0')
      exit("Initial Repeatance");

   $game    = mysql_("SELECT Players, Turns, Marks FROM scu_games WHERE Players like '%".session_id()."%'");
   $onTurn  = (count(explode(",", $game['Turns'])) % count(explode(",", $game['Marks'])));
   $players = explode(",", $game['Players']);

   if($players[$onTurn] != session_id()){
      echo "Cheat!";
   }
   else{
      $_REQUEST['mark'] = mysql_real_escape_string($_REQUEST['mark']);
      mysql_("UPDATE scu_games SET Turns=CONCAT(Turns, ',', '".$_REQUEST['mark']."') WHERE Players like '%".session_id()."%' AND Turns not like '%,".$_REQUEST['mark']."%'");
      echo "Success";
   }

   //If it's AI's turn next
   if(stripos(" ".$game['Players'], "AI:")){
      echo ';'.$onTurn;
      $n = ($onTurn+1) % count(explode(",", $game['Marks']));
      echo ';'.$n;

      if(stripos(" ".$players[$n], "AI:"))
         echo ';AI next';

      echo "\n".join(" , ", $players);
      echo "\n".$game['Turns'];
   }
   exit;
}
if(isset($_REQUEST['ai'])){
   $game    = mysql_("SELECT Players, Turns, Marks FROM scu_games WHERE Players like '%".session_id()."%'");

   //If there's an AI actually playing
   if(stripos(" ".$game['Players'], "AI:")){
      $onTurn  = (count(explode(",", $game['Turns'])) % count(explode(",", $game['Marks'])));
      $players_ = explode(",", $game['Players']);

      //If the AI is on turn
      while(stripos(" ".$players_[$onTurn], "AI:")){
         $GLOBALS['ai'] = $onTurn;
         $_REQUEST['m'] = $game['Turns'];
         $_REQUEST['d'] = (stripos(" ".$players_[$onTurn], "AI:Easy")? 0 : (stripos(" ".$players_[$onTurn], "AI:Hard")? 2 : 1));

         //echo $_REQUEST['m']."\n";

         include "ai.php";

         echo $chosen."\n\n";

         mysql_("UPDATE scu_games SET Turns=CONCAT(Turns, ',', '".$chosen."') WHERE Players like '%".session_id()."%' AND Turns not like '%,".$chosen."%'");

         $onTurn = ($onTurn+1)%count($players_);
         $game['Turns'] .= ",".$chosen;
      }
   }
   exit;
}
if(isset($_REQUEST['turn'])){
   if(isset($_REQUEST['host']))
      $sql = "SELECT Turns FROM scu_games WHERE Players like '%". mysql_real_escape_string($_REQUEST['host'])."%'";
   else
      $sql = "SELECT Turns FROM scu_games WHERE Players like '%".session_id()."%' AND LENGTH(Players)-LENGTH(REPLACE(Players,',',''))=LENGTH(Marks)-LENGTH(REPLACE(Marks,',',''))";

   $moves = mysql_($sql);

   if(!$moves)
      echo "Abandoned";

   $e = explode(",", $moves);

   if(isset($e[$_REQUEST['turn']-1]))
      echo  $e[$_REQUEST['turn']-1];
   exit;
}
if(isset($_REQUEST['myturn'])){
   $a = max(0, ((int)$_REQUEST['myturn'])-1 );
   $players = explode(",", mysql_("SELECT Players FROM scu_games WHERE Players like '%".session_id()."%'"));
//var_dump($a,$players);
   //Do a manual search because array_search has no offset option
   for($i=$a; $i<count($players); $i++)
      if($players[$i] == session_id())
         echo $i+1, die();
   //If no result yet, check before the offset
   for($j=0; $j<$a; $j++)
      if($players[$j] == session_id())
         echo $j+1, die();

   exit;
}
if(isset($_REQUEST['replay'])){
   mysql_("UPDATE scu_games SET Private=1 WHERE Players like '%".session_id()."%'");
}
if(isset($_REQUEST['record'])){
   $records = mysql_("SELECT SID FROM scu_users WHERE SID like 'record%'", true);
   $players = mysql_("SELECT Players FROM scu_games WHERE Players like '%".session_id()."%' AND Players like '%,%'") or die("Failure!");

   $players = explode(",", $players);
   foreach($players as $key => $sid){
      $players[$key] = mysql_("SELECT Name FROM scu_users WHERE SID='".$sid."'");

      if(isset($_REQUEST['winner']) && $_REQUEST['winner'] == $key)
        $players[$key] .= "*";
   }
   $players = join(" vs ", $players);

   mysql_("INSERT INTO scu_users VALUES ( 'record ".$records."', '".$players."')") or die();
   mysql_("UPDATE scu_games SET Players = 'record ".$records."', Private=0 WHERE Players like '%".session_id()."%'") or die();

   //Expected that $_REQUEST['rename'] would come!
   if(!isset($_REQUEST['rename'], $_REQUEST['to']))
      exit("Success");
   else
      $_REQUEST['rename'] = mysql_("SELECT GameName FROM scu_games WHERE Players = 'record ".$records."'");
}
if(isset($_REQUEST['rename'], $_REQUEST['to'])){
   //Host only permission
   if(!isset($_REQUEST['record']) && $_SERVER['REMOTE_ADDR'] != '127.0.0.1')
      exit("Permission Denied!");

   //Clean the variables from injection attempts
   $_REQUEST['to'] = preg_replace('/[^a-zA-Zà-ÿÀ-ß0-9_\ \-\.,\?!:\=]/', '', $_REQUEST['to']);
   $_REQUEST['rename'] = preg_replace('/[^a-zA-Zà-ÿÀ-ß0-9_\ \-\.,\?!:\=]/', '', $_REQUEST['rename']);

   //If "renameTo" is an empty string it should keep the previous name
   if(trim($_REQUEST['to'])=="" || strtolower(trim($_REQUEST['to']))=='null')
      exit("Success");

   //Stop if such name already exists
   if(mysql_("SELECT GameName FROM scu_games WHERE GameName='".$_REQUEST['to']."'", true)){
      echo "Wanted name already exists!";

      if(!isset($_REQUEST['record']))
         echo "<br /><a href='?'>Home</a>";

      exit;
   }

   mysql_("UPDATE scu_games SET GameName='".$_REQUEST['to']."' WHERE GameName='".$_REQUEST['rename']."'");

   if(!isset($_REQUEST['record']))
      echo '<script type="text/javascript">window.location.href="?"</script>';

   exit("Success");
}
}

//Javascript
js:
if(isset($_REQUEST['js'])){
   if($_REQUEST['js']=='call'){
      echo '   xmlhttp = new Array();'."\n";
      echo "\n";
      echo 'function call(url, callback, sync){'."\n";
      echo '   async = (typeof callback == "boolean")? !callback : (sync!=null)? !sync : true;'."\n";
      echo "\n";
      echo '   if(window.XMLHttpRequest)'."\n";
      echo '      var xh = new XMLHttpRequest();'."\n";
      echo '   else'."\n";
      echo '   if(window.ActiveXObject)'."\n";
      echo '      var xh = new ActiveXObject("Microsoft.XMLHTTP");'."\n";
      echo '   else'."\n";
      echo '      return false;'."\n";
      echo "\n";
      echo "\n";
      echo '   if(typeof callback != "boolean" && callback!=null)'."\n";
      echo '      xh.onreadystatechange = function(){'."\n";
      echo '         if (this.readyState==4 && this.status==200)'."\n";
      echo '            callback(this.responseText);'."\n";
      echo '         xmlhttp.splice(xmlhttp.indexOf(this), 1);'."\n";
      echo '      }'."\n";
      echo "\n";
      echo '   xh.open("GET", url, async);'."\n";
      echo '   xh.send(null);'."\n";
      echo "\n";
      echo '   xmlhttp.push(xh);'."\n";
      echo '   return async? xh : xh.responseText;'."\n";
      echo '}'."\n";
   }
   if($_REQUEST['js']=='coordinates'){
      echo 'function x(coordinates){'."\n";
      echo '   return coordinates.split(":")[0];'."\n";
      echo '}'."\n";
      echo "\n";
      echo 'function y(coordinates){'."\n";
      echo '   return coordinates.split(":")[1];'."\n";
      echo '}'."\n";
      echo "\n";
      echo "\n";
      echo 'function getPosition(obj) {'."\n";
      echo '   var curleft = curtop = 0;'."\n";
      echo "\n";
      echo '   if (obj.offsetParent)'."\n";
      echo '      do {'."\n";
      echo '         curleft += obj.offsetLeft;'."\n";
      echo '         curtop += obj.offsetTop;'."\n";
      echo '      }while (obj = obj.offsetParent);'."\n";
      echo "\n";
      echo '   return [curleft,curtop];'."\n";
      echo '}'."\n";
   }
   if($_REQUEST['js']=='maintenance'){
      echo '      topMost    = "0:0";'."\n";
      echo '      leftMost   = "0:0";'."\n";
      echo '      rightMost  = "0:0";'."\n";
      echo '      bottomMost = "0:0";'."\n";
      echo "\n";
      echo 'function maintenance(elem){'."\n";
      echo '   var table = document.getElementById("game:grid");'."\n";
      echo "\n";
      echo '      se  = document.getElementById("scroll:enable"); // se  /Scroll:Enable/'."\n";
      echo '      sbx = -20;                                       // sbx /Scroll  By (X)/'."\n";
      echo '      ebx = 40;                                        // ebx /Enlarge By (X)/'."\n";
      echo '      sby = -12;                                       // sby /Scroll By (Y)/'."\n";
      echo "\n";
      echo '  //If the Cell clicked is:'."\n";
      echo '   //The Last Cell on the Left'."\n";
      echo '   if(x(elem.id)==x(leftMost)){'."\n";
      echo '      for(i=0;i<table.rows.length;i++){'."\n";
      echo '         cell = document.createElement("td");'."\n";
      echo "\n";
      echo '         cell.className = "game cell";'."\n";
      echo '         cell.id = (x(leftMost)-1)+":"+(y(topMost)-i);'."\n";
      echo "\n";
      echo '         document.getElementById(":"+(y(topMost)-i)).insertBefore(cell, document.getElementById(":"+(y(topMost)-i)).firstChild);'."\n";
      echo '      }'."\n";
      echo "\n";
      echo '      leftMost = cell.id;'."\n";
      echo "\n";
      echo '      if(se){'."\n";
      echo '         se.style.width = parseInt(se.offsetWidth) + sbx;'."\n";
      echo '         window.scrollBy(sbx, 0);'."\n";
      echo '         table.style.width = parseInt(table.offsetWidth) + sbx + ebx;'."\n";
      echo '      }'."\n";
      echo '   }'."\n";
      echo "\n";
      echo '   //The Last Cell on the Right'."\n";
      echo '   if(x(elem.id)==x(rightMost)){'."\n";
      echo '      for(i=0;i<table.rows.length;i++){'."\n";
      echo '         cell = document.createElement("td");'."\n";
      echo "\n";
      echo '         cell.className = "game cell";'."\n";
      echo '         cell.id = (x(rightMost)-(-1))+":"+(y(topMost)-i);'."\n";
      echo "\n";
      echo '         document.getElementById(":"+(y(topMost)-i)).appendChild(cell);'."\n";
      echo '      }'."\n";
      echo "\n";
      echo '      rightMost = cell.id;'."\n";
      echo "\n";
      echo '      if(se){'."\n";
      echo '         se.style.width = parseInt(se.offsetWidth) + sbx;'."\n";
      echo '         window.scrollBy(sbx, 0);'."\n";
      echo '         table.style.width = parseInt(table.offsetWidth) + sbx + ebx;'."\n";
      echo '      }'."\n";
      echo '   }'."\n";
      echo "\n";
      echo '   //The Last Cell on the Top'."\n";
      echo '   if(y(elem.id)==y(topMost)){'."\n";
      echo '      cells = table.rows[0].cells.length;'."\n";
      echo "\n";
      echo '      row = document.createElement("tr");'."\n";
      echo '      row.id = ":"+(y(topMost)-(-1));'."\n";
      echo '      table.insertBefore(row, table.firstChild);'."\n";
      echo "\n";
      echo '      for(i=0; i<cells; i++){'."\n";
      echo '         cell = document.createElement("td");'."\n";
      echo "\n";
      echo '         cell.className = "game cell";'."\n";
      echo '         cell.id = (x(leftMost)-(-i))+":"+(y(topMost)-(-1));'."\n";
      echo "\n";
      echo '         row.appendChild(cell);'."\n";
      echo '      }'."\n";
      echo "\n";
      echo '      topMost = cell.id;'."\n";
      echo "\n";
      echo '      if(se){'."\n";
      echo '         se.style.height = parseInt(se.offsetHeight) + sby;'."\n";
      echo '         window.scrollBy(0, sby);'."\n";
      echo '      }'."\n";
      echo '   }'."\n";
      echo "\n";
      echo '   //The Last Cell on the Bottom'."\n";
      echo '   if(y(elem.id)==y(bottomMost)){'."\n";
      echo '      cells = table.rows[0].cells.length;'."\n";
      echo "\n";
      echo '      row = document.createElement("tr");'."\n";
      echo '      row.id = ":"+(y(bottomMost)-1);'."\n";
      echo '      table.appendChild(row);'."\n";
      echo "\n";
      echo '      for(i=0; i<cells; i++){'."\n";
      echo '         cell = document.createElement("td");'."\n";
      echo "\n";
      echo '         cell.className = "game cell";'."\n";
      echo '         cell.id = (x(leftMost)-(-i))+":"+(y(bottomMost)-1);'."\n";
      echo "\n";
      echo '         row.appendChild(cell);'."\n";
      echo '      }'."\n";
      echo "\n";
      echo '      bottomMost = cell.id;'."\n";
      echo "\n";
      echo '      if(se){'."\n";
      echo '         se.style.height = parseInt(se.offsetHeight) + sby;'."\n";
      echo '         window.scrollBy(0, sby);'."\n";
      echo '      }'."\n";
      echo '   }'."\n";
      echo '}'."\n";
   }
   if($_REQUEST['js']=='mark'){
      echo '   curTurn = 1;'."\n";
      echo '   turns = 0;'."\n";
      echo '   gameOver = false;'."\n";
      echo '   lastPlayed = false;'."\n";
      echo "\n";
      echo 'function mark(elem, myAttempt){'."\n";
      echo '   //If the game is over or is not your turn, stop'."\n";
      echo '   if(gameOver) return;'."\n";
      echo '   if(myAttempt && curTurn != myTurn) return false;'."\n";
      echo '   if(myAttempt && inProgress) return false;'."\n";
      echo "\n";
      echo '   //Get the element to mark'."\n";
      echo '   if(typeof elem == "string") elem = document.getElementById(elem);'."\n";
      echo "\n";
      echo '    //Dont bother making the call if inProgress'."\n";
      echo '    if(!inProgress)'."\n";
      echo '       var cr = call("?mark="+elem.id, true);'."\n";
      echo "\n";
      echo '   //Trigger the AI to act'."\n";
      echo '   if(typeof cr != \'undefined\' && cr.indexOf("AI next")!=-1)'."\n";
      echo '      call("?ai");'."\n";
      echo "\n";
      echo '   // If not in progress and call did not returned "Success", stop'."\n";
      echo '   if(!(inProgress || cr.indexOf("Success")!=-1))'."\n";
      echo '      return false;'."\n";
      echo "\n";
      echo '   // Maintain the Game Grid to proper size'."\n";
      echo '   maintenance(elem);'."\n";
      echo "\n";
      echo '   //Display adjecent cells where needed'."\n";
      echo '   // i==(xOffset), j==(yOffset)'."\n";
      echo '   for(i=-1;i<=1;i++)'."\n";
      echo '      for(j=-1;j<=1;j++){'."\n";
      echo '         cell = document.getElementById( (x(elem.id)-(-i))+":"+(y(elem.id)-(-j)) );'."\n";
      echo "\n";
      echo '         if(cell.className.indexOf("available")!=-1)'."\n";
      echo '            continue;'."\n";
      echo "\n";
      echo '         cell.className += " available";'."\n";
      echo '         if(!observer){'."\n";
      echo '           cell.setAttribute("onclick",     "mark(this, true);");'."\n";
      echo '           cell.setAttribute("onmouseover", "highlight(this);");'."\n";
      echo '           cell.setAttribute("onmouseout",  "unhighlight(this);");'."\n";
      echo '         }'."\n";
      echo '      }'."\n";
      echo "\n";
      echo '   //Get the mark before turns shift and the wrong mark is fetched'."\n";
      echo '   var m = marks[curTurn-1];'."\n";
      echo "\n";
      echo '   //Adorn the marked cell'."\n";
      echo '   elem.innerHTML  = m;'."\n";
      echo '   elem.className += " player"+curTurn;'."\n";
      echo "\n";
      echo '   //Disable Element\'s further marking'."\n";
      echo '   elem.setAttribute("onclick", "");'."\n";
      echo '   elem.setAttribute("onmouseover", "");'."\n";
      echo '   elem.setAttribute("onmouseout", "");'."\n";
      echo "\n";
      echo '   //Apply and broadcast the move/turn'."\n";
      echo '     //call("?mark="+elem.id, true);'."\n";
      echo '   curTurn = (curTurn % marks.length)-(-1);'."\n";
      echo '   turns++;'."\n";
      echo "\n";
      echo '   //Handle multiple instances of one player'."\n";
      echo '   if(!observer){'."\n";
      echo '      myTurn = parseInt(call("?myturn="+curTurn, true));'."\n";
      echo '      if(isNaN(parseInt(myTurn))) alert("Error at turn update!");'."\n";
      echo '   }'."\n";
      echo "\n";
      echo '   //Mark the Last Played Move'."\n";
      echo '   if(lastPlayed)'."\n";
      echo '      lastPlayed.className = lastPlayed.className.split("last").join("");'."\n";
      echo '   elem.className += " last";'."\n";
      echo '   lastPlayed = elem;'."\n";
      echo "\n";
      echo '   //Announce Turns'."\n";
      echo '   msg = (!relativeAnnouncement)? "<b>"+marks[curTurn-1]+"</b>\'s turn" : (curTurn==myTurn ? "Your Turn" : "Opponent\'s Turn");'."\n";
      echo '   document.getElementById("notice").innerHTML = msg;'."\n";
      echo "\n";
      echo '   //Check for victory'."\n";
      echo '   if(winCheck(elem.id, m)){'."\n";
      echo '      gameOver = true;'."\n";
      echo '      document.getElementById("notice").innerHTML = "<big style=\"color:darkblue;\"><b>"+m+"</b> Won!</big>";'."\n";
      echo "\n";
      echo '      if(document.getElementById("replay"))'."\n";
      echo '         document.getElementById("replay").innerHTML = "<br /><br><a href=\'#\' onclick=\'call(\"?replay\", function(t){window.location.href=\"?replay&observe='.session_id().'\";} );\'>Replay</a>";'."\n";
      echo "\n";
      echo '      if(document.getElementById("observe"))'."\n";
      echo '         document.getElementById("observe").innerHTML = "<br /><a href=\'#\' onclick=\'call(\"?record&winner="+marks.indexOf(m)+"&rename&to=\"+prompt(\"Would you like to rename the replay?\"), function(t){window.location.href=\"?\";} );\'>Record</a>";'."\n";
      echo '   }'."\n";
      echo '   return true;'."\n";
      echo '}'."\n";
   }
   if($_REQUEST['js']=='winCheck'){
      echo 'function winCheck(elem, mark){'."\n";
      echo '   //Check Horisontal Axis'."\n";
      echo '      x_ = x(elem);'."\n";
      echo '      for(c=0; c<4; c++)'."\n";
      echo '         if(document.getElementById((--x_)+":"+y(elem)).innerHTML!=mark)'."\n";
      echo '            break;'."\n";
      echo '      if(c==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '      x_ = x(elem);'."\n";
      echo '      for(d=0; d<4; d++)'."\n";
      echo '         if(document.getElementById((++x_)+":"+y(elem)).innerHTML!=mark)'."\n";
      echo '            break;'."\n";
      echo '      if(d==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '      if(c-(-d)==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '   //Check Vertical Axis'."\n";
      echo '      y_ = y(elem);'."\n";
      echo '      for(c=0; c<4; c++)'."\n";
      echo '         if(document.getElementById(x(elem)+":"+(--y_)).innerHTML!=mark)'."\n";
      echo '            break;'."\n";
      echo '      if(c==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '      y_ = y(elem);'."\n";
      echo '      for(d=0; d<4; d++)'."\n";
      echo '         if(document.getElementById(x(elem)+":"+(++y_)).innerHTML!=mark)'."\n";
      echo '            break;'."\n";
      echo '      if(d==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '      if(c-(-d)==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '   //Check  \ diagonal'."\n";
      echo '      x_ = x(elem); y_ = y(elem);'."\n";
      echo '      for(c=0; c<4; c++)'."\n";
      echo '         if(document.getElementById((--x_)+":"+(--y_)).innerHTML!=mark)'."\n";
      echo '            break;'."\n";
      echo '      if(c==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '      x_ = x(elem); y_ = y(elem);'."\n";
      echo '      for(d=0; d<4; d++)'."\n";
      echo '         if(document.getElementById((++x_)+":"+(++y_)).innerHTML!=mark)'."\n";
      echo '            break;'."\n";
      echo '      if(d==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '      if(c-(-d)==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '   //Check  / diagonal'."\n";
      echo '      x_ = x(elem); y_ = y(elem);'."\n";
      echo '      for(c=0; c<4; c++)'."\n";
      echo '         if(document.getElementById((--x_)+":"+(++y_)).innerHTML!=mark)'."\n";
      echo '            break;'."\n";
      echo '      if(c==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '      x_ = x(elem); y_ = y(elem);'."\n";
      echo '      for(d=0; d<4; d++)'."\n";
      echo '         if(document.getElementById((++x_)+":"+(--y_)).innerHTML!=mark)'."\n";
      echo '            break;'."\n";
      echo '      if(d==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '      if(c-(-d)==4)'."\n";
      echo '         return true;'."\n";
      echo "\n";
      echo '   return false;'."\n";
      echo '}'."\n";
   }
   if($_REQUEST['js']=='highlight'){
      echo 'function highlight(elem){'."\n";
      echo '   if(gameOver) return;'."\n";
      echo "\n";
      echo '   if(elem.className.indexOf("available")!=-1)'."\n";
      echo '      elem.innerHTML=" "+marks[myTurn-1]+" ";'."\n";
      echo '}'."\n";
      echo "\n";
      echo 'function unhighlight(elem){'."\n";
      echo '   if(gameOver) return;'."\n";
      echo "\n";
      echo '   if(elem.innerHTML == " "+marks[myTurn-1]+" ")'."\n";
      echo '      elem.innerHTML = "";'."\n";
      echo '}'."\n";
   }
   if($_REQUEST['js']=='coloring'){
      echo '      _hMark = mark;'."\n";
      echo '      moves_string = "";'."\n";
      echo '      colored = new Array();'."\n";
      echo '      mark = function(elem, myAttempt){'."\n";
      echo '         if(_hMark(elem, myAttempt)){'."\n";
      echo '            if(moves_string.length!=0)'."\n";
      echo '               moves_string += ",";'."\n";
      echo '            moves_string += (typeof elem == "string")? (elem) : (elem.id);'."\n";
      echo '         }'."\n";
      echo "\n";
      echo '         call("ai.php?ann=dangerous&m="+moves_string, function(t){'."\n";
      echo "\n";
      echo '            var n  = (curTurn-2)'."\n";
      echo '            var el = moves_string.split(",").reverse()[0];'."\n";
      echo '            var _x = el.split(":")[0];'."\n";
      echo '            var _y = el.split(":")[1];'."\n";
      echo '            if(!colored[n]) colored[n] = new Array();'."\n";
      echo "\n";
      echo '            var m, p, c;'."\n";
      echo '            for(m in colored)'."\n";
      echo '               if(m != n)'."\n";
      echo '                  for(p in colored[m]){'."\n";
      echo '                     var stepX = ((colored[m][p][0].split(":")[0] - colored[m][p][1].split(":")[0]) != 0)? 1 : 0;'."\n";
      echo '                     var stepY = ((colored[m][p][0].split(":")[1] - colored[m][p][1].split(":")[1]) != 0)? 1 : 0;'."\n";
      echo "\n";
      echo '                     for(c in colored[m][p]){'."\n";
      echo '                        var x_ = colored[m][p][c].split(":")[0];'."\n";
      echo '                        var y_ = colored[m][p][c].split(":")[1];'."\n";
      echo "\n";
      echo '                        if( Math.abs( Math.abs(x_) - Math.abs(_x) ) == stepX  &&  Math.abs( Math.abs(y_) - Math.abs(_y) ) == stepY){'."\n";
      echo '                           colored[n].push(colored[m][p]);'."\n";
      echo '                           delete colored[m][p];'."\n";
      echo '                           break;'."\n";
      echo '                        }'."\n";
      echo '                     }'."\n";
      echo '                  }'."\n";
      echo "\n";
      echo '            var i, j;'."\n";
      echo '            for(i in colored[n]){'."\n";
      echo '               for(j in colored[n][i]){'."\n";
      echo '                  var ele = document.getElementById(colored[n][i][j]);'."\n";
      echo '                  ele.className = ele.className.replace(" dangerous", "");'."\n";
      echo '               }'."\n";
      echo '               delete colored[n][i]'."\n";
      echo '            }'."\n";
      echo "\n";
      echo '            if(t=="") return false;'."\n";
      echo "\n";
      echo '            colored[curTurn-2] = new Array();'."\n";
      echo '            var dl = t.split("\n");'."\n";
      echo "\n";
      echo '            var i;'."\n";
      echo '            for(i=0; i<dl.length; i++){'."\n";
      echo '               if(dl[i].length == 0) continue;'."\n";
      echo "\n";
      echo '               colored[curTurn-2][i] = new Array();'."\n";
      echo '               var dc = dl[i].split(",");'."\n";
      echo "\n";
      echo '               for(j=0; j<dc.length; j++){'."\n";
      echo '                  var elem = document.getElementById( dc[j] );'."\n";
//      echo '                  if(elem.className.indexOf("dangerous") == -1)'."\n";
      echo '                     elem.className += " dangerous";'."\n";
      echo "\n";
      echo '                  colored[curTurn-2][i][j] = dc[j]'."\n";
      echo '               }'."\n";
      echo "\n";
      echo '            }'."\n";
      echo '         });'."\n";
      echo '      }'."\n";
   }
   exit;
}

//CSS
css:
if(isset($_REQUEST['css'])){
   if($_REQUEST['css']=='game'){
      echo '      .game.cell{'."\n";
      echo '         width:20px;'."\n";
      echo '         height:20px;'."\n";
      echo '         text-align:center;'."\n";
      echo '         font-size:10;'."\n";
      echo '      }'."\n";
      echo '      .available{'."\n";
      echo '         background-color: #f9f9f9;'."\n";
      echo '      }'."\n";
      echo '      .player1{'."\n";
      echo '         color: blue;'."\n";
      echo '      }'."\n";
      echo '      .player2{'."\n";
      echo '         color: red;'."\n";
      echo '      }'."\n";
      echo '      .player3{'."\n";
      echo '         color: green;'."\n";
      echo '      }'."\n";;
      echo '      .player4{'."\n";
      echo '         color: orange;'."\n";
      echo '      }'."\n";
      echo "\n";
      echo '      .dangerous{'."\n";
      echo '         font-size: large;'."\n";
      echo '         font-weight: bold;'."\n";
      echo '      }'."\n";
//      echo '      .player1.dangerous{'."\n";
//      echo '         background-color: #ccccff;'."\n";
//      echo '      }'."\n";
//      echo '      .player2.dangerous{'."\n";
//      echo '         background-color: #ffcccc;'."\n";
//      echo '      }'."\n";
//      echo '      .player3.dangerous{'."\n";
//      echo '         background-color: lightgreen;'."\n";
//      echo '      }'."\n";
//      echo '      .player4.dangerous{'."\n";
//      echo '         background-color: yellow;'."\n";
//      echo '      }'."\n";
      echo '      .last{'."\n";
      echo '         border:2px solid #d4d4d4;'."\n";
      //echo '         background-color: #d4d4d4;'."\n";
      echo '      }'."\n";
   }
   exit;
}

//HTML
head:{
   echo '<html>'."\n";
}

body:{
   //Game - Observe
   observe:{
      if(isset($_REQUEST['observe'])){
         $_REQUEST['observe'] = mysql_real_escape_string($_REQUEST['observe']);
         if(mysql_("SELECT GameName FROM scu_games WHERE Players like '".(isset($_REQUEST['replay'])?'%':'').$_REQUEST['observe']."%'",true)<1){
            echo '<script type="text/javascript">window.location.href="?";</script>';
            exit;
         }

         echo '<head>'."\n";
         echo '   <title>Sea Chess Unbound</title>'."\n";
         echo "\n";
         echo '   <script type="text/javascript" src="?js=call"></script>'."\n";
         echo '   <script type="text/javascript" src="?js=coordinates"></script>'."\n";
         echo '   <script type="text/javascript" src="?js=maintenance"></script>'."\n";
         echo '   <script type="text/javascript" src="?js=mark"></script>'."\n";
         echo '   <script type="text/javascript" src="?js=winCheck"></script>'."\n";
         echo '   <script type="text/javascript" src="?js=coloring"></script>'."\n";
         echo '   <script type="text/javascript">'."\n";
         echo '      observer = true;'."\n";
         echo '      relativeAnnouncement = false;'."\n";
         echo '      hasAI = false;'."\n";
         echo '      inProgress = true;'."\n";
         echo "\n";
         echo '      marks = new Array("'.join('", "', explode(",", mysql_("SELECT Marks FROM scu_games WHERE Players like '%".$_REQUEST['observe']."%'"))).'")'."\n";
         echo '      speed  = '.(isset($_REQUEST['speed'])? $_REQUEST['speed']*1000 : 2000).';'."\n";
         echo '   </script>'."\n";
         echo "\n";
         echo '   <link rel="stylesheet" type="text/css" href="?css=game" />'."\n";
         echo "\n";
         echo '</head>'."\n";
         echo '<body onunload="call(\'?clear\', true);">'."\n";
         echo "\n";
         echo '<table width="100%" height="100%">'."\n";
         echo '   <tr>'."\n";
         echo '      <td width="80%" height="90%" align="center" valign="middle">'."\n";
         echo "\n";
         echo '         <table id="game:grid" style="position:relative;">'."\n";
         echo '            <tr id=":0">'."\n";
         echo '               <td id="0:0" class="game cell"></td>'."\n";
         echo '            </tr>'."\n";
         echo '         </table>'."\n";
         echo "\n";
         echo '         <br />'."\n";
         echo '         <div id="notice"></div>'."\n";
         echo "\n";
         echo '      </td>'."\n";
         echo '   </tr>'."\n";
         echo '   <tr>'."\n";
         echo '      <td width="80%" height="10%" align="center" valign="bottom">'."\n";
         echo "\n";

         echo '         <a href="#"><em onclick="speed = Math.min( (speed -(-500)) , 10000 );">Slow Down</em></a>'."\n";

         echo "\n";
         echo ' &nbsp; &nbsp; '."\n";

         echo '         <a href="#"><strong id="pause"'.
              ' onclick="'.
              '   if(this.innerHTML==\'Pause\'){'.
              '      clearTimeout(reader);'.
              '      this.innerHTML=\'Resume\';'.
              '   }else{ '.
              '      read();'.
              '      this.innerHTML=\'Pause\';'.
              '   }'.
                        '">Pause</strong></a>'."\n";

         echo "\n";
         echo ' &nbsp; &nbsp; '."\n";

         echo '         <a href="#"><em onclick="speed = Math.max( (speed - 500) , 100 );">Speed Up</em></a>'."\n";

         echo "\n";
         echo '         <br />'."\n";
         echo ' &nbsp; '."\n";
         echo ' <a href="#"'.
              '  onclick="'.
              '    if(!lastPlayed) return;'.
              '    if( lastPlayed.id == \'0:0\') return;'.
              '    lastPlayed.innerHTML = \'\';'.
              '    lastPlayed.className = \'game cell available\';'.
              '    clearTimeout(reader);'.
              '    document.getElementById(\'pause\').innerHTML = \'Resume\';'.
              '    turns   -= 2;'.
              '    gameOver = false;'.
              '    mark( call(\'?turn=\'+(turns-(-1))+\'&host='.$_REQUEST['observe'].'\', true) );'.
              ' ">Back a Turn</a>'."\n";

         echo "\n";
         echo '         <br /><br />'."\n";
         echo '         &nbsp; <big id="quit"><a onclick="call(\'?clear&game\', true);" href="?">Quit</a></big>'."\n";
         echo "\n";
         echo '         <script type="text/javascript">'."\n";
         echo '            function read(){'."\n";
         echo '               if((t = call("?turn="+(turns-(-1))+"&host='.$_REQUEST['observe'].'", true)) !="")'."\n";
         echo '                  mark(t);'."\n";
         echo "\n";
         echo '               if(gameOver){'."\n";
         echo '                  inProgress = false;'."\n";
         echo '                  return "done";'."\n";
         echo '               }'."\n";
         echo "\n";
         echo '               reader = setTimeout(arguments.callee, speed);'."\n";
         echo '            } read();'."\n";
         echo '         </script>'."\n";
         echo "\n";
         echo '      </td>'."\n";
         echo '   </tr>'."\n";
         echo '</table>'."\n";
         echo "\n";
         echo '</body>'."\n";
         goto end;
      }
   }
   //Game - Active
   game:{
      if(mysql_("SELECT GameName FROM scu_games WHERE (Turns is not NULL and Turns like '0:0%') AND Players like '%".session_id()."%'", true)>0){

         echo '<head>'."\n";
         echo '   <title>Sea Chess Unbound</title>'."\n";
         echo "\n";
         echo '   <script type="text/javascript" src="?js=call"></script>'."\n";
         echo '   <script type="text/javascript" src="?js=coordinates"></script>'."\n";
         echo '   <script type="text/javascript" src="?js=maintenance"></script>'."\n";
         echo '   <script type="text/javascript" src="?js=mark"></script>'."\n";
         echo '   <script type="text/javascript" src="?js=winCheck"></script>'."\n";
         echo '   <script type="text/javascript" src="?js=highlight"></script>'."\n";
         echo '   <script type="text/javascript">'."\n";
         echo '      observer = false;'."\n";
            $pl = mysql_("SELECT Players FROM scu_games WHERE Players LIKE '%".session_id()."%'");
         echo '      relativeAnnouncement = '.((count(explode(",", $pl)) > count(array_unique(explode(",", $pl))))? 'false' : 'true').';'."\n";
         echo '      hasAI = '.(stripos(" ".$pl, "AI:")? 'true' : 'false').';'."\n";
         echo '      inProgress = true;'."\n";
         echo "\n";
         echo '      marks = new Array("'.join('", "', explode(",", mysql_("SELECT Marks FROM scu_games WHERE Players like '%".session_id()."%'"))).'")'."\n";
         echo '      myTurn = '.(array_search(session_id(), explode(",", mysql_("SELECT Players FROM scu_games WHERE Players like '%".session_id()."%'")))+1)."\n";
         echo "\n";
         echo "\n";
         echo '      _dSurvey = dSurvey = 1000;'."\n";
         echo '      lastMeaningful = (new Date()).getTime();'."\n";
         echo '      function dynamize(meaningful, stepCap){'."\n";
         echo '         if(!stepCap) stepCap = 10000;'."\n";
         echo '         if(!meaningful) meaningful = false; else meaningful = true;'."\n";
         echo "\n";
         echo '         var delay = ( new Date() ).getTime() - lastMeaningful;'."\n";
         echo '         dSurvey = Math.min( ( dSurvey -(-stepCap) ) , Math.round( ( dSurvey -(-delay) )/2) );'."\n";
         echo "\n";
         echo '         if(meaningful) lastMeaningful = ( new Date() ).getTime();'."\n";
         echo '      }'."\n";
         echo "\n";
         echo '      dynamicSurvey = true;'."\n";
         echo '      function listen(){'."\n";
         echo '         call("?turn="+(turns-(-1)), function(t){ dynamize(t!="", 200);  if(t=="Abandoned"){ alert("Your opponent has left the game.\n\nYou can neither continue nor record this game."); gameOver=true; }else if(t!="") mark(t);}, true)'."\n";
         echo "\n";
         echo '         if(!gameOver) reader = setTimeout(arguments.callee, (dynamicSurvey? dSurvey : _dSurvey) );'."\n";
         echo '      }'."\n";
         echo "\n";
         echo '      //Make the initial call'."\n";
         echo '      window.onload = function(){'."\n";
         echo '         while(inProgress && !gameOver)'."\n";
         echo '            call("?turn="+(turns-(-1)), function(t){if(t!="") mark(t); else inProgress = false;}, true);'."\n";
         echo "\n";
         echo '         listen("for new turns");'."\n";
         echo '      }'."\n";
         echo '   </script>'."\n";
         echo "\n";
         echo '   <link rel="stylesheet" type="text/css" href="?css=game" />'."\n";
         echo "\n";
         echo '</head>'."\n";
         echo '<body onunload="call(\'?clear\', true);">'."\n";
         echo "\n";
         echo '<table width="100%" height="100%">'."\n";
         echo '   <tr>'."\n";
         echo '      <td width="80%" height="95%" align="center" valign="middle">'."\n";
         echo "\n";
         echo '         <table id="game:grid">'."\n";
         echo '            <tr id=":0">'."\n";
         echo '               <td id="0:0" class="game cell"></td>'."\n";
         echo '            </tr>'."\n";
         echo '         </table>'."\n";
         echo "\n";
         echo '         <br />'."\n";
         echo '         <div id="notice"></div>'."\n";
         echo '         <div id="replay"></div>'."\n";
         echo "\n";
         echo '      </td>'."\n";
         echo '   </tr>'."\n";
         echo '   <tr>'."\n";
         echo '      <td width="80%" height="5%" align="center" valign="bottom">'."\n";
         echo "\n";
         echo '         <br /><br /><br />'."\n";
         echo '         <big id="quit"><a onclick="call(\'?clear&game\', true);" href="?">Quit</a></big>'."\n";
         echo '         <div id="observe"></div>'."\n";
         echo "\n";
         echo '      </td>'."\n";
         echo '   </tr>'."\n";
         echo '</table>'."\n";
         echo "\n";
         echo '</body>'."\n";
         goto end;
      }
   }
   //Game - Created / Joined
   preparation:{
      if(mysql_("SELECT GameName FROM scu_games WHERE (Turns is NULL  or Turns not like '0:0%') AND Players like '%".session_id()."%'", true)>0){
         echo '<head>'."\n";
         echo '   <title>Sea Chess Unbound</title>'."\n";
         echo "\n";
         echo '   <script type="text/javascript" src="?js=call"></script>'."\n";
         echo '   <script type="text/javascript">call("?status", function(t){if(t=="dead")window.location.href="?";});</script>'."\n";
         echo '</head>'."\n";
         echo '<body onunload="call(\'?clear&game\', true);">'."\n";
         echo "\n";
         echo 'Welcome '.$_COOKIE['username']."\n";
         echo '<br /><a href="?logout"><small>Logout</small></a>'."\n";
         echo "\n";
         echo '<table width="100%" style="margin-top: 50px;">'."\n";
         echo '   <tr>'."\n";
         echo '      <td width="80%" align="center">'."\n";
         echo "\n";
         echo '         <table rules="all" cellpadding="20">'."\n";
         echo '            <tr>'."\n";
         echo '               <td colspan="2" align="center">'."\n";

         $host = (mysql_("Select GameName from scu_games where Players like '".session_id()."%'", true)>0);
         if($host){
            echo '                  <label title="The name by which the game will be seen">'."\n";
            echo '                     <strong>Game Name</strong><br />'."\n";
            echo '                     <input id="game:name" type="text" style="text-align:center" value="'.mysql_("Select GameName from scu_games where Players like '".session_id()."%'").'" onchange="call(\'?update&game=\'+this.value);" />'."\n";
            echo '                  </label>'."\n";
         }else{
            echo '                  <strong>Game Name</strong><br />'."\n";
            echo '                  <em id="game:name"><b>'.mysql_("Select GameName from scu_games where Players like '%".session_id()."%'").'</em>'."\n";
         }

         echo '<br /><br />'."\n";

         if($host){
            echo '                  <label title="Reserve: abija     Ban: !abija     Limit Player Count: <3  =2">'."\n";
            echo '                     <strong>Player Rules</strong><br />'."\n";
            echo '                     <input id="game:rules" type="text" style="text-align:center" value="'.mysql_("Select Rules from scu_games where Players like '".session_id()."%'").'" onchange="call(\'?update&rules=\'+this.value);" />'."\n";
            echo '                  </label>'."\n";
         }else{
            echo '                  <strong>Player Rules</strong><br />'."\n";
            echo '                  <em id="game:rules">'.mysql_("Select Rules from scu_games where Players like '%".session_id()."%'").'</em>'."\n";
         }

         echo '<br /><br />'."\n";

         if($host){
            echo '                  <strong>Private:</strong>'."\n";
            echo '                  <input id="game:private" type="checkbox" '.(mysql_("Select Private from scu_games where Players like '".session_id()."%'")? 'checked' : '').'" onchange="call(\'?update&private=\'+(this.checked? 1 : 0)); refreshLink();" />'."\n";
            echo '                     <div id="game:link" title="People can only join your game using this link">'."\n";
            echo '                        <input type="text" style="text-align:center" readonly value="http://46.10.101.59:22080/SCU/?join='.session_id().'" />'."\n";
            echo '                     </div>'."\n";
            echo '                     <script type="text/javascript">'."\n";
            echo '                     function refreshLink(){'."\n";
            echo '                        var chckbx = document.getElementById("game:private");'."\n";
            echo '                        var shrlnk = document.getElementById("game:link");'."\n";
            echo "\n";
            echo '                        if(chckbx.checked)'."\n";
            echo '                           shrlnk.style.display = "block";'."\n";
            echo '                        else'."\n";
            echo '                           shrlnk.style.display = "none";'."\n";
            echo '                     }'."\n";
            echo '                     refreshLink();'."\n";
            echo '                     </script>'."\n";
         }else{
            echo '                  <strong>'.(mysql_("Select Private from scu_games where Players like '%".session_id()."%'")? 'Private game' : 'Public Game').'</strong>'."\n";
         }

         echo "\n";
         echo '               </td>'."\n";
         echo '            </tr>'."\n";
         echo '            <tr>'."\n";
         echo '                <style type="text/css">small:hover{cursor: default; text-decoration:underline;} select.subtle{border: 1px solid transparent;} select.subtle:hover{border: 1px solid #7F9DB9;}</style>'."\n";
         echo '               <td colspan="2" align="center">'."\n";
         echo '                  Players in game:'."\n";
         echo '                  <div id="players"></div><br />'."\n";
         echo '                  <script type="text/javascript">'."\n";
         echo '                     q_p = true; //Query:Players'."\n";
         echo "\n";
         echo '                     function displayPlayers(t){'."\n";
         echo '                        if(t == q_p) return true;'."\n";
         echo '                        q_p = t;'."\n";
         echo "\n";
         echo '                        if(t!=""){'."\n";
         echo '                           code  = "<table>\n";'."\n";
         echo '                           code += "   <tr>\n";'."\n";
         //echo '                           code += "      <td width=\"70\"></td>\n";'."\n";
         echo '                           code += "      <td width=\"50\"><strong>Turn</strong></td>\n";'."\n";
         echo '                           code += "      <td width=\"150\"><strong>Nickname</strong></td>\n";'."\n";
         echo '                           code += "      <td><strong>Options</strong></td>\n";'."\n";
         echo '                           code += "   </tr>\n";'."\n\n";
         echo "\n";
         echo '                           var ts = t.split(/\(|\)|\[|\]|\{|\}|,/);'."\n";
         echo '                           var players  = new Array();'."\n";
         echo "\n";
         echo '                           for(i=0; i<ts.length; i+=7)'."\n";
         echo '                              players[i/7] = ts.slice(i, (i+6));'."\n";
         echo '                           //player[j] :  {[0]=>SID , [1]=>Nick , [3]=>Mark, [5]=>Turn}'."\n";
         echo "\n";
         echo '                           row = 0; //Needs to be global for reference in js_startgame()'."\n";
         echo '                           var i;'."\n";
         echo "\n";
         echo '                           for(i=0; i<players.length; i++){'."\n";
         echo '                              code += "   <tr>\n";'."\n";
   //      echo '                              code += "      <td><em>"+(i==0? "Host" : "Guest")+"</em>:</td>\n";'."\n";
         echo '                              code += "      <td> <input type=\"text\" id=\"player:"+(row++)+"-turn\" value=\""+players[i][5]+"\"'.($host? ' onchange=\"call(\'?update&sequence&set="+row+"&to=\'+this.value);\"' : ' readonly').' style=\"width:25px;text-align:center;\" /> </td>\n";'."\n";
         echo '                              code += "      <td><strong>"+players[i][1]+"</strong></td>\n";'."\n";
         if($host){
            echo '                              if(i!=0){'."\n";
            echo '                                 code += "      <td><strong>\n";'."\n";
            echo '                                 code += "         <a href=\"#\" onclick=\"call(\'?kick="+i+"\');//call(\'?kick="+players[i][0]+"\');\">Kick</a>\n";'."\n";
            echo '                                 if(players[i][1] != players[0][1] && players[i][1].indexOf("AI")==-1){'."\n";
            echo '                                    code += "         &nbsp;, &nbsp;\n";'."\n";
            echo '                                    code += "         <a href=\"?\" onclick=\"window.onunload=\'\'; call(\'?promote="+players[i][0]+"\', true);\" title=\"Promote to host\">Promote</a>\n"'."\n";
            echo '                                 }'."\n";
            echo '                                    code += "      </strong></td>\n";'."\n";
            echo '                              }else'."\n";
            echo '                                 code += "      <td><strong><a href=\"?\">Leave</a></strong></td>\n";'."\n";
         }else{
            echo '                              if(players[i][1] == "'.$_COOKIE['username'].'")'."\n";
            echo '                                 code += "      <td><strong><a href=\"?\">Leave</a></strong></td>\n";'."\n";
         }
         echo '                              code += "   </tr>\n";'."\n";
         echo '                           }'."\n";

         if($host){
            echo "\n";
            echo '                        code += "   <tr> <td colspan=\"4\" align=\"center\">";'."\n";
            echo '                        code += "      <small>Add</small>";'."\n";
            echo '                        code += "      <select class=\"subtle\" onchange=\"if(this.value != \'\') call(\'?join='.session_id().'&add=\'+this.value);\">";'."\n";
            echo "\n";
            echo '                        var i;'."\n";
            echo '                        var showed = new Array();'."\n";
            echo "\n";
            echo '                        showed.push("AI:Easy");'."\n";
            echo '                        showed.push("AI:Normal");'."\n";
            echo '                        showed.push("AI:Hard");'."\n";
            echo "\n";
            echo '                        code += "         <option value=\"\" selected>a player</option>";'."\n";
            echo "\n";
            echo '                        for(i=0; i<players.length; i++)'."\n";
            echo '                           if(showed.indexOf(players[i][0]) == -1){'."\n";
            echo '                              code += "         <option value=\""+players[i][0]+"\">"+players[i][1]+"</option>";'."\n";
            echo '                              showed.push(players[i][0]);'."\n";
            echo '                           }'."\n";
            echo "\n";
            echo '                        code += "         <option value=\"\">-------------</option>";'."\n";
            echo '                        code += "         <option value=\"AI:Easy\">AI:Easy</option>";'."\n";
            echo '                        code += "         <option value=\"AI:Normal\">AI:Normal</option>";'."\n";
            echo '                        code += "         <option value=\"AI:Hard\">AI:Hard</option>";'."\n";
            echo '                        code += "      </select>";'."\n";
            echo '                     code += "   </td> </tr>";'."\n";
            echo "\n";

            echo '                           if(players.length > 1) code += "   <tr> <td colspan=\"4\" align=\"center\"><a href=\"#\" onclick=\"return js_startgame();\"><em>Start Game</em></a></td> </tr>\n";'."\n";
         }
         echo "\n";
         echo "\n";
         echo '                           code += "</table>\n";'."\n";
         echo '                        }else'."\n";
         echo '                           window.location.href="?";'."\n";
         echo "\n";
         echo '                        document.getElementById("players").innerHTML = code;'."\n";
         echo '                     }'."\n";
         echo "\n";
         if($host){
            echo '                     function js_startgame(){'."\n";
            echo '                        var i;'."\n";
            echo '                        var turns = new Array();'."\n";
            echo "\n";
            echo '                        for(i=0; i<row; i++){'."\n";
            echo '                           var tmp = parseInt(document.getElementById(\'player:\'+i+\'-turn\').value);'."\n";
            echo '                           if(turns.indexOf(tmp-1) != -1){'."\n";
            echo '                              alert(\'Only one player can play \'+(tmp==1? \'1st\' : (tmp==2? \'2nd\' : (tmp==3? \'3rd\' : tmp+\'th\')))+\'!\');'."\n";
            echo '                              return false;'."\n";
            echo '                           }else'."\n";
            echo '                              turns.push(tmp-1);'."\n";
            echo '                        }'."\n";
            echo "\n";
            echo '                        var str_turns = \'t[]=\'+(turns.join(\'&t[]=\'));'."\n";
            echo "\n";
            echo '                        window.onunload=\'\';'."\n";
            echo '                        window.location.href=\'?start&\'+str_turns;'."\n";
            echo '                     }'."\n";
            echo "\n";
         }
         echo "\n";
         echo '                     setInterval("call(\'?players\', displayPlayers);", 1000);'."\n";
         echo '                                  call(\'?players\', displayPlayers); //Make an initial call'."\n";
         echo '                     setInterval("call(\'?status'.($host? "" : "=notHost").'\', function(t){ if(t==\"game started\" || t==\"new host\" || t==\"dead\"){window.onunload=\'\'; window.location.href=\'?\';} });", 1000);'."\n";
         echo '                  </script>'."\n";
         echo '               </td>'."\n";
         echo '            </tr>'."\n";
         echo '         </table>'."\n";
         echo "\n";
         echo '      </td>'."\n";
         echo '   </tr>'."\n";
         echo '</table>'."\n";
         echo "\n";
         echo '</body>'."\n";
         goto end;
      }
   }
   //Game - Lobby
   lobby:{
      echo '<head>'."\n";
      echo '   <title>Sea Chess Unbound</title>'."\n";
      echo "\n";
      echo '   <script type="text/javascript" src="?js=call"></script>'."\n";
      echo "\n";
      echo '   <script type="text/javascript">'."\n";
      echo '      function createGame(){'."\n";
      echo '         var gamename = "'. ((isset($_COOKIE['gamename']))? $_COOKIE['gamename'] : "New Game"). '";'."\n";
      echo '         var rules    = "'. ((isset($_COOKIE['rules']))?    $_COOKIE['rules']    : "<3")      . '";'."\n";
      echo "\n";
      echo '         window.location.href= "?create&game="+gamename+"&rules="+rules;'."\n";
      echo '      }'."\n";
      echo '   </script>'."\n";
      echo '</head>'."\n";
      echo '<body onunload="call(\'?clear\', true);">'."\n";
      echo "\n";
      echo 'Welcome '.$_COOKIE['username']."\n";
      echo '<br /><a href="?logout=http://localhost/"><small>Logout</small></a>'."\n";
      echo "\n";
      echo '<table width="100%" style="margin-top: 50px;">'."\n";
      echo '   <tr>'."\n";
      echo '      <td width="80%" align="center">'."\n";
      echo "\n";
      echo '         <table rules="all" cellpadding="20">'."\n";
      echo '            <tr>'."\n";
      echo '               <td colspan="2" align="center">'."\n";
      echo '                  <input type="button" value="Create Game" onclick="createGame();" />'."\n";
      echo '               </td>'."\n";
      echo '            </tr>'."\n";
      echo '            <tr>'."\n";
      echo '               <!-- Available Games -->'."\n";
      echo '               <td align="center" valign="top" width="50%">'."\n";
      echo '                  These are the curretly available games that<br /> you may <b>join and play</b>:'."\n";
      echo '                  <br /><br />'."\n";
      echo '                  <div id="availableGames"></div>'."\n";
      echo "\n";
      echo '                  <script type="text/javascript">'."\n";
      echo '                     q_ag = true; //Query:AvailableGames'."\n";
      echo '                     function displayAvailableGames(t){'."\n";
      echo '                        if(t == q_ag) return true;'."\n";
      echo '                        q_ag = t;'."\n";
      echo "\n";
      echo '                        if(t!=""){'."\n";
      echo '                           code  = "<table width=\"100%\" cellspacing=\"20\">\n";'."\n";
      echo '                           code += "   <tr>\n";'."\n";
      echo '                           code += "      <td align=\'left\'> <strong>Game Name</strong></td>\n";'."\n";
      echo '                           code += "      <td align=\'center\'><strong>Players</strong></td>\n";'."\n";
      echo '                           code += "      <td></td>\n";'."\n";
      echo '                           code += "   </tr>\n";'."\n";
      echo "\n";
      echo '                           games = t.split("\n");'."\n";
      echo '                           var i,j;'."\n";
      echo '                           for(i=0; i<games.length; i++){'."\n";
      echo '                              var tmp      = games[i].split("/");'."\n";
      echo '                              var gamename = tmp[0];'."\n";
      echo '                              var players  = tmp[1].split(",");'."\n";
      echo "\n";
      echo '                              code += "   <tr>\n";'."\n";
      echo '                              code += "      <td>"+gamename+"</td>\n";'."\n";
      echo '                              code += "      <td>";'."\n";
      echo '                              for(j=0; j<players.length; j++){'."\n";
      echo '                                 code += (j>0? " , " : "");'."\n";
      echo '                                 code += players[j].split("(")[1].split(")")[0];'."\n";
      echo '                              }'."\n";
      echo '                              code += "      </td>\n";'."\n";
      echo '                              code += "      <td> <a href=\"?join="+(players[0].split("(")[0])+"\">Join</a> </td>\n";'."\n";
      echo '                              code += "   </tr>\n";'."\n";
      echo '                           }'."\n";
      echo '                           code += "</table>\n";'."\n";
      echo '                        }else'."\n";
      echo '                           code = "Currently none";'."\n";
      echo "\n";
      echo '                        document.getElementById("availableGames").innerHTML = code;'."\n";
      echo '                     }'."\n";
      echo "\n"; ////D Make dynamic with a default query delay of 5000
      echo '                     setInterval("call(\'?availableGames\', displayAvailableGames);", 1000);'."\n";
      echo '                                  call(\'?availableGames\', displayAvailableGames); // Make the initial call'."\n";
      echo '                  </script>'."\n";
      echo '               </td>'."\n";
      echo "\n";
      echo '               <td align="center" valign="top" width="50%">'."\n";
      echo '                  These are the games that<br /> you can <b>observe</b>:'."\n";
      echo '                  <br /><br />'."\n";
      echo '                  <div id="playedGames"></div>'."\n";
      echo "\n";
      echo '                  <script type="text/javascript">'."\n";
      echo '                     q_pg = true; //Query:PlayedGames'."\n";
      echo "\n";
      echo '                     function displayPlayedGames(t){'."\n";
      echo '                        if(t == q_pg) return true;'."\n";
      echo '                        q_pg = t;'."\n";
      echo "\n";
      echo '                        if(t!=""){'."\n";
      echo '                           code  = "<table width=\"100%\" cellspacing=\"20\">\n";'."\n";
      echo '                           code += "   <tr>\n";'."\n";
      echo '                           code += "      <td align=\'left\'> <strong>Game Name</strong></td>\n";'."\n";
      echo '                           code += "      <td align=\'center\'><strong>Players</strong></td>\n";'."\n";
      echo '                           code += "      <td></td>\n";'."\n";
      echo '                           code += "   </tr>\n";'."\n";
      echo "\n";
      echo '                           games = t.split("\n");'."\n";
      echo '                           var i,j;'."\n";
      echo '                           for(i=0; i<games.length; i++){'."\n";
      echo '                              var tmp      = games[i].split("/");'."\n";
      echo '                              var gamename = tmp[0];'."\n";
      echo '                              var players  = tmp[1].split(",");'."\n";
      echo "\n";
      echo '                              code += "   <tr>\n";'."\n";
      echo '                              code += "      <td>"+gamename+"</td>\n";'."\n";
      echo '                              code += "      <td>";'."\n";
      echo '                              for(j=0; j<players.length; j++){'."\n";
      echo '                                 code += (j>0? " vs " : "");'."\n";
      echo '                                 code += players[j].split("(")[1].split(")")[0];'."\n";
      echo '                              }'."\n";
      echo '                              code += "      </td>\n";'."\n";
      echo '                              code += "      <td> <a href=\"?observe="+(players[0].split("(")[0])+"\">Observe</a> </td>\n";'."\n";
      echo '                              code += "   </tr>\n";'."\n";
      echo '                           }'."\n";
      echo '                           code += "</table>\n";'."\n";
      echo '                        }else'."\n";
      echo '                           code = "Currently none";'."\n";
      echo "\n";
      echo '                        document.getElementById("playedGames").innerHTML = code;'."\n";
      echo '                     }'."\n";
      echo "\n"; ////D Make dynamic with a default query delay of 5000
      echo '                     setInterval("call(\'?playedGames\', displayPlayedGames);", 1000);'."\n";
      echo '                                  call(\'?playedGames\', displayPlayedGames); // Make the initial call'."\n";
      echo '                  </script>'."\n";
      echo '               </td>'."\n";
      echo '            </tr>'."\n";
      echo '         </table>'."\n";
      echo "\n";
      echo '      </td>'."\n";
      echo '   </tr>'."\n";
      echo '</table>'."\n";
      echo "\n";
      echo '</body>'."\n";
      goto end;
   }
}

end:{
   echo '</html>'."\n";
}
?>