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
   // A function to clean the URL
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
            <a onclick="dev_toggle_ratings();">Hide Ratings</a>
         </div>

      </td>
   </tr>
</table>

</body>
</html>