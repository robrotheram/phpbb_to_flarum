<?php
// Configs for phpbb database
$servername = "localhost";
$username = "username";
$password = "password";
$dbname = "phpbbDatabase";
$fileName = "flaurm.sql";
$phpprefix = "phpbb_";
//Sets the inital id for the new users posts and discussions set if you know you have more then 1000 users change.
$id = 1000;
$post_data = "INSERT INTO posts (id, user_id, discussion_id, time, type, content) VALUES ";
$diss_data = "INSERT INTO discussions_tags (discussion_id, tag_id) VALUES ";
$myfile = file_exists($fileName) ? fopen($fileName, 'a') : fopen($fileName, 'w');
// Create connection
$conn = new mysqli($servername, $username, $password,$dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";
//Generate Usertables
$result = $conn->query("SELECT user_id, username_clean, user_email FROM ".$phpprefix."users");
if ($result->num_rows > 0) {
        fwrite($myfile, "INSERT INTO users (id, username, email, password, is_activated) VALUES ");
        $tmp_str = "";
    while($row = $result->fetch_assoc()) {
                if($row["user_email"] !=''){
                        $tmp_password = sha1(md5(time()));
                        $tmp_str .= "(".($id+$row["user_id"]).", '".$row["username_clean"]."', '".$row["user_email"]."', '".$tmp_password."', 1),";
                        echo "User: ".$row["username_clean"]." Been Extracted <br/> ";
                }
    }
        $tmp_str = (rtrim($tmp_str,','))."; \n";
        fwrite($myfile, $tmp_str);
} else { echo "0 results"; }

//Generate Tags
echo "<hr/>";
$result = $conn->query("SELECT forum_id, forum_name, forum_desc  FROM ".$phpprefix."forums");
if ($result->num_rows > 0) {
        fwrite($myfile, "INSERT INTO tags (id, name, description, slug, color, position) VALUES ");
        $tmp_str = "";
		$x = 1;
		while($row = $result->fetch_assoc()) {
			$slug = slugify($row["forum_name"]);
			$tmp_str .= "(".($id+$row["forum_id"]).", '".mysql_escape_mimic($row["forum_name"])."', '".mysql_escape_mimic(strip_tags(stripBBCode($row["forum_desc"])))."', '".mysql_escape_mimic($slug)."', '".rand_color()."', ".$x."),";
			$x++;
		}
        $tmp_str = (rtrim($tmp_str,','))."; \n";
        fwrite($myfile, $tmp_str);
} else { echo "0 results"; }

//Generate Disscussions

//poster_id
echo "<hr/>";
$result = $conn->query("SELECT topic_id, forum_id, topic_title, topic_time FROM ".$phpprefix."topics");
if ($result->num_rows > 0) {
        fwrite($myfile, "INSERT INTO discussions (id, title, start_time, comments_count, participants_count, start_post_id, last_post_id, start_user_id, last_user_id, last_time ) VALUES ");
        $tmp_str = "";
	$p_count = 0;
		while($row = $result->fetch_assoc()) {
			$topcis = $conn->query("SELECT * FROM ".$phpprefix."posts where topic_id=".$row["topic_id"]);
			$array = array();
      $date = new DateTime();
      $tmp_date =  $date->format('Y-m-d H:i:s');
      $p_count = 0;
			while($tpl = $topcis->fetch_assoc()) {
				$array[] = $tpl;
				$date->setTimestamp($tpl["post_time"]);
				$tmp_date =  $date->format('Y-m-d H:i:s');
	$cleanComment = "";
        if($p_count>0){$cleanComment = "<r><p>".textProcessing($conn,$tpl['post_text'])."</p></r>";}
	else{$cleanComment = "<t><p>".textProcessing($conn,$tpl['post_text'])."</p></t>";}
	$p_count ++;

				$post_data .= " (".($id+$tpl['post_id']).", ".($id+$tpl['poster_id']).", ".($id+$row['topic_id']).", '".$tmp_date."', 'comment', '".$cleanComment."'),";
			}
			$date = new DateTime();
			$date->setTimestamp($row["topic_time"]);
			$tmp_date =  $date->format('Y-m-d H:i:s');
			$diss_data.="(".($id+$row["topic_id"]).", ".($id+$row["forum_id"])."),";
			$tmp_str .= " (".($id+$row["topic_id"]).", '".textProcessing($conn,$row["topic_title"])."', '".$tmp_date."', ".$topcis->num_rows.", ".$topcis->num_rows.", ".($id+$array[0]['post_id']).",".($id+$array[(($topcis->num_rows)-1)]['post_id']).", ".($id+$array[0]['poster_id']).", ".($id+($array[(($topcis->num_rows)-1)]['poster_id'])).", '".$tmp_date."'),";
		}
        $tmp_str = (rtrim($tmp_str,','))."; \n";
        fwrite($myfile, $tmp_str);
} else { echo "0 results"; }

//Generate user_discussion

$result = $conn->query("SELECT user_id, topic_id FROM ".$phpprefix."topics_posted");
if ($result->num_rows > 0) {
        fwrite($myfile, "INSERT INTO users_discussions (user_id, discussion_id) VALUES ");
        $tmp_str = "";
		while($row = $result->fetch_assoc()) {

			$tmp_str .= "(".($id+$row["user_id"]).", ".($id+$row["topic_id"])."),";
		}
        $tmp_str = (rtrim($tmp_str,','))."; \n";
        fwrite($myfile, $tmp_str);
} else { echo "0 results"; }

$diss_data = (rtrim($diss_data,','))."; \n";
fwrite($myfile, $diss_data);

$post_data = (rtrim($post_data,','))."; \n";
fwrite($myfile, $post_data);

$conn->close();
// Start of functions
function slugify($text){
  $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
  $text = trim($text, '-');
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = strtolower($text);
  $text = preg_replace('~[^-\w]+~', '', $text);
  if (empty($text)){return 'n-a';}
  return $text;
}
function rand_color() {
    return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}
function mysql_escape_mimic($inp) {
    if(is_array($inp))
        return array_map(__METHOD__, $inp);

    if(!empty($inp) && is_string($inp)) {
        return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
    }
    return $inp;
}
#function that will process the main comment and format it for flaurm and the database;
function textProcessing($conn,$text){
  $text = preg_replace('#\:\w+#', '', $text);
  $text = bbcode_toHTML($text);
  $text  = str_replace("&quot;","\"",$text );
  $text = stripBBCode($text);
  #$text =  nl2br($text);
  echo $text."<br/> <hr/> <br/>";
  return $conn->real_escape_string($text);
}
function stripBBCode($text_to_search) {
    $pattern = '|[[\/\!]*?[^\[\]]*?]|si';
    $replace = '';
    return preg_replace($pattern, $replace, $text_to_search);
}

function bbcode_toHTML($bbcode){
  $bbcode = preg_replace('#\[b](.+)\[\/b]#', "<b>$1</b>", $bbcode);
  $bbcode = preg_replace('#\[i](.+)\[\/i]#', "<i>$1</i>", $bbcode);
  $bbcode = preg_replace('#\[u](.+)\[\/u]#', "<u>$1</u>", $bbcode);
  $bbcode = preg_replace('#\[img](.+?)\[\/img]#is', "<img src='$1'\>", $bbcode);
  $bbcode = preg_replace('#\[quote=(.+?)](.+?)\[\/quote]#is', "<QUOTE><i>&gt;</i>$2</QUOTE>", $bbcode);
  $bbcode = preg_replace('#\[code:\w+](.+?)\[\/code:\w+]#is', "<CODE>$1<CODE>", $bbcode);
  $bbcode = preg_replace('#\[\*](.+?)\[\/\*]#is', "<li>$1</li>", $bbcode);
  $bbcode = preg_replace('#\[color=\#\w+](.+?)\[\/color]#is', "$1", $bbcode);
  $bbcode = preg_replace('#\[url=(.+?)](.+?)\[\/url]#is', "<a href='$1'>$2</a>", $bbcode);
  $bbcode = preg_replace('#\[url](.+?)\[\/url]#is', "<a href='$1'>$1</a>", $bbcode);
  $bbcode = preg_replace('#\[list](.+?)\[\/list]#is', "<ul>$1</ul>", $bbcode);
  $bbcode = preg_replace('#\[size=200](.+?)\[\/size]#is', "<h1>$1</h1>", $bbcode);
  $bbcode = preg_replace('#\[size=170](.+?)\[\/size]#is', "<h2>$1</h2>", $bbcode);
  $bbcode = preg_replace('#\[size=150](.+?)\[\/size]#is', "<h3>$1</h3>", $bbcode);
  $bbcode = preg_replace('#\[size=120](.+?)\[\/size]#is', "<h4>$1</h4>", $bbcode);
  $bbcode = preg_replace('#\[size=85](.+?)\[\/size]#is', "<h5>$1</h5>", $bbcode);

  return $bbcode;
}
?>
