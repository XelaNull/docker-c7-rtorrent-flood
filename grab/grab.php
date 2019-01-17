<?php
include "sanitize.php";

if($argv[1]!='') // a CLI argument was passed, so the script was called from the command line
  {
    $parts=explode(':',$argv[1]);$IP=$parts[0];$PORT=$parts[1];
  }
elseif($_GET['IPPORT']!='')
  {
    $parts=explode(':',htmlspeciachars($_GET['IPPORT'])); $IP=$parts[0];$PORT=$parts[1];    
  }
else 
  {
  echo "No IP:PORT provided. You can either provide this as a CLI argument or an HTTP GET variable.\n";
  echo "Example: php grab.php 111.222.333.444:8080\n";
  echo "Example: http://YOURIP/grab.php?IPPORT=111.222.333.444:8080\n\n";
  echo "This script will download the remote torrent data into the current directory that the script is running from."
  }

// If you wish this script to store the downloaded torrent data to a different directory, specify it here
$PREPEND_PATH='./';

// There generally should not be a reason to modify anything below.

// Download the list of remote URL stored by rTorrent+Flood
$HTML=file_get_contents("http://$IP:$PORT/scan.php");
// Convert the list to an array that we can loop through
$URL_List=explode(PHP_EOL,str_replace('<br/>','',$HTML));
echo "Looping through ".count($URL_List)." URLs...\n";
$count=0;
// Loop through the list of URLs to download
foreach($URL_List as $URL)
{ if($URL=='') continue; $count++;
  // Determine just the base filename (ignoring the URL path)
  $name=basename($URL); 
  // Determine the file extension
  $extension = substr($name,strlen($name)-4,4);
  // Determine the filename without the extension
  $name = substr($name,0,strlen($name)-4);
  // Call sanitizeName to calculate new name
  $calculatedName=sanitizeName($name); $dirName=$calculatedName;
  echo "$count of ".count($URL_List)." ";
  // Display the name returned by sanitizeName()
  //echo "\tGUESS: ".$calculatedName." [$extension]\n";

  // Determine if this is a TV Episode, so we can remove season and episode from dirname
  if(preg_match("'^(.+)S([0-9]+)E([0-9]+)*'i",$dirName,$n))
    {
      // This *IS* a TV episode, so we should remove the Season and Episode from the directory name
      $seasonepisode_string=trim(substr($n[0],strrpos($n[0],' ')));
      $dirName=str_replace(" $seasonepisode_string","",$dirName);
    }
  // If the directory does not already exist, we should create it
  if(!file_exists("$dirName")) { echo "\tMKDIR: $dirName\n"; mkdir("$dirName"); }
    
  // Display the directory and name that we will be writing this out to locally
  //echo "\tDEST: $dirName/$calculatedName$extension\n";
  
  // Display the exact wget call being made (which shows the local filepath)
  echo "wget -c -O \"$PREPEND_PATH$dirName/$calculatedName$extension\" \"$URL\"\n";
  // Make the system call to wget with -c option, to make it more rsync-like
  // This command also writes out the file to its new directory and filename. 
  system("wget -c -O \"$PREPEND_PATH$dirName/$calculatedName$extension\" \"$URL\"");
  // Display how many MB we've written locally for this file
  echo "\tWROTE: ".number_format((filesize("$dirName/$calculatedName$extension"))/1024/1024)."MB\n";
}
echo "Completed looping through $count URLs..\n"




/*
function calculateName($currentName)
{
  global $DEBUG, $REMOVE_WORDS, $STRIP_AFTER_WORDS;
  defineConfig(); $sanitizedName=$currentName;
  
  @$space_count=substr_count($sanitizedName, ' ');
  @$period_count=substr_count($sanitizedName, '.');
  
  // We need to loop through STRIP_AFTER_WORDS before we convert . to spaces
  //   otherwise, the filter matching won't work properly
  $word_position='';
  // Strip the name after any of the following words
  foreach($STRIP_AFTER_WORDS as $word)
    {
      if(strpos(strtolower($sanitizedName),strtolower($word))!==FALSE) 
        {
          $word_position=strpos(strtolower($sanitizedName),strtolower($word));
          $sanitizedName=substr($sanitizedName,0,$word_position);
          cleaner_log("calculateName0.5: STRIP-at-WORD: [$word]; RESULT: [$sanitizedName]",2);
        }
    }
  cleaner_log("calculateName1: sanitizedName[$sanitizedName]",3);  
  
  // If there are no spaces in the name, convert periods to spaces
  if ($period_count>$space_count && $period_count>1) 
    { $sanitizedName=str_replace('.',' ',$sanitizedName); cleaner_log("NOSPACES-REMOVED: [$sanitizedName]\n",2); }
  cleaner_log("calculateName2: sanitizedName[$sanitizedName]",3);
  
  /*
  // We should look for the TV indicator of S__E__ or s__e__
  $season=''; $episode=''; $seasonepisode_string='';
  if(preg_match("'^(.+)S([0-9]+)E([0-9]+)*'i",$sanitizedName,$n))
    {
      $last_space_position=strrpos($n[0],' '); 
      $seasonepisode_string=substr($n[0],$last_space_position);
      $season=$n[2]; $episode=$n[3];
      cleaner_log("calculateName0: TV EPISODE DETECTED1: S[$season]E[$episode] name:[$seasonepisode_string] sanitizedName: $sanitizedName",2);

    }
  if ($season!='' && $episode!='')
  {
    $movieformat='TV';
    $strip_from_position=strpos($sanitizedName,$seasonepisode_string);
    $sanitizedName=substr($sanitizedName,0,$strip_from_position);
    cleaner_log("calculateName0: TV EPISODE DETECTED2: S[$season]E[$episode] name:[$seasonepisode_string] sanitizedName: $sanitizedName",2);
  }
  */
  
  /*
  
  // Remove characters that shouldn't be presen in a name, and replace with space
  $sanitizedName=removeCharacters($sanitizedName);
  cleaner_log("calculateName3: periods:$period_count spaces:$space_count sanitizedName[$sanitizedName]",3);
                        
  // Remove any double spaces and then trim the filename
  while(strpos($sanitizedName,'  ')!==false) $sanitizedName=str_replace('  ',' ',$sanitizedName); # Remove any double spaces
  
  // Remove bad words   
  foreach($REMOVE_WORDS as $word) $sanitizedName=trim(str_replace($word,' ',$sanitizedName));
  cleaner_log("calculateName4: sanitizedName[$sanitizedName]",3);
  
  return(trim($sanitizedName));

  // Check with IMDB Name, then rebuild Filename from IMDB Name + IMDB Year + Resolution
  $calculatedName=queryIMDB_Loop($sanitizedName,$currentName);
  cleaner_log("calculateName5: calculatedName[$calculatedName]",3);
  
  if(!format_present($calculatedName) && format_present($currentName)!='')
    {
      $resolution=format_present($currentName);
      if($resolution!='') $calculatedName=trim($calculatedName).$seasonepisode_string.' '.trim($resolution);
    }
  
  return(trim($calculatedName));
}

function removeCharacters($in)
{
  // Remove characters that shouldn't be presen in a filename, and replace with space
  $rtn=str_replace('  ',' ',str_replace(str_split('\\/*?"<>|[]()_|'),'',$in)); return($rtn);  
}

function cleaner_log($LOG_MESSAGE, $LOG_LEVEL)
{
  global $DEBUG; $LOG_LEVEL_TXT='';  
  switch($LOG_LEVEL)
  {
    case "-3": Dsyslog(LOG_CRIT, 'cleaner: CRIT - '.$LOG_MESSAGE); break;
    case "-2": Dsyslog(LOG_ERR, 'cleaner: ERR - '.$LOG_MESSAGE); break;
    case "-1": Dsyslog(LOG_WARNING, 'cleaner: WARN - '.$LOG_MESSAGE); break;
    case "0": Dsyslog(LOG_INFO, 'cleaner: INFO - '.$LOG_MESSAGE); break;    
  }
  if($LOG_LEVEL>0 && $DEBUG>=$LOG_LEVEL) Dsyslog(LOG_DEBUG, "cleaner: DEBUG$LOG_LEVEL - $LOG_MESSAGE");
}

function Dsyslog($Level, $Msg) { echo $Msg."\n"; return; }

function defineConfig()
{
  global $DEBUG, $TITLE_ACCEPTABLE_SIMILARITY_PERCENT, $FILE_ACCEPTABLE_SIMILARITY_PERCENT, $MATCHES_TO_SEARCH;
  global $KNOWN_MOVIE_FILE_EXTENSIONS, $KNOWN_SUBTITLE_FILE_EXTENSIONS, $STRIP_AFTER_WORDS;
  global $KNOWN_EXTENSIONS, $movieFormats, $REMOVE_WORDS, $titleMatch_Array, $yearMatch_Array;
  
  $DEBUG=0;
  $TITLE_ACCEPTABLE_SIMILARITY_PERCENT=84;
  $FILE_ACCEPTABLE_SIMILARITY_PERCENT=60;
  $MATCHES_TO_SEARCH=5;

  $KNOWN_MOVIE_FILE_EXTENSIONS = array('img','mp4','avi','mkv','m2ts','wmv','iso','divx','mpg','m4v');
  $KNOWN_SUBTITLE_FILE_EXTENSIONS = array('sub','idx','srt');
  $KNOWN_EXTENSIONS = array_merge($KNOWN_MOVIE_FILE_EXTENSIONS,$KNOWN_SUBTITLE_FILE_EXTENSIONS);
  $movieFormats=array('2160p','1080p','720p','480p','360p','288p','240p');
  # Case insensitive
  $STRIP_AFTER_WORDS=array('HDR','bluray', 'DVDRip', 'bdrip', 'divx', 'internal', 'repack', 'proper', 
    'Dvd', 'limited', 'xvid', 'AC3', 'HDRip', 'HDTV', 'DVDScr', 'WEBRip', '10bit', 'hevc', 
    'HDTVRip', 'DVDR', 'WEBrip', 'HC', '.MULTI', 'PL.DUAL', 'WEB-DL','.V2','.web','AMZN','REMUX',
    '.REAL');
  # Case sensitive
  $REMOVE_WORDS=array('DVDRip','BDRip','BRRip','BRrip','BRRIP','HDRip','H264','x264-x0r','x264','h264',
    'X264','XviD','XviDHD','Xvid','XViD', 'DC', '5.1', 'AAC','HDR',
    'LIMITED','BluRay','UNCUT','RECUT','VORBIS','IMAX','Bluray',
    'DiAMOND', 'iNT', 'dmd',  'x0r', "\t", 'ureshii', 'vRs', 'amiable',
    'AC3-EVO--directshow','AC3-DiVERSiTY','AC3-EVO','AC3','EVO','WEB DL','V2.WEB-DL','WEB-DL','directshow',
    'DD5.1','DD5 1','DD5', 'remastered', 'CF', 'DVDSCR',
     'CMRG', 'DiVERSiTY', 'HD TS', 'ETRG', 'Extended Edition', 'Blu Ray', 'mHD', ' .',
     'uhd-d3g', '7.1','TrueHD','H265-d3g', 'UHD','AC-3', 'DVD5', 'BF41C57D', 'bdmf', 'lrc',
     'x265-MZABI', '. .', '. -', '  ', '  '
   ); 
  $regex_characters_accepted=' a-zA-z0-9&:\'\-,.éÆä³·//*!"';
  $titleMatch_Array=array(
    "|(?<=og:title' content=\")[$regex_characters_accepted]*.(?=\()|",
    "|(?<=&quot;)[$regex_characters_accepted]*.(?=\()|",
    "|(?<=&quot;)[$regex_characters_accepted].*?(?=&quot)|"
  );
  $yearMatch_Array=array(
    "/(?<=year\/)[0-9]{4,}/", 
    "/(?<=year=)[0-9]{4,}/",
    "/(?<=\()[0-9]{4,}(?=\))/"
  );  
}

function queryIMDB_Loop($simpleFilename,$filePath) 
{
global $DEBUG,$movieFormats;
$result=''; $first_word=''; $last_word=''; $query_strings='';
$origFilename=$simpleFilename;

cleaner_log("queryIMDB_Loop: Before Attempt #1",2);

$query_strings="\"$simpleFilename\" "; $result=queryIMDB($simpleFilename); // ATTEMPT #1

cleaner_log("queryIMDB_Loop: After Attempty #1",2);
if (@strpos($result,'UNKNOWN')!==FALSE) // ATTEMPT #2
  {
  $last_space_position=strrpos($simpleFilename, ' '); $last_word=trim(substr($simpleFilename, $last_space_position));
  $tempFilename=trim(substr($simpleFilename,0,$last_space_position));
  if ($tempFilename != '') { $query_strings.="\"$tempFilename\" "; $result=queryIMDB($tempFilename); }
  }
cleaner_log("queryIMDB_Loop: After Attempty #2",2);
if (strpos($result,'UNKNOWN')!==FALSE) // ATTEMPT #3
  {
    $first_space_position=strpos($tempFilename, ' '); $first_word=trim(substr($tempFilename, 0, $first_space_position));
    $tempFilename=trim(substr($tempFilename,$first_space_position));
    if ($tempFilename != '') { $query_strings.="\"$tempFilename\" "; $result=queryIMDB($tempFilename); }
  }
cleaner_log("queryIMDB_Loop: After Attempty #3",2);
// Reformat the result
if (strpos($result,'UNKNOWN')===FALSE) 
  {
   if($last_word!='' && !is_numeric($last_word)) $simpleFilename=trim(removeCharacters($result).' '.$last_word);
   elseif($first_word!='') $simpleFilename=$first_word.' '.removeCharacters($result);  
   else $simpleFilename=$first_word.' '.removeCharacters($result);
  }
else { cleaner_log("!!!!!!!!!!IMDB FAILURE [$simpleFilename]!!!!!!!!",-1); cleaner_log("$origFilename",-3); }
cleaner_log("queryIMDB_Loop Result: $query_strings = $result<br>\n",1); return(trim($simpleFilename));  
}


function queryIMDB($title,$year=NULL,$type='feature',$loopcount=NULL)
{
  global $DEBUG, $TITLE_ACCEPTABLE_SIMILARITY_PERCENT;
  defineConfig(); $loopcount++;
  if($type!='feature' || $type!='tv') $type='feature';
  
  cleaner_log("queryIMDB1: $title | year: [$year]",3);

  
  // Attempt to extract the year from the title string, if it was not provided separately
//  if($year=='') { preg_match("/[0-9][0-9](91|02)*./",strrev($title),$Match); if(@$Match[0]!='') { $year=trim(strrev($Match[0])); } }
  if($year=='') { preg_match("/[0-9][0-9][0-9][0-9]*./",strrev($title),$Match); if(@$Match[0]!='') { $year=trim(strrev($Match[0])); } }
  
  cleaner_log("queryIMDB2: $title | year: [$year]",3);
  
  # If there are two years present in the title name, we can assume the last is the one we want to remove. 
  # In this rare situation, we should only remove the last year.
  preg_match_all("/[0-9][0-9](91|02)*./",strrev($title),$Match); $year_count=count($Match[0]);
  if($year_count==1) $title=preg_replace("/ (19|20)[0-9][0-9]/",'',$title); # Remove the year from the Title
  else $title=strrev(preg_replace('/'.preg_quote(strrev($year), '/').'/', '', strrev($title), 1)); # Remove the year from the Title

  cleaner_log("queryIMDB: $title",3);

  $IMDBid=''; $ENCODED_TITLE=urlencode($title);
  # TITLE + RELEASE_YEAR + TITLE_TYPE
  if($IMDBid=='' && $year!='' && $year>1900 && $year<=date('Y')) $IMDBid=obtainIMDBid($title,$year,"https://www.imdb.com/search/title?title=".$ENCODED_TITLE."&release_date=$year&title_type=$type");
  # TITLE + RELEASE_YEAR
  if($IMDBid=='' && $year!='' && $year>1900 && $year<=date('Y')) $IMDBid=obtainIMDBid($title,$year,"https://www.imdb.com/search/title?title=".$ENCODED_TITLE."&release_date=$year");
  # TITLE + TITLE_TYPE
  if($IMDBid=='') $IMDBid=obtainIMDBid($title,$year,"https://www.imdb.com/search/title?title=".$ENCODED_TITLE."&title_type=$type");
  # Attempt to swap out 'and' and '&' to see if there is simply a typo in the name
  if($IMDBid=='' && strpos($title,'and')!==FALSE && $loopcount<2) { $IMDBid=queryIMDB(str_replace('and','&',$title),$year,$type,$loopcount); if($IMDBid=='UNKNOWN') $IMDBid=''; }
  if($IMDBid=='' && strpos($title,'&')!==FALSE && $loopcount<2) { $IMDBid=queryIMDB(str_replace('&','and',$title),$year,$type,$loopcount); if($IMDBid=='UNKNOWN') $IMDBid=''; }
  # TITLE ONLY
  if($IMDBid=='') $IMDBid=obtainIMDBid($title,$year,"https://www.imdb.com/search/title?title=".$ENCODED_TITLE);
  # GOOGLE SEARCH
  //if($IMDBid=='') $IMDBid=obtainIMDBid($title,$year,"http://www.google.com/search?q=site:imdb.com+%22".$ENCODED_TITLE."%22&btnI");
  
  if(is_array($IMDBid)) 
    { 
      //if($IMDBid[3]<$TITLE_ACCEPTABLE_SIMILARITY_PERCENT) cleaner_log("$title|IMDBid Certainty|$IMDBid[3]%|",1);
      cleaner_log("queryIMDB: \"$title\" IMDBid Certainty| $IMDBid[3]%",1);            
      return("$IMDBid[1] $IMDBid[2]"); 
    }
  elseif($IMDBid!='' && $IMDBid!='UNKNOWN') { cleaner_log("queryIMDB: \"$title\" IMDBid Return on Previous Match",1); return($IMDBid); }
  else { return("UNKNOWN"); }
}


function getURL($URL)
{
  global $DEBUG;
  $shortenedURL=str_replace("http://www.imdb.com/title/",'',$URL);
  $shortenedURL=str_replace('https://www.imdb.com/search/title?title=','',$shortenedURL);
  //$shortenedURL=str_replace('http://www.google.com/search?q=site:imdb.com+%22','',$shortenedURL);
  $shortenedURL=str_replace("%22&btnI",'',$shortenedURL);

  $cachedFilename="/tmp/imdb/$shortenedURL.dat";
  if(!file_exists($cachedFilename))
    { cleaner_log("NOCACHE: [$cachedFilename]",2);
      $HTML=file_get_contents($URL); if($fp=fopen($cachedFilename,"w+")) { fputs($fp, $HTML); }
    }
  else 
    { cleaner_log("CACHEHIT: [$cachedFilename]\n",2);
      $fp=fopen($cachedFilename,"r"); while(!feof($fp)) $HTML.=fgets($fp,2048);
    }
  fclose($fp);
  return($HTML);
}


# Queries IMDB for a constructed URL, and then make an attempt at obtaining the IMDBid from the HTML source code
function obtainIMDBid($title,$year,$URL)
{
  global $DEBUG, $MATCHES_TO_SEARCH;
  $FULL_URL=$URL;
  $HTML=@getURL($URL); cleaner_log("obtainIMDBid getURL: $URL\n",2);
  
  # Parse the output looking for the title ID
  preg_match_all("/(?<=id=\")[a-z]{2}[0-9]{4,}(?=\|imdb)/",$HTML,$IMDBid2); 
  $Matches=count($IMDBid2[0]); if($MATCHES_TO_SEARCH>$Matches && $Matches>0) $MATCHES_TO_SEARCH=$Matches;
  # Loop through the search results and attempt to validate each IMDBid
  cleaner_log("obtainIMDBid: Matches: $Matches",2);
  for($cnt=0;$cnt<=($MATCHES_TO_SEARCH-1);$cnt++) { $tmp_IMDBid=@$IMDBid2[0][$cnt]; $IMDBid=verifyIMDBid($title,$year,$tmp_IMDBid); if($IMDBid!='') break; }
  if($IMDBid=='') return(FALSE); 
  else { cleaner_log("returning IMDBid MATCH! [$IMDBid[0]]\n",2);  return($IMDBid);}
}


function verifyIMDBid($title,$year,$IMDBid)
{
  global $DEBUG, $TITLE_ACCEPTABLE_SIMILARITY_PERCENT, $titleMatch_Array, $yearMatch_Array;
  if($IMDBid=='') return(''); $Year=''; $Title='';
  $HTML=@getURL("http://www.imdb.com/title/".urlencode($IMDBid));
  
  # Magic code to extract out the Movie Title and Year from the HTML code
  foreach($yearMatch_Array as $regex) if($Year=='') { preg_match($regex, $HTML, $YearMatch); $Year=@$YearMatch[0]; }
  // Run through the Title regex's to try and find a match
  foreach($titleMatch_Array as $regex) if($Title=='') { preg_match($regex, $HTML, $TitleMatch); $Title=trim(@$TitleMatch[0]); }
  if($Title=='' || $Year=='') { cleaner_log("Title[$Title] or Year[$Year] Blank\n",2); return(''); }

  # Perform string comparison to determine how similar they are percentage-wise
  similar_text(strtolower($title), strtolower($Title), $percent_similar); $percent_similar=round($percent_similar,2);
  
  # We need to detect if a forward-slash is present in the Title and if so, lower the required similarity percent
  if(strpos($Title,'/')!==FALSE) $TITLE_ACCEPTABLE_SIMILARITY_PERCENT=$TITLE_ACCEPTABLE_SIMILARITY_PERCENT-10;
  if(substr_count($title,' ')<1) $TITLE_ACCEPTABLE_SIMILARITY_PERCENT=$TITLE_ACCEPTABLE_SIMILARITY_PERCENT-10;
  
  # We need to make sure the year we extract and the year taken from the filename match
  # If they do not match, that should be an immediate fail, as we should always assume a year provided 
  # in a filename is more accurate than a query against IMDB.
  if(strlen($year)==4 && strlen($Year)==4 && $year != $Year) { cleaner_log("YEARS DO NOT MATCH: File[$year] IMDBquery[$Year]: $title",1); return(''); }
  
  # If the percentage match is less than ______, then return NULL, otherwise return the same $IMDBid  
  if($percent_similar<$TITLE_ACCEPTABLE_SIMILARITY_PERCENT) { cleaner_log("Percent Not Similar[$percent_similar]<[$TITLE_ACCEPTABLE_SIMILARITY_PERCENT] on $title != $Title\n",2); return('');  }
  else { $rtn[0]=$IMDBid; $rtn[1]=$Title; $rtn[2]=$Year; $rtn[3]=$percent_similar; cleaner_log("MATCH[$percent_similar]! $title = $Title\n",2); return($rtn); }
}

function dotYear_present($name)
{
  global $STRIP_AFTER_WORDS;
  for($year=1900;$year<=date("Y");$year++) { if(strpos($name,'.'.$year)!==FALSE) { $STRIP_AFTER_WORDS[]=".$year"; return TRUE; } }
  return FALSE;  
}

function format_present($name)
{ global $movieFormats; foreach($movieFormats as $format) { if(strpos($name,$format)!==FALSE) return $format; } return FALSE; }



function downloadRemoteFile($url, $dest)
{
  $options = array(CURLOPT_FILE => is_resource($dest) ? $dest : fopen($dest, 'w'), CURLOPT_FOLLOWLOCATION => true, CURLOPT_URL => $url, CURLOPT_FAILONERROR => true);
  $ch = curl_init(); curl_setopt_array($ch, $options); $return = curl_exec($ch);
  if ($return === false) return curl_error($ch);
  else return true;
}
*/

?>