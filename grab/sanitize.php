<?php
function sanitizeName($currentName)
{
  $DEBUG=0;   
  # Case insensitive
  $STRIP_AFTER_WORDS=array('HDR','bluray','DVD','bdrip','divx','internal','repack', 
    'xvid','AAC','AC3','AC-3','HDR','BRRip','HDTV','10bit','hevc','WEBrip','HC','.MULTI','PL.DUAL',
    'WEB-DL','.V2','.web','AMZN','REMUX','.REAL','h264','h265','x264','x265','UHD','DD5');
  # Case sensitive
  $REMOVE_WORDS=array('DC','5.1','LIMITED','UNCUT','RECUT','VORBIS','IMAX',
    'DiAMOND','iNT','dmd','x0r',"\t",'ureshii','vRs','amiable',
    'EVO','WEB DL','V2.WEB-DL','directshow','remastered','CF','DVDSCR',
     'CMRG','DiVERSiTY','HD TS','ETRG','Extended Edition','Blu Ray','mHD', ' .',
     '7.1','TrueHD','BF41C57D', 'bdmf', 'lrc', '. .', '. -', '  ', '  '
   ); 
   $word_position=''; $sanitizedName=$currentName;

  // We need to loop through STRIP_AFTER_WORDS before we convert . to spaces
  //   otherwise, the filter matching won't work properly
  // Strip the name after any of the following words
  foreach($STRIP_AFTER_WORDS as $word)
    {
      if(strpos(strtolower($sanitizedName),strtolower($word))!==FALSE) 
        {
          $word_position=strpos(strtolower($sanitizedName),strtolower($word));
          $sanitizedName=substr($sanitizedName,0,$word_position);
          cleaner_log("sanitizeName: STRIP-at-WORD: [$word]; RESULT: [$sanitizedName]",1);
        }
    }
  cleaner_log("sanitizeName: [post-strip] sanitizedName[$sanitizedName]",2);  
  
  @$space_count=substr_count($sanitizedName, ' '); @$period_count=substr_count($sanitizedName, '.');
  // If there are no spaces in the name, convert periods to spaces
  if ($period_count>$space_count && $period_count>1) 
    { $sanitizedName=str_replace('.',' ',$sanitizedName); cleaner_log("sanitizeName: NOSPACES-REMOVED: [$sanitizedName]\n",2); }
  cleaner_log("sanitizeName: [post-space-convert] sanitizedName[$sanitizedName] periods:$period_count spaces:$space_count",3);
    
  // Remove characters that shouldn't be presen in a name, and replace with space
  $sanitizedName=str_replace('  ',' ',str_replace(str_split('\\/*?"<>|[]()_|'),'',$sanitizedName));
  cleaner_log("sanitizeName: [post-remove-chars] sanitizedName[$sanitizedName]",2);
                        
  // Remove any double spaces and then trim the filename
  while(strpos($sanitizedName,'  ')!==false) $sanitizedName=str_replace('  ',' ',$sanitizedName); # Remove any double spaces
  cleaner_log("sanitizeName: [post-remove-dblspace] sanitizedName[$sanitizedName]",2);
  
  // Remove bad words   
  foreach($REMOVE_WORDS as $word) 
  {
    if(strpos($sanitizedName,$word)!==FALSE)
    {
      $sanitizedName=trim(str_replace($word,' ',$sanitizedName));  
      cleaner_log("sanitizeName: REMOVE-WORD: [$word]; RESULT: [$sanitizedName]",1);
    }
  }
  cleaner_log("sanitizeName: [$sanitizedName]",1);
  return(trim($sanitizedName));
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
} function Dsyslog($Level, $Msg) { echo $Msg."\n"; return; }

?>