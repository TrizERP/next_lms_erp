<!--<OBJECT ID="MediaPlayer" WIDTH=320 HEIGHT=42 CLASSID="CLSID:22D6F312-B0F6-11D0-94AB-0080C74C7E95" STANDBY="Loading Windows Media Player components...">
    <PARAM NAME="<?php echo $_GET[url]; ?>" VALUE="<?php  echo $_GET[url]; ?>">
    <EMBED TYPE="application/x-mplayer2" SRC="<?php echo $_GET[url]; ?>" NAME="MediaPlayer" WIDTH=320 HEIGHT=42>
    </EMBED>
 <embed id="mymovie" width="545" height="400" quality="high" bgcolor="#000000" name="mymovie" src="http://www.mathtv.com/videos_by_topic##" type="application/x-shockwave-flash"/>
    </OBJECT>-->
<?php
$url = $_GET['url'];
$rel=0;
$return = 'thumb';
    $urls = parse_url($url);
    
    //expect url is http://youtu.be/abcd, where abcd is video iD
    if($urls['host'] == 'youtu.be'){ 
        $id = ltrim($urls['path'],'/');
    }
    //expect  url is http://www.youtube.com/embed/abcd
    else if(strpos($urls['path'],'embed') == 1){ 
        $id = end(explode('/',$urls['path'])) ;
    }
     //expect url is abcd only
    else if(strpos($url,'/')===false){
        $id = $url;
    }
    //expect url is http://www.youtube.com/watch?v=abcd
    else{
        parse_str($urls['query']);
        $id = $v;
    }
    //return embed iframe
    if($return == 'embed'){
        $linkset = '<iframe width="'.($width?$width:560).'" height="'.($height?$height:349).'" src="http://www.youtube.com/embed/'.$id.'?rel='.$rel.'" frameborder="0" allowfullscreen>';
    }
    //return normal thumb
    else if($return == 'thumb'){
        $linkset = 'http://i1.ytimg.com/vi/'.$id.'/default.jpg';
    }
    //return hqthumb
    else if($return == 'hqthumb'){
        $linkset = 'http://i1.ytimg.com/vi/'.$id.'/hqdefault.jpg';
    }
    // else return id
    else{
        $linkset = $id;
    }
$newlink = 'http://www.youtube.com/v/'.$id;
//$newlink = 'http://www-tc.pbs.org/video/media/swf/PBSPlayer.swf?20099';
//echo '<iframe width="'.($width?$width:560).'" height="'.($height?$height:349).'" src="http://www.youtube.com/embed/'.$id.'?rel='.$rel.'" frameborder="0" allowfullscreen>';

?>
<object width="425" height="350" data="<?php echo $newlink; ?>" type="application/x-shockwave-flash"><param name="src" value="<?php echo $newlink; ?>" /></object>
