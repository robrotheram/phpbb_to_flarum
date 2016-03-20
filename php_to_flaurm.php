<?php
// Original script by robrotheram from discuss.flarum.org
// Modified by VIRUXE
// And Reflic

set_time_limit(0);
ini_set('memory_limit', -1);

ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");

// Configs for PHPBB database
$servername = "localhost";
$username = "root";
$password = "";
$exportDBName = "old-dlrg";
$importDBName = "flarum";

// Establish a connection to the server where the PHPBB database exists
$exportDbConnection = new mysqli($servername, $username, $password, $exportDBName);
$importDbConnection = new mysqli($servername, $username, $password, $importDBName);

// Check connection
if ($exportDbConnection->connect_error)
	die("Export - Connection failed: " . $exportDbConnection->connect_error);
else
{
	echo "Export - Connected successfully<br>";

	if(!$exportDbConnection->set_charset("utf8")) 
	{
	    printf("Error loading character set utf8: %s\n", $exportDbConnection->error);
	    exit();
	} 
	else
	    printf("Current character set: %s\n", $exportDbConnection->character_set_name());
}

if ($importDbConnection->connect_error)
	die("Import - Connection failed: " . $importDbConnection->connect_error);
else
{
	echo "Import - Connected successfully<br>";

	if(!$importDbConnection->set_charset("utf8")) 
	{
	    printf("Error loading character set utf8: %s\n", $importDbConnection->error);
	    exit();
	} 
	else
	    printf("Current character set: %s\n", $importDbConnection->character_set_name());
}

//Convert Users
$sqlScript = fopen('flarum_users.sql', 'w');

echo "<hr>Step 1 - Users<hr>";
$result = $exportDbConnection->query("SELECT user_id, from_unixtime(user_regdate) as user_regdate, username_clean, user_email FROM phpbb_users");
$totalUsers = $result->num_rows;
if ($totalUsers) 
{
	fwrite($sqlScript, "INSERT INTO users (id, username, email, password, join_time, is_activated) VALUES \n");

	$i = 0;
	$usersIgnored = 0;
	while($row = $result->fetch_assoc()) 
	{
		$i++;

		if($row["user_email"] != NULL)
		{
			$username = $row["username_clean"];
			$usernameHasSpace = strpos($username, " ");

			if($usernameHasSpace > 0)
				$formatedUsername = str_replace(" ", NULL, $username);
			else
				$formatedUsername = $username;

			$valuesStr = sprintf("\t(%d, '%s', '%s', '%s', '%s', 1)%s\n", $row["user_id"], $formatedUsername, $row["user_email"], sha1(md5(time())), $row['user_regdate'], $i == $totalUsers ? ";" : ",");
			fwrite($sqlScript, $valuesStr);
		}
		else
			$usersIgnored++;
	}
	echo $i-$usersIgnored . ' out of '. $totalUsers .' Total Users converted';
} 
else 
	echo "Something went wrong.";

fclose($sqlScript);

//Convert Categories to Tags
$sqlScript = fopen('flarum_tags.sql', 'w');

echo "<hr>Step 2 - Categories<hr>";
$result = $exportDbConnection->query("SELECT forum_id, forum_name, forum_desc  FROM phpbb_forums");
$totalCategories = $result->num_rows;
if ($totalCategories) 
{
	fwrite($sqlScript, "INSERT INTO tags (id, name, description, slug, color, position) VALUES \n");

	$i = 1;
	while($row = $result->fetch_assoc()) 
	{
		$slug = slugify($row["forum_name"]);
		$valuesStr = sprintf("\t(%d, '%s', '%s', '%s', '%s', %d)%s\n", $row["forum_id"], mysql_escape_mimic($row["forum_name"]), mysql_escape_mimic(strip_tags(stripBBCode($row["forum_desc"]))), mysql_escape_mimic($slug), rand_color(), $i, $i == $totalCategories ? ";" : ",");
		fwrite($sqlScript, $valuesStr);

		$i++;
	}
	echo $totalCategories . ' Categories converted.';
} 
else 
	echo "Something went wrong.";

fclose($sqlScript);


// Convert Topics to Discussions
$sqlScript_discussions = fopen('flarum_discussions.sql', 'w'); //add last post id / last post number?
$sqlScript_discussions_tags = fopen('flarum_discussions_tags.sql', 'w');

echo "<hr>Step 3 - Topics<hr>";
$topicsQuery = $exportDbConnection->query("SELECT topic_id, topic_poster, forum_id, topic_title, topic_time FROM phpbb_topics ORDER BY topic_id DESC;");
$topicCount = $topicsQuery->num_rows;

if($topicCount) 
{
	$curTopicCount = 0;
	$insertString = "INSERT INTO posts (id, user_id, discussion_id, time, type, content) VALUES \n";

	fwrite($sqlScript_discussions, "INSERT INTO discussions (id, title, start_time, comments_count, participants_count, start_post_id, last_post_id, start_user_id, last_user_id, last_time) VALUES \n");
	fwrite($sqlScript_discussions_tags, "INSERT INTO discussions_tags (discussion_id, tag_id) VALUES \n");
	
	//	Loop trough all PHPBB topics
	while($topic = $topicsQuery->fetch_assoc()) 
	{
		//	Convert posts per topic
		$participantsArr = [];
		$lastPosterID = 0;

		$sqlQuery = sprintf("SELECT * FROM phpbb_posts WHERE topic_id = %d;", $topic["topic_id"]);
		$postsQuery = $exportDbConnection->query($sqlQuery);
		$postCount = $postsQuery->num_rows;
		
		if($postCount)
		{
			$curPost = 0;

			//fwrite($sqlScript_posts, $insertString);
			while($post = $postsQuery->fetch_assoc()) 
			{
				$curPost++;

				$posterID = 0;
				$date = new DateTime();
				$date->setTimestamp($post["post_time"]);
				$postDate =  $date->format('Y-m-d H:i:s');
				$postText = formatText($exportDbConnection, $post['post_text']);

				if(empty($post['post_username']))// If the post_username field has text it means it's a "ghost" post. Therefore we should set the poster id to 0 so Flarum knows it's an invalid user
				{
					$posterID = $post['poster_id'];

					// Add to the array only if unique
					if(!in_array($posterID, $participantsArr))
						$participantsArr[] = $posterID;
				}

				if($curPost == $postCount)// Check if it's the last post in the discussion and save the poster id
					$lastPosterID = $posterID;

				// Write post values to SQL Script
				//fwrite($sqlScript_posts, sprintf("\t(%d, %d, %d, '%s', 'comment', '%s')%s\n", $post['post_id'], $posterID, $topic['topic_id'], $postDate, $postText, $curPost != $postCount ? "," : ";"));

				// Execute the insert query in the desired database.
				$formattedValuesStr = sprintf("(%d, %d, %d, '%s', 'comment', '%s');", $post['post_id'], $posterID, $topic['topic_id'], $postDate, $postText);
				$importDbConnection->query($insertString . $formattedValuesStr);
			}
		}
		//else
		//	echo "<br>Topic ". $topic['topic_id'] ." has zero posts.<br>";

		//	Convert topic to Flarum format 
		//
		//	This needs to be done at the end because we need to get the post count first
		//		
		$date = new DateTime();
		$date->setTimestamp($topic["topic_time"]);
		$discussionDate = $date->format('Y-m-d H:i:s');
		$topicTitle = $exportDbConnection->real_escape_string($topic["topic_title"]);

		// Link Discussion/Topic to a Tag/Category
		fwrite($sqlScript_discussions_tags, sprintf("\t(%d, %d),\n", $topic["topic_id"], $topic["forum_id"]));

		// Check for parent forums
		$parentForum = $exportDbConnection->query("SELECT parent_id FROM phpbb_forums WHERE forum_id = " . $topic["forum_id"]);
		$result = $parentForum->fetch_assoc();
		if($result['parent_id'] > 0)
			fwrite($sqlScript_discussions_tags, sprintf("\t(%d, %d),\n", $topic["topic_id"], $result['parent_id']));

		if($lastPosterID == 0)// Just to make sure it displays an actual username if the topic doesn't have posts? Not sure about this.
			$lastPosterID = $topic["topic_poster"];

		// id, title, start_time, comments_count, participants_count, start_post_id, last_post_id, start_user_id, last_user_id, last_time
		$valuesStr = sprintf("\t(%d, '%s', '%s', %d, %d, %d, %d, %d, %d, '%s'),\n", $topic["topic_id"], $topicTitle, $discussionDate, $postCount, count($participantsArr), 1, 1, $topic["topic_poster"], $lastPosterID, $discussionDate);
		
		fwrite($sqlScript_discussions, $valuesStr);
	}
} 

//fclose($sqlScript_posts);
fclose($sqlScript_discussions_tags);
fclose($sqlScript_discussions);

// Convert user posted topics to user discussions?
$sqlScript = fopen('flarum_user_discussions.sql', 'w');
echo "<hr>Last Step - User Discussions<hr/>";
$result = $exportDbConnection->query("SELECT user_id, topic_id FROM phpbb_topics_posted");
if ($result->num_rows > 0) 
{	
	$total = $result->num_rows;
	fwrite($sqlScript, "INSERT INTO users_discussions (user_id, discussion_id) VALUES ");

	$i = 1;
	while($row = $result->fetch_assoc()) {
		$comma =  $i == $total ? ";" : ",";
		fwrite($sqlScript, "	(".($row["user_id"]).", ".($row["topic_id"]).")".$comma."\n");
		$i++;
	}

	echo "Success";
} 
else 
	echo "Table is empty";

// Close connection to the database
$exportDbConnection->close();
$importDbConnection->close();

// Functions
function print_r2($val)
{
	echo '<pre>';
	print_r($val);
	echo  '</pre>';
}

function slugify($text)
{
	$text = preg_replace('~[^\\pL\d]+~u', '-', $text);
	$text = trim($text, '-');
	$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
	$text = strtolower($text);
	$text = preg_replace('~[^-\w]+~', '', $text);

	if (empty($text))
		return 'n-a';

	return $text;
}

function rand_color() 
{
	return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}

function mysql_escape_mimic($inp) 
{
	if(is_array($inp))
			return array_map(__METHOD__, $inp);

	if(!empty($inp) && is_string($inp)) {
			return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
	}
	return $inp;
}

// Formats PHPBB's text to Flarum's text format
function formatText($connection, $text)
{
	$text = preg_replace('#\:\w+#', '', $text);
	$text = convertBBCodeToHTML($text);
	$text = str_replace("&quot;","\"",$text);
	$text = preg_replace('|[[\/\!]*?[^\[\]]*?]|si', '', $text);
	$text = trimSmilies($text);

	// Wrap text lines with paragraph tags
	$explodedText = explode("\n", $text);
	foreach ($explodedText as $key => $value)
	{
		if(strlen($value) > 1)// Only wrap in a paragraph tag if the line has actual text
			$explodedText[$key] = '<p>' . $value . '</p>';
	}
	$text = implode("\n", $explodedText);

	$wrapTag = strpos($text, '&gt;') > 0 ? "r" : "t";// Posts with quotes need to be 'richtext'
	$text = sprintf('<%s>%s</%s>', $wrapTag, $text, $wrapTag);

	return $connection->real_escape_string($text);
}

// Used to convert Categories to Tags
function stripBBCode($text_to_search) {
    $pattern = '|[[\/\!]*?[^\[\]]*?]|si';
    $replace = '';
    return preg_replace($pattern, $replace, $text_to_search);
}

function convertBBCodeToHTML($bbcode)
{
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

function trimSmilies($postText)
{
	$startStr = "<!--";
	$endStr = 'alt="';

	$startStr1 = '" title';
	$endStr1 = " -->";

	$emoticonsCount = substr_count($postText, '<img src="{SMILIES_PATH}');

	for ($i=0; $i < $emoticonsCount; $i++) 
	{ 
		$startPos = strpos($postText, $startStr);
		$endPos = strpos($postText, $endStr);

		$postText = substr_replace($postText, NULL, $startPos, $endPos-$startPos+strlen($endStr));

		$startPos1 = strpos($postText, $startStr1);
		$endPos1 = strpos($postText, $endStr1);

		$postText = substr_replace($postText, NULL, $startPos1, $endPos1-$startPos1+strlen($endStr1));
	}

	return $postText;
}
?>
