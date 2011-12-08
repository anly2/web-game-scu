<?php

if(isset($_REQUEST['php_avs'])){
   $marks = array("X", "O");
   $moves = array_flip(explode(",", $_REQUEST['m']));

   foreach($moves as $id=>$n){
      $moves[$id] = $marks[ ($n % count($marks)) ];
   }


class player{
   public $turn, $mark, $moves = array();

   static $vMarks = 'marks', $vMoves = 'moves';
   function __construct($m){
      $this->mark  = $m;
      $this->turn  =  array_search($m, $GLOBALS[self::$vMarks]);

      foreach($GLOBALS[self::$vMoves] as $id=>$mark)
         if($mark == $m)
            $this->moves[] = $id; // = new cell($id);

      self::$players[] = $this;
   }

   private static  $players = array();
   public  static  function count(){ return count(self::$players); }
   public function enemy($step=1){
      return self::$players[ ($this->turn + $step) % self::count() ];
   }

   public function longest($minLength = 1){
      $lines = array();
      foreach($this->moves as $id){
         $cell = new cell($id);
         $long = $cell->longest($minLength);

         $lines += $long;
         if(current($long))
            $minLength = current($long)->length;
      }
      return array_filter($lines, create_function('$line', 'return ($line->length) >= '.$minLength.';') );
   }
}
foreach(${player::$vMarks} as $m)
   $players[] = new player($m);


class line{
   public $valid, $owner, $blocked = false, $locked = false, $length;
   public $ends = array(), $cells = array(), $gap, $next, $prev;
   private $end, $end_, $incEmpty, $gapPending = false, $stepX, $stepY;

   function __construct($end, $end_, $incEmpty = false){
      if(is_a($end , 'cell')) $end  = $end->id;
      if(is_a($end_, 'cell')) $end_ = $end_->id;
      if( (!is_string($end)) || (!is_string($end_)) ) return false;

      if(!self::collinear($end, $end_)){
         $this->valid = false;
         return false;
      }else
         $this->valid = true;

      $this->end = $end; $this->end_ = $end_;
      $this->ends  = array(new cell($end), new cell($end_));
      $this->owner = self::owner($end, $end_);
      $this->incEmpty = $incEmpty;

         list($x , $y ) = explode(":", $end );
         list($x_, $y_) = explode(":", $end_);

      $this->stepX = ($x>$x_)? -1 : 1;
      $this->stepY = ($y>$y_)? -1 : 1;

      $this->cicle();
      $this->limes();

      if(!is_null($this->next->owner) && $this->next->owner != $this->owner)
         $this->blocked = true;

      if(!is_null($this->prev->owner) && $this->prev->owner != $this->owner){
         if($this->blocked)
            $this->locked = true;
         else
            $this->blocked = true;
      }

      $this->length  = count($this->cells);
   }

   static function collinear($a, $b, $c = false){
      if(!$c) $c = $a;

      list($x1, $y1) = explode(":", $a);
      list($x2, $y2) = explode(":", $b);
      list($x3, $y3) = explode(":", $c);

      return ($y1 - $y2) * ($x1 - $x3) == ($y1 - $y3) * ($x1 - $x2);
   }
   static function owner(){
      $sample = func_get_args();
      $owner  = NULL;

      foreach($sample as $v){
         if(!is_a($v, 'cell'))
            $v = new cell( (string)$v );

         if(!is_null($v->owner))
            return $v->owner;
      }
      return $owner;
   }

   public function cicle(){
      list($x , $y ) = explode(":", $this->end );
      list($x_, $y_) = explode(":", $this->end_);

      for($i=0; $i<=abs($x-$x_); $i++){
         $_x = $x + ($i*($this->stepX));

         for($j=0; $j<=abs($y-$y_); $j++){
            $_y = $y + ($j*($this->stepY));


            if(self::collinear($_x.":".$_y, $this->end, $this->end_)){
               $cell = new cell($_x.":".$_y);

               if(!is_null($this->owner)){
                  if(!is_null($cell->owner)){
                     if($cell->owner != $this->owner)
                        return false;
                  }else{
                     if(!is_null($this->gap)) //If a gap had already been encountered
                        return false;

                     $this->gapPending = $cell;
                  }
               }

               if(is_null($this->owner) || $cell->owner == $this->owner){
                  if($this->gapPending){
                     $this->gap = $this->gapPending;
                     $this->gapPending = false;
                  }

                  $this->cells[] = $cell;
               }else

               if($this->incEmpty && is_null($cell->owner))
                  $this->cells[] = $cell;
            }
         }
      }
      return true;
   }
   public function limes(){
      //if(is_null($this->prev) || is_null($this->next)){
         list($x , $y ) = explode(":", $this->end );
         list($x_, $y_) = explode(":", $this->end_);

         $prevX = reset($this->cells)->x - (($x==$x_)? 0 : $this->stepX);
         $prevY = reset($this->cells)->y - (($y==$y_)? 0 : $this->stepY);
         $this->prev = new cell($prevX.":".$prevY);

         $nextX = end($this->cells)->x + (($x==$x_)? 0 : $this->stepX);
         $nextY = end($this->cells)->y + (($y==$y_)? 0 : $this->stepY);
         $this->next = new cell($nextX.":".$nextY);
      //}
      return array($this->prev, $this->next);
   }

   public function getLength(){
      $this->length = count($this->cells);
      return $this->length;
   }

   public function clearEmpty(){
      for($i=0; $i < count($this->cells); $i++)
         if(is_null($this->cells[$i]->owner)){
            array_splice($this->cells, $i--, 1);
            $this->length = count($this->cells);
         }
   }

   public function same($as){
      if(!is_a($as, 'line')) return false;

      $cells = $this->cells;
      foreach($this->cells as $v)
         if(!in_array($v, $as->cells))
            return false;
      return true;
   }
}

class cell{
   public $id, $x, $y, $owner;

   function __construct($id){
      if(!is_string($id)) return false;

      $this->id = $id;
      list($this->x, $this->y) = explode(":", $id);

      if(isset($GLOBALS[player::$vMoves][$id])){
         $this->mark  = $GLOBALS[player::$vMoves][$id];

         //Careful if is_null gives wrong result for object:player
         foreach($GLOBALS['players'] as $t)
            if($t->mark == $this->mark)
               $this->owner = $t;
      }
   }

   public function openedCorridors($reach = 0){
      $arr  = array();
      $dirs = array();
      $dirs[] = array( 'dir'=>"left",       'x'=>(-$reach-1) , 'y'=>(0)         );
      $dirs[] = array( 'dir'=>"up left",    'x'=>(-$reach-1) , 'y'=>($reach+1)  );
      $dirs[] = array( 'dir'=>"up",         'x'=>(0)         , 'y'=>($reach+1)  );
      $dirs[] = array( 'dir'=>"up right",   'x'=>( $reach+1) , 'y'=>($reach+1)  );
      $dirs[] = array( 'dir'=>"right",      'x'=>( $reach+1) , 'y'=>(0)         );
      $dirs[] = array( 'dir'=>"down right", 'x'=>( $reach+1) , 'y'=>(-$reach-1) );
      $dirs[] = array( 'dir'=>"down",       'x'=>(0)         , 'y'=>(-$reach-1) );
      $dirs[] = array( 'dir'=>"down left",  'x'=>(-$reach-1) , 'y'=>(-$reach-1) );

      foreach($dirs as $dir){
         $vector = new line($this->id, ( ($this->x + $dir['x']) .":". ($this->y + $dir['y']) ), true );

         if($vector->length > $reach+1)
            $arr[] = $vector;
      }

      return $arr;
   }

   public function longest($minLength=0){
      $arr = array();
      $longest_length = $minLength;

      $dirs = array();
      $dirs[] = array( 'dir'=>"left",       'x'=> -4, 'y'=> 0,  'op'=>"right" );
      $dirs[] = array( 'dir'=>"up left",    'x'=> -4, 'y'=> 4,  'op'=>"down right" );
      $dirs[] = array( 'dir'=>"up",         'x'=> 0,  'y'=> 4,  'op'=>"down" );
      $dirs[] = array( 'dir'=>"up right",   'x'=> 4,  'y'=> 4,  'op'=>"down left" );
      $dirs[] = array( 'dir'=>"right",      'x'=> 4,  'y'=> 0,  'op'=>"left" );
      $dirs[] = array( 'dir'=>"down right", 'x'=> 4,  'y'=> -4, 'op'=>"up left" );
      $dirs[] = array( 'dir'=>"down",       'x'=> 0,  'y'=> -4, 'op'=>"up" );
      $dirs[] = array( 'dir'=>"down left",  'x'=> -4, 'y'=> -4, 'op'=>"up right" );

      foreach($dirs as $k=>$dir){
         $vector = new line($this->id, ( ($this->x + $dir['x']) .":". ($this->y + $dir['y']) ) );

         $longest_length = max($longest_length, $vector->length);
         $dirs[$k]['index'] = count($arr);
         $arr[] = $vector;

         $opposite = $dir['op'];
         foreach($dirs as $t)
            if($t['dir'] == $opposite && isset($t['index']))
               $opposite = $arr[$t['index']];

         if(!is_string($opposite)){
            $merged = new line(end($vector->cells)->id, end($opposite->cells)->id);

            $longest_length = max($longest_length, $merged->length);

            $arr[] = $merged;
            //Reversed of Normal Lines are not included, so why should Reversed of Merged be?
            //$arr[] = new line($merged->ends[1]->id, $merged->ends[0]->id);//Include the Identical line but with opposite direction
         }
      }

      //Preserves keys: $arr = array_filter($arr, create_function('$item', 'return $item->length >= '.$longest_length.';'));
      $i=0;
      foreach($arr as $ln){
         if($ln->length < $longest_length)
            array_splice($arr, $i, 1);
         else
            $i++;
      }

      return $arr;
   }

   public function isOf($obj){
      if(is_a($obj, 'player'))
         return ($obj == $this->owner);

      if(is_a($obj, 'line')){
         foreach($obj->cells as $t)
            if($t->id == $this->id)
               return true;
         return false;
      }

      if(is_a($obj, 'cell'))
         return ($obj->id == $this->id);

      return NULL; //Incomparable
   }

   public function sees($what, $searchRange = 5){
      $dirs = array();
      $dirs[] = array( 'dir'=>"left",       'x'=> -1, 'y'=> 0  );
      $dirs[] = array( 'dir'=>"up left",    'x'=> -1, 'y'=> 1  );
      $dirs[] = array( 'dir'=>"up",         'x'=> 0,  'y'=> 1  );
      $dirs[] = array( 'dir'=>"up right",   'x'=> 1,  'y'=> 1  );
      $dirs[] = array( 'dir'=>"right",      'x'=> 1,  'y'=> 0  );
      $dirs[] = array( 'dir'=>"down right", 'x'=> 1,  'y'=> -1 );
      $dirs[] = array( 'dir'=>"down",       'x'=> 0,  'y'=> -1 );
      $dirs[] = array( 'dir'=>"down left",  'x'=> -1, 'y'=> -1 );

      foreach($dirs as $dir){
         $x_ = ($this->x + $dir['x']);
         $y_ = ($this->y + $dir['y']);

         for($i=0; $i<$searchRange; $i++){
            $t = new cell( $x_.":".$y_ );

            if($t->isOf($what))
               return true;

            if(!is_null($t->owner))
               break;

            $x_ += $dir['x'];
            $y_ += $dir['y'];
         }
      }

      return false;
   }

   public function adj($to){
      return $this->sees($to, 1);
   }
}



define('all',        0, true);
define('aggressive', 1, true);
define('defensive',  2, true);
define('mixed',      3, true);

function rate($cell, $player, $r=0){
   $aggr = $def = $mixed = 0;

   if(!is_string($cell))
      if(isset($cell->id))
         $cell = $cell->id;
      else
         return false;

   // Get the Enemy's Longest and Not Blocked (aka dangerous) lines
      //Performance leak! This only needs to be executed once, not for every available rated
   $elnb = $player->enemy()->longest(3);

   foreach($elnb as $k=>$v){
      if(($v->length == 3) && (!$v->blocked))
         continue;

      if(($v->length == 4) && (!$v->locked) )
         continue;

      array_splice($elnb, $k, 1);
   }

   // Apply ownership for further rating
   $GLOBALS['moves'][$cell] = $player->mark;
   $rated = new cell($cell);


   //Survival
      ////Length is double because of directional difference for identical lines

   //If there are more than 1 dangerous lines
   if(count($elnb)>(1*2)){
      // Play aggressive

      // Add points only if rated is part of a line longer than 3 (enemy needs to be block)
      if(count($rated->longest(4))>0)
         $aggr += 5;
   }else
   //If there is only 1 dangerous line
   if(count($elnb)==(1*2)){
      // Play defensive

      // Add points if rated is blocking that dangerous line
      if($elnb[0]->next->id == $rated->id  ||  $elnb[0]->prev->id == $rated->id || (!is_null($elnb[0]->gap) && $elnb[0]->gap->id == $rated->id))
         $def += 7;
   }


   //Aggressive

   // Add 5 points if this will finish the opponent
   if(count($rated->longest(5))>0)
      $aggr += 5;

   // Add a point for each marked cell that combines with rated and the line is not blocked
   $arr = $rated->openedCorridors(2);
   foreach($arr as $v){
      $v->clearEmpty();
      $aggr += floor($v->length /2);
   }

   // Add a point for each open corridor (has potential)
   $aggr += floor(count($rated->openedCorridors())/2);

   // Add two extra points if rated forms a dangerous line (unblocked line longer than 2 cells)
   if(count($rated->longest(3))>0)
      $aggr += 2;


   //Defensive

   // Add a point if an enemy cell is seen (blocks the corridor / can eventually block)
   if($rated->sees($player->enemy()))
      $def  += 1;

   // Add a point if an enemy cell is adjacent to the rated
   if($rated->adj($player->enemy()))
      $def  += 1;

   // Add a point if it blocks a long but not dangerous line (dangerous lines are handled in Survival
      $collection = array();
      $arr = $player->enemy()->longest();
      foreach($arr as $t)
         if($t->length < 3){
            $collection[] = $t->next->id;
            $collection[] = $t->prev->id;
         }
   $def += (int) in_array($rated->id, $collection);


   //Mixed

   // Mix the two values. A more complex formula can be used if needed
   $mixed = $aggr + $def;


   //Remove the temporal ownership
   unset($GLOBALS['moves'][$cell]);


   if($r==0 || $r==1) $rating[] = $aggr;
   if($r==0 || $r==2) $rating[] = $def;
   if($r==0 || $r==3) $rating[] = $mixed;
   if(!isset($rating)) return false;
   if(count($rating)==1) $rating = $rating[0];
   return $rating;
}


   //Collect Availables
   $avs = array();
   foreach($moves as $id=>$mark)
      for($i=-1;$i<=1;$i++)
         for($j=-1;$j<=1;$j++){
            list($x, $y) = explode(":", $id);

            $t = ($x+$i).":".($y+$j);
            if(!isset($moves[$t]) && !in_array($t, $avs))
               $avs[] = $t;
         }

   //Rate every Available
   foreach($avs as $k=>$a)
      $arr[$a] = rate($a, $players[ ((count(explode(",", $_REQUEST['m'])))%count($marks)) ],MIXED);

   //Sort them by highest rating
   arsort($arr, SORT_NUMERIC);

   //Fetch only the Top Rated
   $topRated = array();
   foreach($arr as $id=>$r)
      if($r >= reset($arr))
         $topRated[] = $id;

   echo $topRated[array_rand($topRated)];
   exit;
}
?>
<html>
<head>
   <title>Sea Chess Unbound</title>
   <script type="text/javascript">
   curTurn = 1;
   turns = 0;
   gameOver = false;
   lastPlayed = false;

function mark(elem, myAttempt){
   //If the game is over or is not your turn, stop
   if(gameOver) return false;
   //if(myAttempt && curTurn != myTurn) return false;
   //if(myAttempt && inProgress) return false;

   //Get the element to mark
   if(typeof elem == "string") elem = document.getElementById(elem);

   // If not in progress and call did not returned "Success", stop
   //if(!(inProgress || call("?mark="+elem.id, true) == "Success")) return false;

   // Maintain the Game Grid to proper size
   maintenance(elem);

   //Display adjacent cells where needed
   // i==(xOffset), j==(yOffset)
   var i;
   for(i=-1;i<=1;i++)
      for(j=-1;j<=1;j++){
         var cell = document.getElementById( (x(elem.id)-(-i))+":"+(y(elem.id)-(-j)) );

         if((cell.className.indexOf("available")!=-1) || (cell.className.indexOf("marked")!=-1))
            continue;

         cell.className += " available";
         cell.setAttribute("onclick",     "mark(this, true);");
         cell.setAttribute("onmouseover", "highlight(this);");
         cell.setAttribute("onmouseout",  "unhighlight(this);");
      }

   //Get the mark before turns shift and the wrong mark is fetched
   var m = marks[curTurn-1];

   //Adorn the marked cell
   elem.innerHTML  = m;
   elem.className += " player"+curTurn;

   //Disable Element's further marking
   elem.setAttribute("onclick", "");
   elem.setAttribute("onmouseover", "");
   elem.setAttribute("onmouseout", "");
   elem.className = elem.className.split(" available").join(" marked");

   //Shift turns
   curTurn = (curTurn % marks.length)-(-1);
   turns++;

   //Mark the Last Played Move
   if(lastPlayed)
      lastPlayed.className = lastPlayed.className.split(" last").join("");
   elem.className += " last";
   lastPlayed = elem;

   //Announce Turns
   msg =  "<b>"+marks[curTurn-1]+"</b>'s turn";
   document.getElementById("notice").innerHTML = msg;

   //Check for victory
   if(winCheck(elem.id, m)){
      gameOver = true;
      document.getElementById("notice").innerHTML = "<big style=\"color:darkblue;\"><b>"+m+"</b> Won!</big>";
   }
   return true;
}
   </script>
   <script type="text/javascript">
      topMost    = "0:0";
      leftMost   = "0:0";
      rightMost  = "0:0";
      bottomMost = "0:0";

function maintenance(elem){
   var table = document.getElementById("game:grid");

      se  = document.getElementById("scroll:enable");  // se  /Scroll:Enable/
      sbx = -20;                                       // sbx /Scroll  By (X)/
      ebx = 40;                                        // ebx /Enlarge By (X)/
      sby = -12;                                       // sby /Scroll By (Y)/

  //If the Cell clicked is:
   //The Last Cell on the Left
   if(x(elem.id)==x(leftMost)){
      var i;
      for(i=0;i<table.rows.length;i++){
         var cell = document.createElement("td");

         cell.className = "game cell";
         cell.id = (x(leftMost)-1)+":"+(y(topMost)-i);
            cell.setAttribute('title', cell.id);

         document.getElementById(":"+(y(topMost)-i)).insertBefore(cell, document.getElementById(":"+(y(topMost)-i)).firstChild);
      }

      leftMost = cell.id;

      if(se){
         se.style.width = parseInt(se.offsetWidth) + sbx;
         window.scrollBy(sbx, 0);
         table.style.width = parseInt(table.offsetWidth) + sbx + ebx;
      }
   }

   //The Last Cell on the Right
   if(x(elem.id)==x(rightMost)){
      var i;
      for(i=0;i<table.rows.length;i++){
         var cell = document.createElement("td");

         cell.className = "game cell";
         cell.id = (x(rightMost)-(-1))+":"+(y(topMost)-i);
            cell.setAttribute('title', cell.id);

         document.getElementById(":"+(y(topMost)-i)).appendChild(cell);
      }

      rightMost = cell.id;

      if(se){
         se.style.width = parseInt(se.offsetWidth) + sbx;
         window.scrollBy(sbx, 0);
         table.style.width = parseInt(table.offsetWidth) + sbx + ebx;
      }
   }

   //The Last Cell on the Top
   if(y(elem.id)==y(topMost)){
      var cells = table.rows[0].cells.length;

      var row = document.createElement("tr");
      row.id = ":"+(y(topMost)-(-1));
      table.insertBefore(row, table.firstChild);

      var i;
      for(i=0; i<cells; i++){
         var cell = document.createElement("td");

         cell.className = "game cell";
         cell.id = (x(leftMost)-(-i))+":"+(y(topMost)-(-1));
            cell.setAttribute('title', cell.id);

         row.appendChild(cell);
      }

      topMost = cell.id;

      if(se){
         se.style.height = parseInt(se.offsetHeight) + sby;
         window.scrollBy(0, sby);
      }
   }

   //The Last Cell on the Bottom
   if(y(elem.id)==y(bottomMost)){
      var cells = table.rows[0].cells.length;

      var row = document.createElement("tr");
      row.id = ":"+(y(bottomMost)-1);
      table.appendChild(row);

      var i;
      for(i=0; i<cells; i++){
         var cell = document.createElement("td");

         cell.className = "game cell";
         cell.id = (x(leftMost)-(-i))+":"+(y(bottomMost)-1);
            cell.setAttribute('title', cell.id);

         row.appendChild(cell);
      }

      bottomMost = cell.id;

      if(se){
         se.style.height = parseInt(se.offsetHeight) + sby;
         window.scrollBy(0, sby);
      }
   }
}

   </script>
   <script type="text/javascript">
function x(coordinates){
   return parseInt(coordinates.split(":")[0]);
}

function y(coordinates){
   return parseInt(coordinates.split(":")[1]);
}


function getPosition(obj) {
   var curleft = curtop = 0;

   if (obj.offsetParent)
      do {
         curleft += obj.offsetLeft;
         curtop += obj.offsetTop;
      }while (obj = obj.offsetParent);

   return [curleft,curtop];
}

   </script>
   <script type="text/javascript">
function highlight(elem){
   if(gameOver) return;

   if(elem.className.indexOf("available")!=-1)
      elem.innerHTML=" "+marks[curTurn-1]+" ";
}

function unhighlight(elem){
   if(gameOver) return;

   if(elem.className.indexOf("available")!=-1)
      elem.innerHTML = elem.innerHTML.split(" "+marks[curTurn-1]+" ").join("");
}
   </script>
   <script type="text/javascript">
function winCheck(elem, mark){
   //Check Horisontal Axis
      x_ = x(elem);
      for(c=0; c<4; c++)
         if(document.getElementById((--x_)+":"+y(elem)).innerHTML!=mark)
            break;
      if(c==4)
         return true;

      x_ = x(elem);
      for(d=0; d<4; d++)
         if(document.getElementById((++x_)+":"+y(elem)).innerHTML!=mark)
            break;
      if(d==4)
         return true;

      if(c-(-d)==4)
         return true;

   //Check Vertical Axis
      y_ = y(elem);
      for(c=0; c<4; c++)
         if(document.getElementById(x(elem)+":"+(--y_)).innerHTML!=mark)
            break;
      if(c==4)
         return true;

      y_ = y(elem);
      for(d=0; d<4; d++)
         if(document.getElementById(x(elem)+":"+(++y_)).innerHTML!=mark)
            break;
      if(d==4)
         return true;

      if(c-(-d)==4)
         return true;

   //Check  \ diagonal
      x_ = x(elem); y_ = y(elem);
      for(c=0; c<4; c++)
         if(document.getElementById((--x_)+":"+(--y_)).innerHTML!=mark)
            break;
      if(c==4)
         return true;

      x_ = x(elem); y_ = y(elem);
      for(d=0; d<4; d++)
         if(document.getElementById((++x_)+":"+(++y_)).innerHTML!=mark)
            break;
      if(d==4)
         return true;

      if(c-(-d)==4)
         return true;

   //Check  / diagonal
      x_ = x(elem); y_ = y(elem);
      for(c=0; c<4; c++)
         if(document.getElementById((--x_)+":"+(++y_)).innerHTML!=mark)
            break;
      if(c==4)
         return true;

      x_ = x(elem); y_ = y(elem);
      for(d=0; d<4; d++)
         if(document.getElementById((++x_)+":"+(--y_)).innerHTML!=mark)
            break;
      if(d==4)
         return true;

      if(c-(-d)==4)
         return true;

   return false;
}
   </script>
   <script type="text/javascript">
      marks = new Array("X", "O");
      myTurn = 1;

      //Make the initial call
      window.onload = function(){
<?php
   if(isset($_REQUEST['m'])){
      $e = explode(",", $_REQUEST['m']);
      foreach($e as $id)
         echo '            mark("'.$id.'");'."\n";
   }else
         echo '            mark("0:0");'."\n";

   if(isset($_REQUEST['play']))
         echo '            dev_play('.((isset($_REQUEST['rate']))?'true':'').');'."\n";
   else
      if(isset($_REQUEST['rate']))
         echo '            dev_rate_available(true);'."\n";
?>
      }
   </script>

   <script type="text/javascript">
   //class player {enemy(), longest, moves[]}
   //class line   {length, cells[], ends[], blocked, locked, identical()}
   //class cell   {openedCorridors(), sees(), adj(), owner, x,y ,id}
   //Array::each{}

      //Class: player
      function player(m){
         this.turn  = marks.indexOf(m);
         this.mark  = m;
         this.moves = new Array();

         this.enemy = function(){
            return players[((this.turn-(-1))%marks.length)];
         };

         this.longest = function(incLocked){
            if(!incLocked) incLocked = false;
            var arr = new Array(); //So that .length is defined
            var longest_length   = 0;
            var i;
            for(i=0; i<this.moves.length; i++){

               var toDownLeft  = new line(this.moves[i].id, (((this.moves[i].x)-4)+":"+((this.moves[i].y)-4)) );
               if(toDownLeft.length >= longest_length){
                  //if(arr.every( function(item){ return !toDownLeft.identical(item); } ))
                  if(incLocked || !toDownLeft.locked){
                     arr.push(toDownLeft);
                     longest_length = toDownLeft.length;
                  }
               }

               var toDown      = new line(this.moves[i].id, ((this.moves[i].x)+":"+((this.moves[i].y)-4)) );
               if(toDown.length >= longest_length){
                  //if(arr.every( function(item){ return !toDown.identical(item); } ))
                  if(incLocked || !toDown.locked){
                     arr.push(toDown);
                     longest_length = toDown.length;
                  }
               }

               var toDownRight = new line(this.moves[i].id, (((this.moves[i].x)-(-4))+":"+((this.moves[i].y)-4)) );
               if(toDownRight.length >= longest_length){
                  //if(arr.every( function(item){ return !toDownRight.identical(item); } ))
                  if(incLocked || !toDownRight.locked){
                     arr.push(toDownRight);
                     longest_length = toDownRight.length;
                  }
               }

               var toUpLeft  = new line(this.moves[i].id, (((this.moves[i].x)-4)+":"+((this.moves[i].y)-(-4))) );
               if(toUpLeft.length >= longest_length){
                  //if(arr.every( function(item){ return !toUpLeft.identical(item); } ))
                  if(incLocked || !toUpLeft.locked){
                     arr.push(toUpLeft);
                     longest_length = toUpLeft.length;
                  }
               }

               var toUp      = new line(this.moves[i].id, ((this.moves[i].x)+":"+((this.moves[i].y)-(-4))) );
               if(toUp.length >= longest_length){
                  //if(arr.every( function(item){ return !toUp.identical(item); } ))
                  if(incLocked || !toUp.locked){
                     arr.push(toUp);
                     longest_length = toUp.length;
                  }
               }

               var toUpRight = new line(this.moves[i].id, (((this.moves[i].x)-(-4))+":"+((this.moves[i].y)-(-4))) );
               if(toUpRight.length >= longest_length){
                  //if(arr.every( function(item){ return !toUpRight.identical(item); } ))
                  if(incLocked || !toUpRight.locked){
                     arr.push(toUpRight);
                     longest_length = toUpRight.length;
                  }
               }

               var toLeft      = new line(this.moves[i].id, (((this.moves[i].x)-4)+":"+(this.moves[i].y)) );
               if(toLeft.length >= longest_length){
                  //if(arr.every( function(item){ return !toLeft.identical(item); } ))
                  if(incLocked || !toLeft.locked){
                     arr.push(toLeft);
                     longest_length = toLeft.length;
                  }
               }

               var toRight = new line(this.moves[i].id, (((this.moves[i].x)-(-4))+":"+(this.moves[i].y)) );
               if(toRight.length >= longest_length){
                  //if(arr.every( function(item){ return !toRight.identical(item); } ))
                  if(incLocked || !toRight.locked){
                     arr.push(toRight);
                     longest_length = toRight.length;
                  }
               }
            }
            var t = arr.each( function(item){ return item.length>=longest_length; } ,true);
            return t;
         };
      }
      //Additional to class:player
      {
         var players = new Array();
         var i;
         for(i=0; i<marks.length; i++)
            players[i] = new player(marks[i]);

         mark_old = mark;
         mark = function(elem, myAttempt){
            if(mark_old(elem, myAttempt)){
               players[marks.length - curTurn].moves.push(new cell(elem));
               return true;
            }else
               return false;
         };
      }
      //End of Class:player


      //Class: line
      function line(end, end_, incEmpty){
         if(end.id)  end  = end.id;
         if(end_.id) end_ = end_.id;

         var valid = false;
         if(x(end) == x(end_))
             valid = true;
         if(y(end) == y(end_))
             valid = true;
         if(Math.abs(x(end)-x(end_)) == Math.abs(y(end)-y(end_)))
             valid = true;
         if(!valid)
            return false;


         this.ends  = [new cell(end), new cell(end_)];


         this.owner_check = true;
         if(this.ends[0].owner == undefined)
            if(this.ends[1].owner == undefined)
               this.owner_check = false;
            else
               this.owner = this.ends[1].owner;
         else
            this.owner = this.ends[0].owner;

         this.cells = new Array();
         this.gaps  = new Array();
         var potential_gap = false;

         var x_step = (x(end)>x(end_))? -1 : 1;
         var y_step = (y(end)>y(end_))? -1 : 1;
         var i;

         lineCycle:
         for(i=0; i<=Math.abs(x(end)-x(end_)); i++){
            var x_ = x(end) -(-(i*x_step));

            for(j=0; j<=Math.abs(y(end)-y(end_)); j++){
               var y_ = y(end) -(-(j*y_step));

               if(x(end)==x(end_) || y(end)==y(end_) || Math.abs(x(end)-x_) == Math.abs(y(end)-y_)){
                  var tmp = new cell(x_+":"+y_);

                  if(incEmpty && tmp.owner==undefined){
                     this.gaps.push(tmp);
                     this.cells.push(tmp);
                  }

                  if(this.owner_check)
                     if(tmp.owner != this.owner && tmp.owner != undefined)
                        break lineCycle;

                  if(this.owner_check && tmp.owner == undefined){
                     if(!incEmpty){ // incEmpty is handled by the last if
                        if(this.gaps.length>0) //If a gap had already been encountered
                           break lineCycle;
                        potential_gap = tmp;
                     }
                  }else

                  if(!this.owner_check || tmp.owner == this.owner){
                     if(potential_gap){
                        this.gaps.push(potential_gap);
                        potential_gap = false; // Practically useless from here on
                     }
                     this.cells.push(tmp);
                  }
               }
            }
         }

         if(!this.owner_check) this.owner = undefined;

         this.blocked = false;
         this.locked  = false;
            var x_before  = this.cells[0].x - ((x(end)==x(end_))? 0 : x_step);
            var y_before  = this.cells[0].y - ((y(end)==y(end_))? 0 : y_step);
            this.previous = new cell(x_before+":"+y_before);

            var x_after   = this.cells[this.cells.length-1].x -(- ((x(end)==x(end_))? 0 : x_step) );
            var y_after   = this.cells[this.cells.length-1].y -(- ((y(end)==y(end_))? 0 : y_step) );
            this.next     = new cell(x_after+":"+y_after);

         if(this.next.owner && this.next.owner != this.owner)
            this.blocked = true;

         if(this.previous.owner && this.previous.owner != this.owner){
            if(this.blocked)
               this.locked = true;
            else
               this.blocked = true;
         }
         this.length  = this.cells.length;

         this.clearEmpty = function(){
            var i;
            for(i=0; i<this.cells.length;i++)
               if(!this.cells[i].owner || this.cells[i].tested){
                  this.cells.splice(i--, 1);
                  this.length = this.cells.length;
               }
         }

         this.identical = function(to){
            if(!to instanceof line) return false;

            var cells = this.cells;
            var i;
            for(i=0; i<cells.length; i++)
               if(to.cells.every(function(item){ return item.id != cells[i].id; }))
                  return false;
            return true;
         }
      }
      //End of Class:line

      //Class: cell
      function cell(id){
         this.node  = (id.id)? id : document.getElementById(id);
         this.id    = (id.id)? id.id : id;
         this.x     = this.id.split(":")[0];
         this.y     = this.id.split(":")[1];
         this.owner = undefined;

         if(!this.node) return false;
         if(this.node.cell) return this.node.cell;

         eval('this.owner = players[marks.indexOf(this.node.innerHTML.split(/[^'+marks.join("")+']/).join(""))];');

         this.openedCorridors = function(reach){
            if(!reach) reach = 0;

            var arr = new Array();
            var tmp, i;

            var dirs = new Array();
                dirs.push( {dir: "left",       x: -reach-1 , y: 0        } );
                dirs.push( {dir: "up left",    x: -reach-1 , y: reach+1  } );
                dirs.push( {dir: "up",         x: 0        , y: reach+1  } );
                dirs.push( {dir: "up right",   x: reach+1  , y: reach+1  } );
                dirs.push( {dir: "right",      x: reach+1  , y: 0        } );
                dirs.push( {dir: "down right", x: reach+1  , y: -reach-1 } );
                dirs.push( {dir: "down",       x: 0        , y: -reach-1 } );
                dirs.push( {dir: "down left",  x: -reach-1 , y: -reach-1 } );

            for(i=0; i<dirs.length; i++){
               var vector = new line(this.id, ( ((this.x)-(-dirs[i].x)) +":"+ ((this.y)-(-dirs[i].y)) ), true );

               if(vector.length > reach)
                  arr.push(vector);
            }

            return arr;
         };

         this.longest = function(){
            var arr = new Array();
            var longest_length   = 0;
            var i;

            var dirs = new Array();
                dirs.push( {dir: "left",       x: -4, y: 0,  op: "right"} );
                dirs.push( {dir: "up left",    x: -4, y: 4,  op: "down right"} );
                dirs.push( {dir: "up",         x: 0,  y: 4,  op: "down"} );
                dirs.push( {dir: "up right",   x: 4,  y: 4,  op: "down left"} );
                dirs.push( {dir: "right",      x: 4,  y: 0,  op: "left"} );
                dirs.push( {dir: "down right", x: 4,  y: -4, op: "up left"} );
                dirs.push( {dir: "down",       x: 0,  y: -4, op: "up"} );
                dirs.push( {dir: "down left",  x: -4, y: -4, op: "up right"} );

            for(i=0; i<dirs.length; i++){
               var vector = new line(this.id, ( ((this.x)-(-dirs[i].x)) +":"+ ((this.y)-(-dirs[i].y)) ) );

               longest_length = Math.max(longest_length, vector.length);
               dirs[i].index = arr.length;
               arr.push(vector);

               var opposite = dirs[i].op;
               dirs.each( function(direction){ if(direction.dir == opposite && direction.index != undefined) opposite = arr[direction.index]; return false; } );

               if(typeof opposite != "string"){
                  var merged = new line(vector.cells.last().id, opposite.cells.last().id);

                  longest_length = Math.max(longest_length, merged.length);

                  arr.push(merged);
                  arr.push( (new line(merged.ends[1].id, merged.ends[0].id)) );//Include the Identical line but with opposite direction
               }
            }

            arr = arr.each( function(item){ return item.length>=longest_length; } ,true);
            return arr;
         };

         this.isOf = function(obj){
            if(obj instanceof player)
               return (obj == this.owner);

            if(obj instanceof line){
               var tid = this.id; // tid /Tested ID/
               return (!obj.cells.every( function(item){ return item.id!=tid; } ));
            }

            if(obj instanceof cell)
               return (obj.id == this.id);
         }

         this.sees = function(what){
            var dirs = new Array();
                dirs.push( {dir: "left",       x: -1, y: 0} );
                dirs.push( {dir: "up left",    x: -1, y: 1} );
                dirs.push( {dir: "up",         x: 0,  y: 1} );
                dirs.push( {dir: "up right",   x: 1,  y: 1} );
                dirs.push( {dir: "right",      x: 1,  y: 0} );
                dirs.push( {dir: "down right", x: 1,  y: -1} );
                dirs.push( {dir: "down",       x: 0,  y: -1} );
                dirs.push( {dir: "down left",  x: -1, y: -1} );
            var i;

            for(i=0; i<dirs.length; i++){
               var x_ = this.x -(-dirs[i].x);
               var y_ = this.y -(-dirs[i].y);

               while(document.getElementById( (x_)+":"+(y_) )){
                  var tmp =         new cell( (x_)+":"+(y_) );

                  if(tmp.isOf(what))
                     return true;

                  if(tmp.owner != undefined)
                     break;

                  x_ -= -(dirs[i].x);
                  y_ -= -(dirs[i].y);
               }
            }

            return false;
         };

         this.adj = function(to){
            var dirs = new Array();
                dirs.push( {dir: "left",       x: -1, y: 0} );
                dirs.push( {dir: "up left",    x: -1, y: 1} );
                dirs.push( {dir: "up",         x: 0,  y: 1} );
                dirs.push( {dir: "up right",   x: 1,  y: 1} );
                dirs.push( {dir: "right",      x: 1,  y: 0} );
                dirs.push( {dir: "down right", x: 1,  y: -1} );
                dirs.push( {dir: "down",       x: 0,  y: -1} );
                dirs.push( {dir: "down left",  x: -1, y: -1} );
            var i;

            for(i=0; i<dirs.length; i++){
               var x_ = this.x -(-dirs[i].x);
               var y_ = this.y -(-dirs[i].y);

               if(document.getElementById( (x_)+":"+(y_) )){
                  var tmp =      new cell( (x_)+":"+(y_) );

                  if(tmp.isOf(to))
                     return true;

                  if(tmp.owner != undefined)
                     break;
               }
            }

            return false;
         };

         this.node.cell = this;
      }
      //End of Class:cell

      //Improve array
      Array.prototype.last = function(offset){
         if(!offset) offset = 0;
         return this[this.length - offset - 1];
      }
      Array.prototype.each = function(filter, rArray){
         var arr     = new Array();
         var summary = {length:0, blocked:true, locked:true, ends:new Array(), cells:new Array(), owner:undefined};

         // Filter is a function with first argument the item to be tested. If true is returned the item is included
         if(!filter) filter = function(item){return true;}

         //Walk array, apply filter and update the summary
         var i;
         for(i=0; i<this.length; i++){
            if(filter(this[i])){
               arr.push(this[i]);

               var j;
               for(j in summary){
                  if(typeof summary[j] == "number")
                     summary[j] += this[i][j];
                  else

                  if(typeof summary[j] == "object" && summary[j] instanceof Array)
                     summary[j] = summary[j].concat(this[i][j]);
                  else

                  if(typeof summary[j] == "boolean")
                     summary[j] = (summary[j] && this[i][j]);
                  else
                     summary[j] = (summary[j]==this[i][j])? this[i][j] : "Multiple";
               }
            }
         }

         //Return the summary object or the filtered array if argument[1] is true
         return (rArray)? arr : summary;
      };
   </script>
   <script type="text/javascript">
   var temp_erred = false;
   var topRated = new Array();

   // Rate all available cells
   function dev_rate_available(announce, playCalled){
      //Temporal javascript to prevent a few bugs
      if(temp_erred){
         dev_toggle_ratings( ((announce? "&rate" : "")+(playCalled? "&play" : "")) );
         return false;
      }else
         temp_erred = true;


      // Get the Enemy's Longest and Not Blocked (aka dangerous) lines
      var elnb = players[curTurn-1].enemy().longest().each(
         function(item){
            return ( item.gaps.length<2 && ( ((item.length==3) && (!item.blocked)) || ((item.length==4) && !item.locked) )  );
         }
      , true);

      // Fetch the available cells for testing
      var avs = document.getElementsByClassName("available");
      var i;
      for(i=0; i<avs.length; i++){
         // Define the tested cell object
         var tested  = new cell(avs[i].id);
         tested.tested = true;

         // Define the three basic types of rates
         tested.aggr = 0; //Aggressive / Offensive
         tested.def  = 0; //Defensive
         tested.mixed;    //Mixed of Aggressive and Defensive

         // Apply ownership for further test checks
         tested.owner = players[curTurn-1];

         //Survival
            ////Length is double because of directional difference for identical arrays

         //If there are more than 1 dangerous lines
         if(elnb.length>(1*2)){
            // Play aggressive

            // Add points only if tested is part of a line longer than 3 (required to block)
            if(tested.longest().each( function(item){ return item.length>=4; }, true).length>0)
               tested.aggr += 5;
         }else
         //If there is only 1 dangerous line
         if(elnb.length==(1*2)){
            // Play defensive

            //If has no gaps between cells
            if(elnb[0].gaps.length == 0){
               // Add points if tested is blocking that dangerous line
               if(elnb[0].next.id == tested.id  ||  elnb[0].previous.id == tested.id)
                  tested.def  += 7;

            //If has gaps
            }else{
               //Add points if tested is among the gaps (and thus blocks the dangerous line)
               if(!elnb[0].gaps.every( function(item){ return item.id != tested.id; } ))
                  tested.def  += 7;
            }
         }


         //Aggressive

         // Add 5 points if this will finish the opponent
         if(tested.longest().each( function(item){ return item.length>4; }, true).length>0)
            tested.aggr += 5;

         // Add a point for each marked cell that combines with tested and the line is not blocked
         tested.aggr += tested.openedCorridors(2).each(function(item){item.clearEmpty(); return true;}).length;

         // Add a point for each open corridor (has potential)
         tested.aggr += Math.floor((tested.openedCorridors().length)/2);

         // Add two extra points if tested forms a dangerous line (unblocked line longer than 2 cells)
         if(tested.longest().each( function(item){ return item.length>2; }, true).length>0)
            tested.aggr += 2;


         //Defensive

         // Add a point if an enemy block is seen (blocks the corridor / can eventually block)
         if( tested.sees(tested.owner.enemy()) )
            tested.def  += 1;

         // Add a point if an enemy block is adjacent to the tested
         if( tested.adj(tested.owner.enemy()) )
            tested.def  += 1;

         // Add a point if it blocks a long but not dangerous line (dangerous lines are handled in Survival
            var collection = new Array();
            players[curTurn-1].enemy().longest().each( function(item){if(item.length<=2) collection = collection.concat(item.previous.id, item.next.id)} );
         tested.def  += ((collection.indexOf(tested.id)!=-1)? 1 : 0);


         //Mixed

         // Mix the two values. A more complex formula can be used if needed
         tested.mixed = tested.aggr + tested.def;


         // Luxury! Display the ratings inside the cell
         if(announce)
            avs[i].innerHTML = "<sup>"+tested.aggr+"</sup><small>"+tested.mixed+"</small><sup>"+tested.def+"</sup>";

         // Save the ratings on the cell element itself before this object is deleted
         tested.node.aggr  = tested.aggr;
         tested.node.def   = tested.def;
         tested.node.mixed = tested.mixed;

         // Delete the link between the cell element and the cell object
         delete tested.node.cell;
      }

      // Convert the HTML Collection to a normal array
      var arr = new Array();
      var i;
      for(i=0; i<avs.length; i++)
         arr.push(avs[i]);

      // Sort the cells according to their ratings
      arr.sort( function(a,b){return ((b.mixed)-(a.mixed)); } );

      // Fill the topRated array with all the cells that are atop after sorting
      var i = 0;
      while(typeof arr[i] != 'undefined' && arr[i].mixed == arr[0].mixed){
         topRated.push(arr[i]);

         // Luxury! Display a distinct border to denote a favorite cell
         if(announce)
            arr[i].className += " topRated";

         i++;
      }

      // Return to "dev_play()" which actually marks a random topRated cell
      return true;
   }


   // Utilizing the rates on cells and actually marking a topRated
   function dev_play(announce){
      if(gameOver) window.location.href = "?";
      if(!announce) announce = false;
      if(!dev_rate_available(announce, true)) return false;
      mark( topRated[ Math.floor((Math.random())*(topRated.length)) ].id );
   }
      // Bind Space key to AI:Play()
      window.onkeydown = function(event){ if(event.keyCode==32) dev_play(); };


   // A function to cleaning the URL
   function dev_toggle_ratings(additional){
      if(!additional) additional = '';
      window.location.href= "?m="+moves_string+additional;
   }

      // Extend the mark function to record the moves in a string
      _oMark = mark;
      var moves_string = '';
      mark = function(elem, myAttempt){
         if(_oMark(elem, myAttempt)){
            if(typeof elem == "string")
               moves_string += (moves_string.length==0? "" : ",")+(elem);
            else
               moves_string += (moves_string.length==0? "" : ",")+(elem.id);
            return true;
         }else
            return false;
      }

      // Extend the mark function to highlight dangerous lines
//      _hMark = mark;
//      var colored = new Array();
//      mark = function(elem, myAttempt){
//         _hMark(elem, myAttempt);
//
//         var en = players[curTurn-1];
//         var elnb = en.longest().each(
//            function(item){
//               return ( item.gaps.length<2 && ( ((item.length==3) && (!item.blocked)) || ((item.length==4) && !item.locked) )  );
//            }
//         , true);
//
//         var ec = colored[en.turn];
//         if(ec){
//            var i;
//            for(i=0; i<ec.length; i++){
//               document.getElementById(ec[i]).className = document.getElementById(ec[i]).className.split(" dangerous").join("");
//               delete ec[i];
//            }
//         }else
//            ec = colored[en.turn] = new Array();
//
//         var j;
//         for(j=0; j<elnb.length; j++){
//            var n;
//            for(n=0; n<elnb[j].cells.length; n++){
//               document.getElementById( elnb[j].cells[n].id ).className += " dangerous";
//
//               if(ec.indexOf(elnb[j].cells[n].id) == -1)
//                  ec.push( elnb[j].cells[n].id );
//            }
//         }
//      }

   </script>

   <style type="text/css">
      .game.cell{
         width:20px;
         height:20px;
         text-align:center;
         font-size:10;
      }
      .available{
         background-color: #fbfbfb;
      }
      .marked{
         background-color: #f9f9f9;
      }
      .player1{
         color: blue;
      }
      .player2{
         color: red;
      }
      .player3{
         color: green;
      }
      .player4{
         color: yellow;
      }
      .last{
         border:2px solid #d4d4d4;
      }
      .topRated{
         border:2px solid #ffdddd;
      }
   </style>

</head>
<body>

<table width="100%" height="100%">

   <tr>
      <td width="80%" height="95%" align="center" valign="middle">

         <table id="game:grid">
            <tr id=":0">
               <td id="0:0" class="game cell"></td>
            </tr>
         </table>

         <br />

         <div id="notice"></div>

      </td>
   </tr>
   <tr>
      <td width="80%" height="5%" align="center" valign="bottom">

         <br /><br /><br />
         <big id="quit"><a href="index.php">Quit</a></big>

         <div id="dev">
            <a onclick="dev_rate_available(true);">Rate Available</a>
            &nbsp; &nbsp;
            <a onclick="dev_play();">Play</a>
            &nbsp; &nbsp;
            <a onclick="dev_toggle_ratings();">Hide Ratings</a>
         </div>

      </td>
   </tr>
</table>

</body>
</html>