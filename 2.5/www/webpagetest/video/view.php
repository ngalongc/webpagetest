<?php
chdir('..');
include 'common.inc';
$id = $_REQUEST['id'];
$valid = false;
$done = false;
$embed = false;
if( $_REQUEST['embed'] )
{
    $embed = true;
    header('Last-Modified: ' . date('r'));
    header('Expires: '.gmdate('r', time() + 31536000));
}

$page_keywords = array('Video','comparison','Webpagetest','Website Speed Test');
$page_description = "Side-by-side video comparison of website performance.";

$xml = false;
if( !strcasecmp($_REQUEST['f'], 'xml') )
    $xml = true;
$json = false;
if( !strcasecmp($_REQUEST['f'], 'json') )
    $json = true;

$ini = null;
$title = "WebPagetest - Visual Comparison";

$dir = GetVideoPath($id, true);
if( is_dir("./$dir") )
{
    $valid = true;
    $ini = parse_ini_file("./$dir/video.ini");
    if( isset($ini['completed']) )
    {
        $done = true;
        GenerateVideoThumbnail("./$dir");
    }
    
    // get the video time
    $date = date("M j, Y", filemtime("./$dir"));
    if( is_file("./$dir/video.mp4")  )
        $date = date("M j, Y", filemtime("./$dir/video.mp4"));
    $title .= " - $date";

    $labels = json_decode(file_get_contents("./$dir/labels.txt"), true);
    if( count($labels) )
    {
        $title .= ' : ';
        foreach($labels as $index => $label)
        {
            if( $index > 0 )
                $title .= ", ";
            $title .= $label;
        }
    }
}

if( $xml || $json )
{
    $error = "Ok";
    if( $valid )
    {
        if( $done )
        {
            $ret = 200;

            $host  = $_SERVER['HTTP_HOST'];
            $uri   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $videoUrl = "http://$host$uri/download.php?id=$id";
        }
        else
            $ret = 100;
    }
    else
    {
        $ret = 400;
        $error = "Invalid video ID";
    }
}

if( $xml )
{
    header ('Content-type: text/xml');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<response>\n";
    echo "<statusCode>$ret</statusCode>\n";
    echo "<statusText>$error</statusText>\n";
    if( strlen($_REQUEST['r']) )
        echo "<requestId>{$_REQUEST['r']}</requestId>\n";
    echo "<data>\n";
    echo "<videoId>$id</videoId>\n";
    if( strlen($videoUrl) )
        echo '<videoUrl>' . htmlspecialchars($videoUrl) . '</videoUrl>\n';
    echo "</data>\n";
    echo "</response>\n";
}
elseif( $json )
{
    $ret = array();
    $ret['statusCode'] = $ret;
    $ret['statusText'] = $error;
    if( strlen($_REQUEST['r']) )
        $ret['requestId'] = $_REQUEST['r'];
    $ret['data'] = array();
    $ret['data']['videoId'] = $id;
    if( strlen($videoUrl) )
        $ret['data']['videoUrl'] = $videoUrl;
    header ("Content-type: application/json");
    echo json_encode($ret);
}
else
{
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <title><?php echo $title;?></title>
        <?php
        if( $valid && !$done && !$embed )
        {
            ?>
            <noscript>
            <meta http-equiv="refresh" content="10" />
            </noscript>
            <script language="JavaScript">
            setTimeout( "window.location.reload(true)", 10000 );
            </script>
            <?php
        }
        ?>
        <?php 
            if( !$embed )
            {
                $gaTemplate = 'Video'; 
                include ('head.inc'); 
            }
        ?>
        <style type="text/css">
            div.content
            {
                text-align:center;
                background-color: black;
                color: white;
                font-family: arial,sans-serif
            }
            .link
            {
                text-decoration: none;
                color: white;
            }
            #player
            {
                margin-left: auto;
                margin-right: auto;
            }
            <?php
            if( $embed )
                echo 'body {background-color: black;}';
            ?>
        </style>
        <script type="text/javascript" src="/video/player/flowplayer-3.2.6.min.js"></script>
    </head>
    <body>
        <div class="page">
            <?php
            if( !$embed )
            {
                $tab = 'Test Result';
                $videoId = $id;
                $nosubheader = true;
                include 'header.inc';
            }

            if( $valid && ($done || $embed) )
            {
                $width = 800;
                $height = 600;

                $hasThumb = false;
                if( is_file("./$dir/video.png") )
                {
                    $hasThumb = true;
                    list($width, $height) = getimagesize("./$dir/video.png");
                }
                
                if( $_REQUEST['width'] )
                    $width = (int)$_REQUEST['width'];
                if( $_REQUEST['height'] )
                    $height = (int)$_REQUEST['height'];
                    
                echo '<div';
                echo " style=\"display:block; width:{$width}px; height:{$height}px\"";
                echo " id=\"player\">\n";
                echo "</div>\n";

                // embed the actual player
                ?>
                <script>
                    flowplayer("player", 
                                    {
                                        src: "/video/player/flowplayer-3.2.7.swf",
                                        cachebusting: false,
                                        version: [9, 115]
                                    } , 
                                    { 
                                        clip:  { 
                                            scaling: "fit"
                                        } ,
                                        playlist: [
                                            <?php
                                            if( $hasThumb )
                                            {
                                                echo "{ url: '/$dir/video.png'} ,\n";
                                                echo "{ url: '/$dir/video.mp4', autoPlay: false, autoBuffering: false}\n";
                                            }
                                            else
                                                echo "{ url: '/$dir/video.mp4', autoPlay: false, autoBuffering: true}\n";
                                            ?>
                                        ],
                                        plugins: {
                                            controls: {
                                                volume:false,
                                                mute:false,
                                                stop:true,
                                                tooltips: { 
                                                    buttons: true, 
                                                    fullscreen: 'Enter fullscreen mode' 
                                                } 
                                            }
                                        } ,
                                        canvas:  { 
                                            backgroundColor: '#000000', 
                                            backgroundGradient: 'none'
                                        }
                                    }
                                ); 
                </script>
                <?php                
                if(!$embed)
                    echo "<br><a class=\"link\" href=\"/video/download.php?id=$id\">Click here to download the video file...</a>\n";
            }
            elseif( $valid && !$embed )
                echo '<h1>Your video will be available shortly.  Please wait...</h1>';
            elseif($embed)
                echo '<h1>The requested video does not exist.</h1>';
            else
                echo '<h1>The requested video does not exist.  Please try creating it again and if the problem persists please contact us.</h1>';
            ?>
            
            <?php 
                if (!$embed)
                    include('footer.inc'); 
            ?>
        </div>
    </body>
</html>

<?php
}
?>