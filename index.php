<?php
# Used to separate multipart
$boundary = "my_boundary";

# Set this so PHP doesn't timeout during a long stream
set_time_limit(0);

# Disable PHP's compression of output to the client
ini_set('zlib.output_compression', 0);

# Set implicit flush, and flush all current buffers
ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++)
    ob_end_flush();
ob_implicit_flush(1);

# We start with the standard headers. PHP allows us this much
header("Cache-Control: no-cache");
header("Cache-Control: private");
header("Pragma: no-cache");
header("Content-type: multipart/x-mixed-replace; boundary=$boundary");

# From here out, we no longer expect to be able to use the header() function
print "--$boundary\n";

# Banner for some situations
function banner($info) {
    global $boundary;
    $font_size = 40;
    global $font;
    print "Content-type: image/jpeg\n\n";
    $im = imagecreatetruecolor(704, 576);
    $fill_color = imagecolorallocate($im, 50,100,200);
    imagefill($im,1,1,$fill_color);
    $text_color = imagecolorallocate($im, 255, 255, 255);
    $bbox = imagettfbbox($font_size, 0, $font, 'НЕТ СИГНАЛА');
    $x = $bbox[0] + (imagesx($im) / 2) - ($bbox[4] / 2) - 25;
    $y = $bbox[1] + (imagesy($im) / 2) - ($bbox[5] / 2) - 5;
    imagettftext($im, $font_size, 0, $x, $y - $font_size / 2, $text_color, $font, 'НЕТ СИГНАЛА' );
    $bbox = imagettfbbox($font_size * 0.7, 0, $font, $info);
    $x = $bbox[0] + (imagesx($im) / 2) - ($bbox[4] / 2) - 25;
    $y = $bbox[1] + (imagesy($im) / 2) - ($bbox[5] / 2) - 5;
    imagettftext($im, $font_size * 0.7, 0, $x, $y+$font_size / 2, $text_color, $font, $info );
    $dt = date("Y-m-d H:i:s");
    $bbox = imagettfbbox($font_size * 0.7, 0, $font, $dt);
    $x = $bbox[0] + (imagesx($im) / 2) - ($bbox[4] / 2) - 25;
    $y = $bbox[1] + (imagesy($im) / 2) - ($bbox[5] / 2) - 5;
    imagettftext($im, $font_size * 0.7, 0, $x, $y+$font_size * 1.5, $text_color, $font, $dt );
    imagejpeg($im);
    imagedestroy($im);
    print "--$boundary\n";
}

# Target file name detected from request.
$filename = basename($_SERVER["REQUEST_URI"]);
$file = "/var/www/html/video/upload/".basename($_SERVER["REQUEST_URI"],".mjpg")."/live.mjpg";
$dir = "/var/www/html/video/upload/".basename($_SERVER["REQUEST_URI"],".mjpg");
$font = './Arial_Bold.ttf';
$font_size = 100;
# The main loop
while (true) {

    # If no file, present banner
    while( !file_exists($file)) {
	banner("такой канал отсутствует");
	usleep(500000);
    }

    # if file exists, show it, and setup inotify

    print "Content-type: image/jpeg\n\n";
    $im = imagecreatefromjpeg($file);
    imagejpeg($im);
    imagedestroy($im);
    print "--$boundary\n";

    $in = inotify_init();
    stream_set_blocking($in, false);
    $wd = inotify_add_watch($in, $dir, IN_MOVED_TO);
    $to = 2;
    $i = 0;

    while( file_exists($file) ) {

	# Wait for a file change or timeout. Present banner or frame
	$r = array($in);
	$w = array();
	$e = array();
	$n = stream_select($r, $w, $e, $to);
	if( $n == 0 ) {
	    if( !file_exists($file)) {
		banner("такой канал отсутствует");
	    } else {
		# No updates detected, present last frame with flashy text
		$i++;
		print "Content-type: image/jpeg\n\n";
		$im = imagecreatefromjpeg($file);
		if( $i % 2) {
		    $text_color = imagecolorallocate($im, 255, 0, 0 );
		    $bbox = imagettfbbox($font_size, 0, $font, 'НЕТ СИГНАЛА' );
		    $x = $bbox[0] + (imagesx($im) / 2) - ($bbox[4] / 2) - 25;
		    $y = $bbox[1] + (imagesy($im) / 2) - ($bbox[5] / 2) - 5;
		    imagettftext($im, $font_size, 0, $x, $y - $font_size * 0.5, $text_color, $font, 'НЕТ СИГНАЛА' );
		    $bbox = imagettfbbox($font_size * 0.33, 0, $font, "последний кадр получен:" );
		    $x = $bbox[0] + (imagesx($im) / 2) - ($bbox[4] / 2) - 25;
		    $y = $bbox[1] + (imagesy($im) / 2) - ($bbox[5] / 2) - 5;
		    imagettftext($im, $font_size*0.33, 0, $x, $y + $font_size * 0.5, $text_color, $font, "последний кадр получен:" );
		    clearstatcache ( $clear_realpath_cache = true, $file );
		    $ft = date ("d m Y H:i:s", filectime($file));
		    $bbox = imagettfbbox($font_size * 0.33, 0, $font, $ft );
		    $x = $bbox[0] + (imagesx($im) / 2) - ($bbox[4] / 2) - 25;
		    $y = $bbox[1] + (imagesy($im) / 2) - ($bbox[5] / 2) - 5;
		    imagettftext($im, $font_size*0.33, 0, $x, $y+ $font_size * 1.5, $text_color, $font, $ft );
		}
		imagejpeg($im);
		imagedestroy($im);
		print "--$boundary\n";
	    }
	} else {
	    if( !file_exists($file)) {
		banner("файл отсутствует");
	    } else {
		# New frame detected, present it
		$ev = inotify_read($in);
		print "Content-type: image/jpeg\n\n";
		print file_get_contents($file);
		print "--$boundary\n";
	    }
	}
    }
}

inotify_rm_watch($in,$wd);
fclose( $in );
?>