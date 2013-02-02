<?php
//Admirations: 0:0,1:0,-1:1,1:1,1:-1,2:-2,0:-1,2:-1,0:-2,0:1,-1:2,2:0,2:-3,-1:0,0:-3,0:-4,1:-3,-1:-3,-1:-1,-2:0,-2:-1,-3:-1,-2:-2,-1:-2,2:-4,3:-5,3:-3,4:-3,3:-2,4:-4,1:-4,4:-1,4:-2,1:-5,2:-6,2:-5,4:-5,5:-2,3:-4,1:-2,0:-5,-1:-6,2:1,-1:3,0:2,-2:2,1:-6,-3:1,-4:0,-3:0,-3:-2,-1:-5,-1:-4,-2:-3,-3:-3,-4:-4,-4:-2,5:-1,-5:-1,-6:0,-5:-2,-6:-2,-4:-1,-4:1,-5:0,-5:1,-6:1,-7:2,-5:-3,-5:-4,-3:3,-6:-4,-3:-4,-6:-3,-6:-1,-7:0,-4:-3,-2:-5,-7:-1,-8:-1,-3:-5,-3:-6,-7:-2,-2:-6,-4:-6,-2:-7,-4:-5,-2:-4
//Could have won: 0:0,-1:-1,1:-1,0:-2,-1:1,2:-2,1:0,1:-2,-1:-2,-2:0,1:-3,-2:-1,0:-1,-2:-3,-2:-2,-3:-2,2:1,3:2,0:1,3:1,0:2,0:3,2:0,3:0,1:1,3:-1,-2:1,3:-2

initiation:{
   if(!isset($_REQUEST['m'])) die("No Moves given to the AI!");

   $moves = array_flip(explode(",", $_REQUEST['m']));
   $marks = array("X", "O");

   if(isset($GLOBALS['ai'])){
      $marks = explode(",", mysql_("SELECT Marks FROM scu_games WHERE Players LIKE '%".session_id()."%'"));

      if( $GLOBALS['ai'] != ((count(explode(",", $_REQUEST['m'])))%count($marks)) )
         exit;
   }

   foreach($moves as $id=>$n)
      $moves[$id] = $marks[ ($n % count($marks)) ];


      if(!function_exists('c_dump')){
         function c_dump($v, $indent = ''){
            $type = gettype($v);
            if($type == "array"){
               echo $indent."Array(".count($v).")\n";
                  $indent .= "\t";
               foreach($v as $v_)
                  c_dump($v_);
            }else{
               //echo $indent.ucfirst($type)."\n";
                  $indent .= "\t";
               echo $v."\n";
            }
         }
      }
}

core:{
if(!class_exists('player')){
   class player{
      public $turn, $mark, $moves = array();

      static $vMarks = 'marks', $vMoves = 'moves';
      function __construct($m){
         $this->mark  = $m;
         $this->turn  =  array_search($m, $GLOBALS[self::$vMarks]);

         foreach($GLOBALS[self::$vMoves] as $id=>$mark)
            if($mark == $m)
               $this->moves[] = $id; // = new cell($id);

         $this->uid = count(self::$players);
         self::$players[] = $this;
      }

      private static  $players = array();
      public  static  function count(){ return count(self::$players); }
      public function enemy($step=1){
         return self::$players[ ($this->turn + $step) % self::count() ];
      }

      public function enemies(){
         return new playerCollection( array_splice(self::$players, $this->uid, 1) );
      }

      public function longest($minLength = 1, $allViable = false){
         $lines = array();
         foreach($this->moves as $id){
            $cell = new cell($id);
            $long = $cell->longest($minLength, $allViable);

            $lines = array_merge($lines, $long);
            if(!$allViable && current($long))
               $minLength = current($long)->length; //cell::longest ensures that $long[0].length is >= $minLength
         }


         $lines = array_filter($lines, create_function('$line', 'return ($line->length) >= '.$minLength.';') );
         $lines = array_merge($lines);

         // Remove identical lines
         for($k=0, $k_ = $j_ = count($lines); $k<$k_; $k++){
            if(!isset($lines[$k])) continue;

            for($j=0; $j<$j_; $j++){
               if(!isset($lines[$j])) continue;

               if($k != $j)
               if( reset($lines[$k]->cells)->id == reset($lines[$j]->cells)->id)
               if(   end($lines[$k]->cells)->id ==   end($lines[$j]->cells)->id){
                  unset($lines[$j]);

                  if(reset($lines[$k]->cells)->id != reset($lines[$k]->ends)->id){
                     $v->ends[0] = $lines[$k]->cells[0];
                     //$lines[$k]->end     = $lines[$k]->cells[0]->id;   //Property is private
                  }
                  if(end($lines[$k]->cells)->id   != end($lines[$k]->ends)->id){
                     $v->ends[1] = end($lines[$k]->cells);
                     //$lines[$k]->end_    = end($lines[$k]->cells)->id; //Property is private
                  }
               }
            }
         }
         $lines = array_merge($lines);

         return $lines;
      }

      public function longer($than = 1){
         return $this->longest($than, true);
      }

      function __toString(){
         return $this->mark;
      }

      public function clear(){
         return self::$players = array();
      }
   }

   class playerCollection{
      public $players;

      function __construct($arr){
         $this->players = $arr;
      }

      public function longer($than = 1){
         $arr = array();
         foreach($this->players as $pl){
            $arr = array_merge($arr, $pl->longer($than));
         }
         return $arr;
      }
   }
}

if(!class_exists('line')){
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
                        if(!is_null($this->gap) || $this->gapPending != false) //If a gap had already been encountered
                           return false;

                        $this->gapPending = $cell;
                           continue;
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

      function __toString(){
         return "(".$this->end." -> ".$this->end_.")";
      }
   }
}

if(!class_exists('cell')){
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

      public function openedCorridors(){
         $arr  = array();
         $dirs = array();
         $dirs[] = array( 'dir'=>"left",       'x'=> -1  , 'y'=>  0 );
         $dirs[] = array( 'dir'=>"up left",    'x'=> -1  , 'y'=>  1 );
         $dirs[] = array( 'dir'=>"up",         'x'=>  0  , 'y'=>  1 );
         $dirs[] = array( 'dir'=>"up right",   'x'=>  1  , 'y'=>  1 );
         $dirs[] = array( 'dir'=>"right",      'x'=>  1  , 'y'=>  0 );
         $dirs[] = array( 'dir'=>"down right", 'x'=>  1  , 'y'=> -1 );
         $dirs[] = array( 'dir'=>"down",       'x'=>  0  , 'y'=> -1 );
         $dirs[] = array( 'dir'=>"down left",  'x'=> -1  , 'y'=> -1 );

         foreach($dirs as $dir){
            $i = 1;
            do{
               $cell = new cell( ($this->x + $i*$dir['x']) .":". ($this->y + $i*$dir['y']) );

               // If is same owner continue searching (Undecided Corridor)
               if($this->owner == $cell->owner){
                  $i++;
                  continue;
               }else

               // If is enemy, reset the result (Closed Corridor)
               if(!is_null($cell->owner))
                  $i = 0;
               // Else if is a gap $i will show the offset (Opened Corridor)

               unset($cell);
               break;
            }while(true);

            if($i>0){
               $vector = new line($this->id, ( ($this->x + $i*$dir['x']) .":". ($this->y + $i*$dir['y']) ) );
               if(!$vector->blocked)
                  $arr[] = $vector;
            }
         }

         return $arr;
      }

      public function longest($minLength=0, $allViable = false){
         ////dangerous3 are counted both Straight and Reversed
         ////dangerous4 are only counted Straight!!!! BUG
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

            if(!$allViable)
               $longest_length = max($longest_length, $vector->length);

            $opposite = $dir['op'];
            foreach($dirs as $t)
               if($t['dir'] == $opposite && isset($t['index']))
                  $opposite = $arr[($i = $t['index'])];

            if(!is_string($opposite)){
               $merged = new line(end($vector->cells)->id, end($opposite->cells)->id);

               if(!$allViable)
                  $longest_length = max($longest_length, $merged->length);

               $arr[] = $merged;

               //Remove the incomplete line
               unset($arr[$i]); //$i is surely set because else $opposite would remain string and we wont come to here

               //Reversed of Normal Lines are not included, so why should Reversed of Merged be?
               //$arr[] = new line($merged->ends[1]->id, $merged->ends[0]->id);//Include the Identical line but with opposite direction
            }else{
               $dirs[$k]['index'] = count($arr);
               $arr[] = $vector;
            }
         }

         $arr = array_filter($arr, create_function('$item', 'return $item->length >= '.$longest_length.';'));
         $arr = array_merge($arr);

         // Remove identical lines
         for($k=0, $k_ = $j_ = count($arr); $k<$k_; $k++){
            if(!isset($arr[$k])) continue;

            for($j=0; $j<$j_; $j++){
               if(!isset($arr[$j])) continue;

               if($k != $j)
               if( reset($arr[$k]->cells)->id == reset($arr[$j]->cells)->id)
               if(   end($arr[$k]->cells)->id ==   end($arr[$j]->cells)->id){
                  unset($arr[$j]);

                  if(reset($arr[$k]->cells)->id != reset($arr[$k]->ends)->id){
                     $v->ends[0] = $arr[$k]->cells[0];
                     //$arr[$k]->end     = $arr[$k]->cells[0]->id;   //Property is private
                  }
                  if(end($arr[$k]->cells)->id   != end($arr[$k]->ends)->id){
                     $v->ends[1] = end($arr[$k]->cells);
                     //$arr[$k]->end_    = end($arr[$k]->cells)->id; //Property is private
                  }
               }
            }
         }
         $arr = array_merge($arr);

         return $arr;
      }

      public function longer($than){
         return $this->longest($than, true);
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

      function __toString(){
         return $this->id;
      }
   }
}

$players = player::clear(); //array();
foreach(${player::$vMarks} as $m)
   $players[] = new player($m);
}


rate:
if(!defined('easy'))   define('easy',   0, true);
if(!defined('normal')) define('normal', 1, true);
if(!defined('hard'))   define('hard',   2, true);
$difficulty = isset($_REQUEST['d'])? (int) $_REQUEST['d'] : normal;

if(!defined('all'))        define('all',        0, true);
if(!defined('aggressive')) define('aggressive', 1, true);
if(!defined('defensive'))  define('defensive',  2, true);
if(!defined('mixed'))      define('mixed',      3, true);
$announce = isset($_REQUEST['ann']);

if(!function_exists('rate')){
   function rate($cell, $player, $r=0){
      $aggr   = $def   = $mixed = 0;
      $m_aggr = $m_def = 1;
      global $difficulty;
      global $announce;

      if(!is_string($cell))
         if(isset($cell->id))
            $cell = $cell->id;
         else
            return false;

      // Get the Enemy's Longest and Not Blocked (aka dangerous) lines
      static $elnb;
      if(!isset($elnb)){
         $elnb = $player->enemy()->longer(3);
         foreach($elnb as $k=>$v){
            if(($v->locked) || (($v->length < 4) && ($v->blocked)))
               unset($elnb[$k]);
         }
         $elnb = array_merge($elnb);
   if($announce) echo "<pre>",c_dump($elnb),"</pre><hr />\n";
      }

      // Apply ownership for further rating
      $GLOBALS['moves'][$cell] = $player->mark;
      $rated = new cell($cell);

                  if($announce) echo "Cell: ".$cell."<br />\n<br />\n"; $a_ = $aggr; $d_ = $def;

      //Survival
         ////Length is double because of directional difference for identical lines

      //If there are more than 1 dangerous lines
      if(count($elnb)>(1)){
         // Play aggressive
   //      if($difficulty == normal) $m_def -= 0.5;

         // Add points only if rated is part of a line longer than 3 (enemy needs to be block)
         if(count($rated->longest(4))>0)
            $aggr += 5;
                  if($announce) echo "Survival: a".($aggr-$a_)."<br />\n"; $a_ = $aggr;
      }else
      //If there is only 1 dangerous line
      if(count($elnb)==(1)){
         // Play defensive
   //      if($difficulty == normal) $m_aggr -= 0.5;

         // Add points if rated is blocking that dangerous line
         if( ( !($elnb[0]->length == 4 && !is_null($elnb[0]->gap)) && ($elnb[0]->next->id == $rated->id  ||  $elnb[0]->prev->id == $rated->id)) || (!is_null($elnb[0]->gap) && $elnb[0]->gap->id == $rated->id))
            $def += ($elnb[0]->length == 4)? 17 : 6;
                  if($announce) echo "Survival: d".($def-$d_)."<br />\n"; $d_ = $def;
      }


      //Aggressive

      // Add the staggering twenty points if this will finish the opponent
      if(count($rated->longest(5))>0) //The usual case with a waiting d4
         $aggr += 20;
      // The more rare case where there is an unblocked d3 deserves only ten points
      else{
         $d4 = $rated->longest(4);
         foreach($d4 as $k=>$v)
            if($v->blocked || !is_null($v->gap))
               unset($d4[$k]);

         if(count($d4)>0)
            $aggr += 10;
      }
                  if($announce) echo "Execute: ".($aggr-$a_)."<br />\n"; $a_ = $aggr;

      // Add a point for each open corridor (has potential)
         $cor = $rated->openedCorridors();
      $aggr += floor(count($cor)/3); // )/2);
                  if($announce) echo "Potential: ".($aggr-$a_)."<br />\n"; $a_ = $aggr;

      // Add a point for each marked cell that combines with rated and the line is not blocked
         $combos = 0;
         if($difficulty != easy){
            $cor = array_merge($cor, $rated->longer(4));
            foreach($cor as $v){
               if( (!$v->blocked && $v->length > 1) || (!$v->locked && $v->length >= 4) )
                  $combos += $v->length;
            }
         }
      $aggr += floor($combos/2);
                  if($announce) echo "Combine: ".($aggr-$a_)."<br />\n"; $a_ = $aggr;

      // Add two extra points if rated forms a dangerous line (unblocked line longer than 2 cells)
         $longest = $rated->longest(3);
         foreach($longest as $k=>$v){
            if($v->locked || ($v->length < 4 && $v->blocked))
               unset($longest[$k]);
         }
      if(count($longest)>0)
         $aggr += 2;
                  if($announce) echo "Endanger: ".($aggr-$a_)."<br />\n"; $a_ = $aggr;

      // Add three extra points if rated forms two+ dangerous lines
         $long = $rated->longer(3);
         foreach($long as $k=>$v){ //Remove blocked ones
            if($v->locked || ($v->length < 4 && $v->blocked) )
               unset($long[$k]);
         }
      if(count($long) >= 2){
         $aggr += 3;
         //Add four more points for d4 among these  dangerous lines
         foreach($long as $line)
            if($line->length > 3)
               $aggr += 4;
      }
                  if($announce) echo "Unstoppable: ".($aggr-$a_)."<br />\n"; $a_ = $aggr;

                  if($announce) echo "<br />\n";


      //Defensive

      // Add a point if an enemy cell is seen (blocks the corridor / can eventually block)
      if($rated->sees($player->enemy()))
         $def  += 1;
                  if($announce) echo "Sees: ".($def-$d_)."<br />\n"; $d_ = $def;

      // Add a point if an enemy cell is adjacent to the rated
      if($rated->adj($player->enemy()))
         $def  += 1;
                  if($announce) echo "Touches: ".($def-$d_)."<br />\n"; $d_ = $def;


      // Add 5 points if it prevents the forming of 2 dangerous lines
         ////Should be able to stack these points if there are multiple enemies
         $rated->owner = $player->enemy();
         $GLOBALS['moves'][$cell] = $player->enemy()->mark;

         $eLong = $rated->longer(3);
         foreach($eLong as $k=>$v){
            if($v->locked || ($v->length < 4 && $v->blocked))
               unset($eLong[$k]);
         }
      if(count($eLong)>(1))
         $def += 5;

         $rated->owner = $player;
         $GLOBALS['moves'][$cell] = $player->mark;
                  if($announce) echo "Precognition: ".($def-$d_)."<br />\n"; $d_ = $def;

                  if($announce) echo "<br />\n";
                  if($announce) echo "Total: a$a_ d$d_ m".($a_+$d_)."<hr />\n";

      //Mixed

      // Mix the two values. A more complex formula can be used if needed
      $mixed = $m_aggr*$aggr + $m_def*$def;


      //Remove the temporal ownership
      unset($rated, $GLOBALS['moves'][$cell]);


      if($r==0 || $r==1) $rating[] = $aggr;
      if($r==0 || $r==2) $rating[] = $def;
      if($r==0 || $r==3) $rating[] = $mixed;

      if(!isset($rating))
         return false;
      if(count($rating)==1)
         $rating = $rating[0];

      return $rating;
   }
}

partial:{
   // Only part of the capabilities are needed
   if(isset($_REQUEST['ann']) && $_REQUEST['ann'] == 'dangerous'){
      $player = $players[ ((count(explode(",", $_REQUEST['m'])))%count($marks)) ];

      $elnb = $player->enemy()->longer(3);
      foreach($elnb as $k=>$v){
         if(($v->locked) || (($v->length < 4) && ($v->blocked)))
            unset($elnb[$k]);
      }
      $elnb = array_merge($elnb);

      foreach($elnb as $l){
         $initial = 0;
         foreach($l->cells as $c)
            echo ((!($initial++))? "" : ",") . $c->id;

         echo "\n";
      }
      exit;
   }
}

ellection:{
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

   //Make the decision
   $chosen = $topRated[array_rand($topRated)];
}


   //AI:Act!
   if(!isset($GLOBALS['ai']))
      echo $chosen;
?>