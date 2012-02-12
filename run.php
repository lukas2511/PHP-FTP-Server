#!/usr/bin/php -q
<?php
/**
  * Listens for requests and forks on each connection
  */

$__server_listening = true;

error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();
declare(ticks = 1);

become_daemon();

/* nobody/nogroup, change to your host's uid/gid of the non-priv user */
change_identity(65534, 65534);

/* handle signals */
pcntl_signal(SIGTERM, 'sig_handler');
pcntl_signal(SIGINT, 'sig_handler');
pcntl_signal(SIGCHLD, 'sig_handler');

$port=21;

//echo "[DD] Listening on ".$port."\n";

/* change this to your own host / port */
server_loop("127.0.0.1", $port);

/**
  * Change the identity to a non-priv user
  */
function change_identity( $uid, $gid )
{
    if( !posix_setgid( $gid ) )
    {
        print "Unable to setgid to " . $gid . "!\n";
        exit;
    }

    if( !posix_setuid( $uid ) )
    {
        print "Unable to setuid to " . $uid . "!\n";
        exit;
    }
}

/**
  * Creates a server socket and listens for incoming client connections
  * @param string $address The address to listen on
  * @param int $port The port to listen on
  */
function server_loop($address, $port)
{
    GLOBAL $__server_listening;

    if(($sock = socket_create(AF_INET, SOCK_STREAM, 0)) < 0)
    {
        echo "failed to create socket: ".socket_strerror($sock)."\n";
        exit();
    }

    if(($ret = socket_bind($sock, $address, $port)) < 0)
    {
        echo "failed to bind socket: ".socket_strerror($ret)."\n";
        exit();
    }

    if( ( $ret = socket_listen( $sock, 0 ) ) < 0 )
    {
        echo "failed to listen to socket: ".socket_strerror($ret)."\n";
        exit();
    }

    socket_set_nonblock($sock);
   
    echo "waiting for clients to connect\n";

    while ($__server_listening)
    {
        $connection = @socket_accept($sock);
        if ($connection === false)
        {
            usleep(100);
        }elseif ($connection > 0)
        {
            handle_client($sock, $connection);
        }else
        {
            echo "error: ".socket_strerror($connection);
            die;
        }
    }
}

/**
  * Signal handler
  */
function sig_handler($sig)
{
    switch($sig)
    {
        case SIGTERM:
        case SIGINT:
            exit();
        break;

        case SIGCHLD:
            pcntl_waitpid(-1, $status);
        break;
    }
}

/**
  * Handle a new client connection
  */
function handle_client($ssock, $csock)
{
    GLOBAL $__server_listening;

    $pid = pcntl_fork();

    if ($pid == -1)
    {
        /* fork failed */
        echo "fork failure!\n";
        die;
    }elseif ($pid == 0)
    {
        /* child process */
        $__server_listening = false;
        socket_close($ssock);
        interact($csock);
        socket_close($csock);
    }else
    {
        socket_close($csock);
    }
}

function interact($socket)
{
    /* TALK TO YOUR CLIENT */
    if(file_exists("client.php")){
        include "client.php";
    }
}

/**
  * Become a daemon by forking and closing the parent
  */
function become_daemon()
{
    $pid = pcntl_fork();
   
    if ($pid == -1)
    {
        /* fork failed */
        echo "fork failure!\n";
        exit();
    }elseif ($pid)
    {
        /* close the parent */
        exit();
    }else
    {
        /* child becomes our daemon */
        posix_setsid();
        chdir('/');
        umask(0);
        return posix_getpid();

    }
}

?>
