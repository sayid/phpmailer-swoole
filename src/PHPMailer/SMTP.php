<?php

namespace PHPMailerSwoole\PHPMailer;

/**
 * 复写smtp连接器
 * Class SMTP
 * @package App\PHPMailer
 */
class SMTP extends \PHPMailer\PHPMailer\SMTP
{

    public $smtp_conn;

    private $swoole_conn_setting = [
        'package_max_length' => 2000000
    ];

    public function swooleSetting($settiong = [])
    {
        $this->swoole_conn_setting = $settiong;
    }
    public function connect($host, $port = null, $timeout = 30, $options = [])
    {

        $url = parse_url($host);
        $sockType = SWOOLE_SOCK_TCP;
        if('ssl' === $url['scheme'])
        {
            $sockType = $sockType | SWOOLE_SSL;
        }
        $host = $url['host'];
        $this->smtp_conn = new \Swoole\Coroutine\Client($sockType);

        if ($this->swoole_conn_setting) {
            $this->smtp_conn->set($this->swoole_conn_setting);
        }
        if (!$this->smtp_conn->connect($host, $port, $timeout)) {
            //连接失败
            return false;
        }
        // Get any announcement
        $announce = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $announce, self::DEBUG_SERVER);

        return true;
    }

    /**
     * Initiate a TLS (encrypted) session.
     *
     * @return bool
     */
    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }

        //Allow the best TLS version(s) we can
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;

        //PHP 5.6.7 dropped inclusion of TLS 1.1 and 1.2 in STREAM_CRYPTO_METHOD_TLS_CLIENT
        //so add them back in manually if we can
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        SOL_SOCKET;
        // Begin encrypted connection
        set_error_handler([$this, 'errorHandler']);
        $crypto_ok = stream_socket_enable_crypto(
            $this->smtp_conn->getSocket(),
            true,
            $crypto_method
        );
        restore_error_handler();

        return (bool) $crypto_ok;
    }


    /**
     * Check connection state.
     *
     * @return bool True if connected
     */
    public function connected()
    {
        if (is_object($this->smtp_conn)) {
            if (isset($this->smtp_conn->errCode) && $this->smtp_conn->errCode) {
                // The socket is valid but we are not connected
                $this->edebug(
                    'SMTP NOTICE: EOF caught while checking if connected',
                    self::DEBUG_CLIENT
                );
                $this->close();

                return false;
            }

            return true; // everything looks good
        }

        return false;
    }

    /**
     * Close the socket and clean up the state of the class.
     * Don't use this function without first trying to use QUIT.
     *
     * @see quit()
     */
    public function close()
    {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_object($this->smtp_conn)) {
            // close the connection and cleanup
            $this->smtp_conn->close();
            $this->smtp_conn = null; //Makes for cleaner serialization
            $this->edebug('Connection: closed', self::DEBUG_CONNECTION);
        }
    }


    /**
     * Send raw data to the server.
     *
     * @param string $data    The data to send
     * @param string $command Optionally, the command this is part of, used only for controlling debug output
     *
     * @return int|bool The number of bytes sent to the server or false on error
     */
    public function client_send($data, $command = '')
    {
        //If SMTP transcripts are left enabled, or debug output is posted online
        //it can leak credentials, so hide credentials in all but lowest level
        if (self::DEBUG_LOWLEVEL > $this->do_debug and
            in_array($command, ['User & Password', 'Username', 'Password'], true)) {
            $this->edebug('CLIENT -> SERVER: <credentials hidden>', self::DEBUG_CLIENT);
        } else {
            $this->edebug('CLIENT -> SERVER: ' . $data, self::DEBUG_CLIENT);
        }
        set_error_handler([$this, 'errorHandler']);
        $result = $this->smtp_conn->send($data);
        restore_error_handler();

        return $result;
    }



    /**
     * Read the SMTP server's response.
     * Either before eof or socket timeout occurs on the operation.
     * With SMTP we can tell if we have more lines to read if the
     * 4th character is '-' symbol. If it is a space then we don't
     * need to read anything else.
     *
     * @return string
     */
    protected function get_lines()
    {
        // If the connection is bad, give up straight away
        if (!is_object($this->smtp_conn)) {
            return '';
        }
        $data = '';
        while (is_object($this->smtp_conn)) {
            $str = $this->smtp_conn->recv();
            if (empty($str)) {
                break;
            }
            $data = $data . $str;
            break;
        }

        return $data;
    }
}
