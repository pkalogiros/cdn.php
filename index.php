<?php

class Index
{
  const APP_BASE_URL = 'your-url/';
  const APP_DATA = '/upload/';
  //  origin url;
  const APP_CDN_URL = 'your-origin.com';
  const APP_PROTOCOL = 'https://';

  public static function init ()
  {
      $uri_parts = explode ('?', $_SERVER['REQUEST_URI'], 2);
      $_uri = $uri_parts[0];
      // $_uri = str_replace ('//', '/', $_uri); // filtering /

      $l = strlen ($_uri);
      if ($l < 4 || $l > 1024) Index::return404 ();
      $_uri = substr ($_uri, 1); // remove first /

      $path = Index::APP_DATA . $_uri;
      if (file_exists ($path)) Index::returnFile ($path);

      Index::cacheFile ($_uri, $path);
  }

  private static function return404()
  {
    http_response_code (404);
    echo '404';
    exit (0);
  }

  private static function returnFile ($path)
  {
    $basename = basename ($path);
    $ext = strtolower (array_pop (explode ('.', $basename)));

    $ctype = 'application/octet-stream';
    $ext_map = array (
        'jpg'        => 'image/jpeg',
        'mp4'        => 'video/mp4',
        'txt'        => 'text/plain',
        'html'       => 'text/html',
        'css'        => 'text/css',
        'javascript' => 'application/javascript',
        'json'       => 'application/json',
        'png'        => 'image/png',
        'gif'        => 'image/gif',
        'pdf'        => 'application/pdf',
        'zip'        => 'application/zip'
    );

    if (isset ($ext_map[ $ext ])) $ctype = $ext_map[ $ext ];

    $length = filesize ($path);
    $size = $length;

    header ('Content-Type: ' . $ctype);
    //header ('Content-Length: ' . filesize ($path));
    header ('Access-Control-Allow-Origin: *');
    header ('Cache-Control: max-age=25724800');
    header ('Pragma: public');
    header ('Accept-Ranges: bytes');

    if (isset($_SERVER['HTTP_RANGE']))
    {
      $start  = 0;               // Start byte
      $end    = $length - 1;       // End byte
      $c_start = $start;
      $c_end   = $end;

      list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
      if (strpos($range, ',') !== false)
      {
          header('HTTP/1.1 416 Requested Range Not Satisfiable');
          header("Content-Range: bytes $start-$end/$size");
          exit (0);
      }

      if ($range == '-')
      {
        $c_start = $size - substr($range, 1);
      }
      else
      {
        $range  = explode('-', $range);
        $c_start = $range[0];
        $c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
      }

      $c_end = ($c_end > $end) ? $end : $c_end;
      if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size)
      {
          header('HTTP/1.1 416 Requested Range Not Satisfiable');
          header("Content-Range: bytes $start-$end/$size");
          exit;
      }

      $start  = $c_start;
      $end    = $c_end;
      $length = $end - $start + 1;

      $fp = @fopen($path, 'rb');
      fseek($fp, $start);
      header('HTTP/1.1 206 Partial Content');

      header("Content-Range: bytes $start-$end/$size");
      header("Content-Length: ".$length);
      $buffer = 1024 * 8;

      while(!feof($fp) && ($p = ftell($fp)) <= $end)
      {
          if ($p + $buffer > $end) {
              $buffer = $end - $p + 1;
          }
          set_time_limit(0);
          echo fread($fp, $buffer);
          flush();
      }
      fclose($fp);

      exit (0);
    }
    
    header("Content-Length: ".$length);
    readfile ($path);

    exit (0);
  }

  private static function fetchFile ($tmp_target, $target, $file)
  {
    $newf = fopen ($tmp_target, 'wb');
    if ($newf)
    {
        // stream_set_timeout ($newf, 2);
        while (!feof ($file))
            fwrite ($newf, fread ($file, 1024 * 8), 1024 * 8);

        fclose ($newf);
        rename ($tmp_target, $target);
    }

    fclose ($file);
  }

  private static function cacheFile ($path, $target, $attempts = 200)
  {
    // grab the file from the origin and cache it
    $origin_loc = Index::APP_PROTOCOL . Index::APP_CDN_URL . $path;
    $tmp_target = $target . '.tmp';

    if (file_exists ($tmp_target))
    {
        usleep (60000);
        if ($attempts === 0)
        {
            unlink ($tmp_target);
            Index::return404 ();
        }

        Index::cacheFile ($path, $target, --$attempts);
        return ;
    }

    if ($attempts !== 200)
        if (file_exists ($target)) Index::returnFile ($target);

    if(!file_exists (dirname ($target))) mkdir (dirname ($target), 0777, true);

    ignore_user_abort (true);
    set_time_limit (0);

    exec ('axel -n 20 "' . $origin_loc . '" --quiet -o "' . $tmp_target . '"');
    if (file_exists ($tmp_target) && filesize ($tmp_target) > 200)
    {
      rename ($tmp_target, $target);
      Index::returnFile ($target);
    }
    else
    {
      if (file_exists ($tmp_target)) unlink ($tmp_target);

      header ('Access-Control-Allow-Origin: *');
      // redirect to origin
      header ("Location: " . $origin_loc );
      exit (0);
    }

    $file = @fopen ($origin_loc, 'rb');

    if ($file)
    {
      // stream_set_timeout ($file, 2);
      Index::fetchFile ($tmp_target, $target, $file);
    }
    else
    {
        // if timeout try again
        $pos = strpos ($http_response_header[0], '408');
        if ($pos)
        {
          $file = @fopen ($origin_loc, 'rb'); if ($file) Index::fetchFile ($tmp_target, $target, $file);
        }
        else
        {
            $pos = strpos ($http_response_header[0], '504');
            if ($pos)
            {
              $file = @fopen ($origin_loc, 'rb'); if ($file)  Index::fetchFile ($tmp_target, $target, $file);
            }
        }
       // --- 
    }

    if (file_exists ($target)) Index::returnFile ($target);
    Index::return404 ();
  }
  // -
}

function m () {
  error_reporting (E_ALL & ~E_WARNING);
  // header ('X-Powered-By: cdn');

  Index::init ();
}
m ();
