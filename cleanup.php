<?php

class CleanUp
{
  private $downer = null;
  private $down_type = 0;
  private $space_avail_limit = 1000000; // clear files when 1.0 gb remains
  private $space_avail_limit_hard = 300000; // remove server when disk is full (>300mb)
  private $APP_DATA = '/upload';

  function __construct ()
  {
    // check if we are cleaning old files now
    $this->filelock = $this->APP_DATA . '/filelock.data';

    if (file_exists ($this->filelock))
    {
      // check the time inside it
      $curr = time () - (file_get_contents ($this->filelock) / 1);
      // if less than 10 minutes have passed
      if ($curr < 600)
      {
        $this->ok ();
        exit (0);
      }

      unlink ($this->filelock);
    }

    $avail_space = exec ('df | grep  ' . substr ($this->APP_DATA, 0, -1));
    $u = explode (' ', preg_replace('/\s+/', ' ', $avail_space));
    $avail_space = $u[3] / 1;

    if ($avail_space < $this->space_avail_limit_hard)
    {
      $this->failed (2);
    }

    else if ($avail_space < $this->space_avail_limit) //1gb
    {
      $this->ok ();
      $this->clean_old_files ($this->APP_DATA);

      exit (0);
    }

      $this->ok ();
  }

  private function ok ()
  {
      // http_response_code (200);
      echo '1';

    if (file_exists ($this->downer))
        unlink ($this->downer);

    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request ();
  }

  private function failed ($type = 0)
  {
    http_response_code (404);

    $this->down_type = $type;

    if (!file_exists ($this->downer))
    {
        touch ($this->downer);
        $this->email_admin ();
    }
    exit (0);
  }

  private function rrmdir($dir) {
    foreach(glob($dir . '/*') as $file) {
      if(is_dir($file)) $this->rrmdir($file); else unlink($file);
    } rmdir($dir);
  }

  private function clean_old_files ($dir_name)
  {
    if (file_exists ($dir_name))
    {
      set_time_limit (900);
      ignore_user_abort (true);

      file_put_contents ($this->filelock, time ());

      $date = 7 * 24 * 60 * 60; // 60 seconds, 60 minutes, 24 hours, 5 days
      $ttime = time ();

      try
      {
        foreach (new RecursiveIteratorIterator (
                  new RecursiveDirectoryIterator ($dir_name, FilesystemIterator::SKIP_DOTS),
                  RecursiveIteratorIterator::CHILD_FIRST) as $path)
        {
          if ($ttime - $path->getCTime () >= $date)
          {
            $filename = $path->getPathname ();
            if (strpos ($filename, 'lost+found') !== FALSE) continue;
            $path->isFile () ? unlink ($filename) : $this->rrmdir ($filename);
          }
        }
        // ----
      }
      catch (\UnexpectedValueException $e)
      {
        print_r ($e);
      }

      sleep (10); // do not force a new deletion of files for 10 seconds les the system drop CPU usage
      unlink ($this->filelock);

    }
    // -
  }

  private function email_admin ()
  {
  }

  public function index ($var_arr)
  {
  }
  // end
}

function m() {
    $t = new \CleanUp();
}
m ();
