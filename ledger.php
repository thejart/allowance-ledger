<?php
function connectToMysql($database, $table, $host = 'localhost') {
  try {
    $mysqlCreds = file_get_contents('mysql.creds');
    list($user, $pass) = explode("\n", $mysqlCreds);
  } catch (Exception $e) {
    error_log('Mysql creds not determined, exiting');
    exit(1);
  }
  mysql_connect($host, $user, $pass) or die(mysql_error());
  mysql_select_db($database) or die(mysql_error());
}

$path = "allowance-ledger/";
$thisScript = basename(__FILE__);
$database = "finance";
$table = "ledger";
$host = $_SERVER['HTTP_HOST'];
connectToMysql($database, $table);

if ($_GET['edit']) {
  if (isset($_GET['cleared'])) {
    $insert = "update ". $table. " set description='". $_GET['description']. "',".
              "amount=". $_GET['amount']. ",cleared=1 where id=". $_GET['id'];
  } else {
    $insert = "update ". $table. " set description='". $_GET['description']. "',".
              "amount=". $_GET['amount']. " where id=". $_GET['id'];
  }
  mysql_query($insert) or die ("[$insert] failed");
  header( "HTTP/1.1 301 Moved Permanently" );
  header( "Status: 301 Moved Permanently" );
  header( "Location: https://$host/". $path. $thisScript);
  exit(0); // This is Optional but suggested, to avoid any accidental output
}
else if ($_GET['description'] && $_GET['amount']) {
  mysql_query("insert into ". $table. " (credit,description,amount) ".
              "values (". $_GET['credit']. ",'". $_GET['description']. "',". $_GET['amount']. ")");
  header( "HTTP/1.1 301 Moved Permanently" );
  header( "Status: 301 Moved Permanently" );
  header( "Location: https://$host/". $path. $thisScript);
  exit(0); // This is Optional but suggested, to avoid any accidental output
}
else if ($_GET['id']) {
  mysql_query("delete from ". $table. " where id=". $_GET['id']);
  header( "HTTP/1.1 301 Moved Permanently" );
  header( "Status: 301 Moved Permanently" );
  header( "Location: https://$host/". $path. $thisScript);
  exit(0); // This is Optional but suggested, to avoid any accidental output
}


$creditQ = mysql_query("select sum(amount) as c from ". $table.
                        " where credit is TRUE order by time");
$credit = mysql_fetch_array($creditQ);
$debitQ = mysql_query("select sum(amount) as d from ". $table.
                        " where credit is FALSE order by time");
$debit = mysql_fetch_array($debitQ);
$balance = $credit[c] - $debit[d];
$outstandingQ = mysql_query("select sum(amount) as o from ". $table.
                  " where credit is FALSE and cleared is FALSE order by time");
$outstanding = mysql_fetch_array($outstandingQ);


$calstr = `cal`;
$cal = preg_split('/\n/',$calstr);
$len = sizeof($cal);
$lastmday = (preg_match('/\S/',$cal[($len-2)])) ? $cal[($len-2)] :
                                                  $cal[($len-3)];
if (preg_match('/\s/',$lastmday)) {
  $d = preg_split('/\s+/',$lastmday);
  $len = sizeof($d);
  $lastmday = $d[($len-2)];
}

$todaystr = `date | awk '{print $3}'`;
$today = preg_replace('/^(\d+).*/','$1',$todaystr);
$payday = ($today >= 15) ? $lastmday : 15;
$daysleft = ($lastmday - $today) ? $payday - $today : 15;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
         "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>My Budget</title>
<meta name="viewport" content="width=320; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;"/>
<script type="text/javascript">
  <!--
  function makeHttpRequest(blah, callback_function, return_xml) {
    var url = "<?=$thisScript?>";
    var http_request, response, i;

    var activex_ids = [
      'MSXML2.XMLHTTP.3.0',
      'MSXML2.XMLHTTP',
      'Microsoft.XMLHTTP'
    ];

    if (window.XMLHttpRequest) { // Mozilla, Safari, IE7+...
      http_request = new XMLHttpRequest();
      if (http_request.overrideMimeType) {
        http_request.overrideMimeType('text/xml');
      }
    } else if (window.ActiveXObject) { // IE6 and older
      for (i = 0; i < activex_ids.length; i++) {
        try {
          http_request = new ActiveXObject(activex_ids[i]);
        } catch (e) {}
      }
    }

    if (!http_request) {
      alert('Unfortunatelly you browser doesn\'t support this feature.');
      return false;
    }

    http_request.onreadystatechange = function() {
      if (http_request.readyState !== 4) {
          // not ready yet
          return;
      }
      if (http_request.status !== 200) {
        // ready, but not OK
        alert('There was a problem with the request.(Code: ' +
              http_request.status + ')');
        return;
      }

      if (return_xml) {
        response = http_request.responseXML;
      } else {
        response = http_request.responseText;
      }
/*
      callback_function(response);

      document.getElementById('ajax-response').innerHTML =
              http_request.responseText;
      document.getElementById('ajax-response').className = "header1 red";
      if (http_request.responseText == "Email Sent") {
        setTimeout("hideit('email');",2000);
      }
*/
    };

    http_request.open('GET', url, true);
    http_request.send(null);
    return false;
  }
  function hideit(element) {
    elem = document.getElementById(element);
    elem.className = "hideme";
    return false;
  }
  function confirmDeletion(id,descript) {
    delurl = "<?=$thisScript?>?id=" + id;
    var response = confirm("You're deleting \"" + descript + "\".  OK?!?");
    if (response) {
      window.location = delurl;
    }
    else {
      return false;
    }
  }
  function editTrans(id,amount,descript) {
    clr = document.getElementById('clr');
    clr.checked = false;
    ID = document.getElementById('id');
    ID.value = id;
    desc = document.getElementById('desc');
    desc.value = descript;
    amt = document.getElementById('amt');
    amt.value = amount;
    elem = document.getElementById('editor');
    elem.className = 'showme';
  }
  // -->
</script>
<style type="text/css">
<!--
  div.showme {
/*
    visibility:visible;
*/
    position:absolute;
    display:block;
    top:30px;
    left:10px;
    width:260px;
    height:250px;
    background-color:#7EB0DE;
    margin:5px;
    padding:10px;
    border: 2px solid #000000;
    z-index:10;
  }
  div.hideme {
    display:none;
    position:fixed;
    z-index:0;
  }
  a:link { color:red; }
  .nav {
    font-size:16pt;
  }
  a.nav { color:red; }
  div.nav { float:right; font-size:11pt; padding:6px 0 0; }
-->
</style>
</head>
<body>
<div class='hideme' id='editor'>
<form action='<?=$thisScript?>' method=GET>
  <input type="hidden" name="id" id="id" value=""><br>
  <input type="hidden" name="edit" value="1"><br>
  <table align='center'>
  <tr>
    <td>description</td>
    <td><input size='15' type="text" name="description" id="desc" value=""></td>
  </tr>
  <tr><td>&nbsp;</td></tr>
  <tr>
    <td>amount</td>
    <td><input size='8' type="text" name="amount" id="amt" value=""></td>
  </tr>
  <tr><td>&nbsp;</td></tr>
  <tr>
    <td><label for="clr">cleared?</label></td>
    <td><input type="checkbox" name="cleared" id="clr"></td>
  </tr>
  <tr><td>&nbsp;</td></tr>
  <tr>
    <td><input type="submit" value="OK"></td>
    <td align='right'>
      <input type="submit" value="Cancel" onClick="return hideit('editor');">
    </td>
  </tr>
  </table>
</form>
</div>

<table width='100%' align='center' border='0' cellpadding='0' cellspacing='0'>
  <tr height=30>
    <td colspan=4 valign='top'>
      <a class='nav' href='<?=$thisScript?>'>Budget</a> <span class='nav'>|</span>
      <a class='nav' href='<?=$thisScript?>?view=summary'>Summary</a>
      <div class='nav'>
<?
  echo "$". sprintf('%01.2f', $balance/$daysleft). "/day for $daysleft days";
  #echo "daysleft: $daysleft | today: [$today] | lastmday: [$lastmday]";
?>
      </div>
    </td>
  </tr>

<?
if (isset($_GET['view']) && $_GET['view'] == 'summary') {
  $dur = (isset($_GET['dur'])) ? $_GET['dur'] : 15;
  $lnks = array('Weekly' => 7, 'Bi-Weekly' => 15, 'Monthly' => 30, 'Quarterly' => 91);
  $header = "<tr height=30><td valign='top' align='center' colspan=3>";
  foreach ($lnks as $k=>$v) {
    if ($v != 7) { $header .= "&nbsp;&nbsp;|&nbsp;&nbsp;"; }
    if ($v == $dur) {
      $header .= "$k";
    } else {
      $header .= "<a href='". $thisScript. "?view=summary&dur=$v'>$k</a>";
    }
  }
  print "$header</td></tr>\n";
  $start = 0;
  $cycles = 6;
  for($i=0;$i<$cycles;$i++) {
    $x = $dur + $start - 1;  # we're really only including the following day
    $q = mysql_query("select subdate(now(), interval $x day) as d");
    $r = mysql_fetch_array($q);
    $from = $r[d];
    $from = preg_replace("/\d{4}-(\d{2})-(\d{2}).*/","$1/$2",$from);
    $q = mysql_query("select subdate(now(), interval $start day) as d");
    $r = mysql_fetch_array($q);
    $to = $r[d];
    $to = preg_replace("/\d{4}-(\d{2})-(\d{2}).*/","$1/$2",$to);
    $creditS = "select sum(amount) s from ". $table. " where credit=1 and ".
               "(datediff(now(),time)<". ($dur+$start). ") and ".
               "(datediff(now(),time)>=". $start. ")";
    $creditQ = mysql_query($creditS);
    $c = mysql_fetch_array($creditQ);
    $tot = ($c[s]) ? " <b>\$$c[s]</b>" : "";
    print "
    <tr>
      <td colspan=2><em>$from to $to</em>$tot</td>
    </tr>\n";

    ## sum for calculating percentage
    $dursummary = "select sum(amount) as s from ". $table.
                      " where credit=0 and (datediff(now(),time)<". ($dur+$start).
                      ") and (datediff(now(),time)>=". $start. ")";
    $durQ = mysql_query($dursummary);
    $row = mysql_fetch_array($durQ);
    $sum = $row['s'];

    $dursummary = "select sum(amount) as s,description from ". $table.
                      " where credit=0 and (datediff(now(),time)<". ($dur+$start).
                      ") and (datediff(now(),time)>=". $start. ")
                      group by description order by s desc";
                      #order by s group by description with rollup";
    print "<!-- $dursummary -->\n";
    $durQ = mysql_query($dursummary);
    $j=0;
    $entries = mysql_num_rows($durQ);
    while ($row = mysql_fetch_array($durQ)) {
      $perc = sprintf('%01.1f', (($row['s']/$sum)*100));
      #$perc = ($perc != 100) ? '&nbsp;&nbsp;<em>('. $perc. '%)</em>' : '';
      $perc = '&nbsp;&nbsp;<em>'. $perc. '%</em>';
      $j++;
      #$bgcolor = ($j==$entries || $j%2) ? '#ffffff' : '#c0c0c0';
      $bgcolor = ($j%2) ? '#ffffff' : '#c0c0c0';
      $amount = '$'. $row[s];
      $desc = $row[description];
      if (!$desc) { $amount = "<b>$amount</b>"; }
      print "
      <tr bgcolor='$bgcolor'>
        <td align='left'>$desc</td>
        <td align='right'>$perc</td>
        <td align='right'>$amount</td>
      </tr>\n";
    }
    print "
    <tr bgcolor='#ffffff'>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td align='right'>$$sum</td>
    </tr>\n";
    $start+=$dur;
  }
} else {
?>

  <tr>
    <td colspan='4' align='left'>
    <form action="<?=$thisScript?>" method="get">
    <select name="credit">
    <option selected value="0">Debit</option>
    <option value="1">Credit</option>
    </select>

    <input size='15' type="text" name="description" value="">
    <input id="zip" size='8' type="text" name="amount" value="">
    <input type="submit" value="OK">
    </form>
    </td>
  </tr>
<?  if ($outstanding[o]) { ?>
  <tr>
    <td colspan='4' align='right'>
      <strong><em>$<?=$outstanding[o]?> uncleared</em></strong>
    </td>
  </tr>

<? } ?>

<tr>
  <td>&nbsp;</td>
  <td>&nbsp;</td>
  <td><i>amount</i></td>
  <td align='right'><i>balance</i></td>
</tr>

<?
  $allQ = mysql_query("select id,credit,description,amount,time,cleared 
                      from ". $table. " where (datediff(current_timestamp(),time)<60)
                      order by time desc");
                      #from ".$table." where (datediff(current_timestamp(),time)<40)
  $k=0;
  while ($row = mysql_fetch_array($allQ)) {
    $id = $row[id];
    $credit = $row[credit];
    $description = $row[description];
    $amount = $row[amount];
    $time = $row[time];
    $cleared = $row[cleared];
    $Pbalance = sprintf("%01.2f",$balance);

    if ($balance > 0 ) { $bgcolor = 'ffffff'; }
    else { $bgcolor = 'ffff00'; }
    if (!$k) { $Sstrong = "<strong>"; $Estrong = "</strong>"; }
    else { $Sstrong = ""; $Estrong = ""; }
  ?>

    <tr bgcolor='<?=$bgcolor?>'>
      <td>
        <a href='javascript:confirmDeletion("<?=$id?>","<?=$description?>")'><img src='x.png'></a>
      </td>

  <?
    $tshort = preg_replace("/\d{4}-(\d{2})-(\d{2}).*/","$1/$2",$time);

    if ($credit) {
      echo "<td>". $description. " ($tshort)</td><td>$". $amount.
           "</td><td align='right'>". $Sstrong. "$". $Pbalance. $Estrong.
           "</td></tr>\n";
      $balance = $balance - $amount;
    } else {
      if (!$cleared) {
        echo "<td>". $description. " ($tshort) </td><td><a href='#' ".
             "onClick=\"return editTrans('$id','$amount','$description');\">($".
             $amount.
             ")</a></td><td align='right'>". $Sstrong. "$". $Pbalance. $Estrong.
             "</td></tr>\n";
      } else {
        echo "<td>". $description. " ($tshort) </td><td>($". $amount.
             ")</td><td align='right'>". $Sstrong. "$". $Pbalance. $Estrong.
             "</td></tr>\n";
      }
      $balance = $balance + $amount;
    }
    $k++;
  }


  ## uncleared items that are outside of the previous bounds
  $allQ = mysql_query("select id,credit,description,amount,time,cleared 
                      from ". $table. " where time<='". $time. "' and cleared=0 and credit=0
                      order by time desc");
                      #from ".$table." where (datediff(current_timestamp(),time)<40)
  $k=0;
  while ($row = mysql_fetch_array($allQ)) {
    $id = $row[id];
    $credit = $row[credit];
    $description = $row[description];
    $amount = $row[amount];
    $time = $row[time];
    $cleared = $row[cleared];

    $bgcolor = '7f7fff';
  ?>

    <tr bgcolor='<?=$bgcolor?>'>
      <td>
        <a href='javascript:confirmDeletion("<?=$id?>","<?=$description?>")'><img src='x.png'></a>
      </td>

  <?
    $tshort = preg_replace("/\d{4}-(\d{2})-(\d{2}).*/","$1/$2",$time);

    echo "<td>". $description. " (". $tshort. ") </td><td><a href='#' ".
         "onClick=\"return editTrans('". $id. "','". $amount. "','". $description. "');\">($".
         $amount.
         ")</a></td><td align='right'>OLD</td></tr>\n";
    $k++;
  }

} ?>

</table>
</body>
</html>
