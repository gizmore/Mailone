<?php
/**
 * Mailone 1.0.0
 * Simply include this file.
 * Optionally pre-define the constants from the beginning of the code.
 * Get emails sent on php errors, even fatals.
 * Include again to re-force error reporting etc....
 * Available as joomla plugin, wordpress plugin, etc.
 * (c) 2017 by gizmore@wechall.net
 * Licensed under the WeChall Public License v6.0.
 * 
 * @example
 * define('MAILONE_ENABLE', 1);
 * define('MAILONE_VERBOSE', 1);
 * define('MAILONE_LEVEL', E_ALL);
 * define('MAILONE_MAIL_TO', 'gizmore@wechall.net');
 * 
 * include 'mailone.php';
 * someblackbox();
 * include 'mailone.php'; # re-force! (should not be necessary.... but you never know)
 * moreblackbox
*/
namespace Mailone;

use Throwable;

final class Common
{
    public static function define(string $key, $value) { if (!defined($key)) define($key, $value); }
}
Common::define('MAILONE_ENABLE', true); # global on/off
Common::define('MAILONE_VERBOSE', false); # try to bring errors to screen. Disables output buffering. tries to force show errors
Common::define('MAILONE_LEVEL', false); # Error level to adjust to. false for no adjustment. 0x7fffffff is pretty much E_ALL.
#Common::define('MAILONE_LEVEL', 0x7fffffff); # Error level to adjust to. false for no adjustment. 0x7fffffff is pretty much E_ALL.
// Common::define('MAILONE_PEDANTIC', true); # mails every error
# Mail settings
Common::define('MAILONE_MAIL_SENDER_MAIL', 'robot@wechall.net');
Common::define('MAILONE_MAIL_SENDER_NAME', 'Mailone');
Common::define('MAILONE_MAIL_TO', 'root@localhost');
Common::define('MAILONE_MAIL_SENDMAIL', true); # Default is localhost sendmail
# Remote SMPT is not recommended.
Common::define('MAILONE_MAIL_SMTP_HOST', 'localhost');
Common::define('MAILONE_MAIL_SMTP_USER', 'robot@wechall.net');
Common::define('MAILONE_MAIL_SMTP_PASS', 'passwortatwechall');

# Debug and mailcode below here
final class Debug
{
    private static $enabled = false;
    
    ################
    ### Settings ###
    ################
    public static function enable()
    {
        if (!self::$enabled)
        {
            self::$enabled = true;
            set_exception_handler(array('Mailone\\Debug', 'exception_handler'));
            set_error_handler(array('Mailone\\Debug', 'error_handler'));
            register_shutdown_function(array('Mailone\\Debug', 'shutdown_function'));
        }
    }
    public static function disable()
    {
        if (self::$enabled)
        {
            self::$enabled = false;
            restore_exception_handler();
            restore_error_handler();
        }
    }
    
    /**
     * This one get's called on a fatal. No stacktrace available and some vars are messed up.
     */
    public static function shutdown_function()
    {
        if (self::$enabled)
        {
            $error = error_get_last();
            if ($error['type'] != 0)
            {
                self::error_handler(1, $error['message'], self::shortpath($error['file']), $error['line'], NULL);
            }
        }
    }
    
    
    /**
     * Error handler creates some html backtrace and can die on _every_ warning etc.
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @param $errcontext
     * @return false
     */
    public static function error_handler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        if (error_reporting() === 0) # Is set by PHP when you supress with @
        {
            return;
        }
        
        # TODO: log
        
        switch($errno)
        {
            case -1: $errnostr = 'GWF Error'; break;
            
            case E_ERROR: case E_CORE_ERROR: $errnostr = 'PHP Fatal Error'; break;
            case E_WARNING: case E_USER_WARNING: case E_CORE_WARNING: $errnostr = 'PHP Warning'; break;
            case E_USER_NOTICE: case E_NOTICE: $errnostr = 'PHP Notice'; break;
            case E_USER_ERROR: $errnostr = 'PHP Error'; break;
            case E_STRICT: $errnostr = 'PHP Strict Error'; break;
            # if(PHP5.3) case E_DEPRECATED: case E_USER_DEPRECATED: $errnostr = 'PHP Deprecated'; break;
            # if(PHP5.2) case E_RECOVERABLE_ERROR: $errnostr = 'PHP Recoverable Error'; break;
            case E_COMPILE_WARNING: case E_COMPILE_ERROR: $errnostr = 'PHP Compiling Error'; break;
            case E_PARSE: $errnostr = 'PHP Parsing Error'; break;
            
            default: $errnostr = 'PHP Unknown Error '.$errno; break;
        }
        
        $message = sprintf('%s(EH %s) %s in %s line %s.', $errnostr, $errno, $errstr, $errfile, $errline);
        $trace = self::backtrace($message);
        self::sendDebugMail($trace);
        return true;
    }
    
    public static function exception_handler($e)
    {
        $message = sprintf('Exception (XH) %s in %s line %s.', $e->getMessage(), $e->getFile(), $e->getLine());
        $trace = self::backtraceException($e, $message);
        self::sendDebugMail($trace);
        return true;
    }
    
    
    /**
     * Send error report mail.
     * @param string $message
     */
    public static function sendDebugMail($message)
    {
        $mail = Mail::botMail();
        $mail->setReceiver(MAILONE_MAIL_TO);
        $mail->setSubject('MAIL ON ERROR');
        $mail->setBody($message);
        
        if (MAILONE_VERBOSE)
        {
            printf("<pre>%s</pre>", htmlspecialchars($message));
        }
        
        $mail->sendAsText();
    }
    
    /**
     * Get some additional information
     * @todo move?
     */
    public static function getDebugText($message)
    {
        $user = "???USER???";
        // 		try { $user = GDO_User::current()->displayName(); } catch (Exception $e) { $user = 'ERROR'; }
        $args = array(
            isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'NULL',
            isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'NULL',
            isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'NULL',
            isset($_SERVER['USER_AGENT']) ? $_SERVER['USER_AGENT'] : 'NULL',
            $user,
            $message,
            print_r($_GET, true),
            print_r($_POST, true),
            print_r($_COOKIE, true),
        );
        $args = array_map('htmlspecialchars', $args);
        $pattern = "RequestMethod: %s\nRequestURI: %s\nReferer: %s\nIP: %s\nUserAgent: %s\nGDO_User: %s\n\nMessage: %s\n\n_GET: %s\n\n_POST: %s\n\n_COOKIE: %s\n\n";
        return vsprintf($pattern, $args);
    }
    
    /**
     * Return a backtrace in either HTML or plaintext. You should use monospace font for html.
     * HTML means (x)html(5) and <pre> style.
     * Plaintext means nice for logfiles.
     * @param string $message
     * @param boolean $html
     * @return string
     */
    public static function backtrace($message='', $html=true)
    {
        return self::backtraceMessage($message, $html, debug_backtrace());
    }
    
    public static function backtraceException(Throwable $e, $html=true, $message='')
    {
        $message = sprintf("PHP Exception$message: %s in %s line %s", $e->getMessage(), self::shortpath($e->getFile()), $e->getLine());
        return self::backtraceMessage($message, $html, $e->getTrace());
    }
    
    private static function backtraceArgs(array $args=null)
    {
        $out = [];
        if ($args)
        {
            foreach ($args as $arg)
            {
                $out[] = self::backtraceArg($arg);
            }
        }
        return implode(",", $out);
    }
    private static function backtraceArg($arg)
    {
        if ($arg === null)
        {
            return 'NULL';
        }
        elseif ($arg === true)
        {
            return 'true';
        }
        elseif ($arg === false)
        {
            return 'false';
        }
        elseif (is_string($arg) || is_array($arg))
        {
            $arg = json_encode($arg);
        }
        elseif (is_object($arg))
        {
            return get_class($arg);# . "#" . $obj->__toString();
        }
        else
        {
            $arg = json_encode($arg, 1);
        }
        $app = mb_strlen($arg) > 48 ? '…' : '';
        return mb_substr($arg, 0, 48).$app;
    }
    
    private static function backtraceMessage($message, $html=true, array $stack)
    {
        $badformat = false;
        
        $back = self::shortpath($message);
        
        $implode = [];
        $preline = 'Unknown';
        $prefile = 'Unknown';
        $longest = 0;
        $i = 0;
        
        foreach ($stack as $row)
        {
            if ($i++ > 0)
            {
                $function = sprintf('%s%s(%s)', isset($row['class']) ? $row['class'].$row['type'] : '', $row['function'], self::backtraceArgs(@$row['args']));
                $implode[] = array(
                    $function,
                    $prefile,
                    $preline,
                );
                $len = strlen($function);
                $longest = max(array($len, $longest));
            }
            $preline = isset($row['line']) ? $row['line'] : '?';
            $prefile = isset($row['file']) ? $row['file'] : '[unknown file]';
        }
        
        $copy = [];
        foreach ($implode as $imp)
        {
            list($func, $file, $line) = $imp;
            $len = strlen($func);
            $func .= str_repeat('.', $longest-$len);
            $copy[] = sprintf('%s %s line %s.', $func, self::shortpath($file), $line);
        }
        
        $back .= "\n";
        $back .= sprintf("Backtrace starts in %s line %s.\n", self::shortpath($prefile), $preline);
        $back .= implode("\n", array_reverse($copy));
        return $back;
    }
    
    /**
     * Strip full pathes so we don't have a full path disclosure.
     * @param string $path
     * @return string
     */
    public static function shortpath($path)
    {
        return $path;
        //                 $path = str_replace(GWF_PATH, '', $path);
        //                 return trim($path, ' /');
    }
}

final class Mail
{
    const HEADER_NEWLINE = "\n";
    
    private $reply = '';
    private $replyName = '';
    private $receiver = '';
    private $receiverName = '';
    private $return = '';
    private $returnName = '';
    private $sender = '';
    private $senderName = '';
    private $subject = '';
    private $body = '';
    private $headers = [];
    private $gpgKey = '';
    private $resendCheck = false;
    
    private $allowGPG = true;
    
    // 	public function __construct() {}
    public function setReply($r) { $this->reply = $r; }
    public function setReplyName($rn) { $this->replyName = $rn; }
    public function setSender($s) { $this->sender = $s; }
    public function setSenderName($sn) { $this->senderName = $sn; }
    public function setReturn($r) { $this->return = $r; }
    public function setReturnName($rn) { $this->returnName = $rn; }
    public function setReceiver($r) { $this->receiver = $r; }
    public function setReceiverName($rn) { $this->receiverName = $rn; }
    public function setSubject($s) { $this->subject = $this->escapeHeader($s); }
    public function setBody($b) { $this->body = $b; }
    public function setGPGKey($k) { $this->gpgKey = $k; }
    public function setAllowGPG($bool) { $this->allowGPG = $bool; }
    public function setResendCheck($bool) { $this->resendCheck = $bool; }
    
    private function escapeHeader($h) { return str_replace("\r", '', str_replace("\n", '', $h)); }
    
    public static function botMail()
    {
        $mail = new self();
        $mail->setSender(MAILONE_MAIL_SENDER_MAIL);
        $mail->setSenderName(MAILONE_MAIL_SENDER_NAME);
        return $mail;
    }
    
    private function getUTF8Reply()
    {
        if ($this->reply === '')
        {
            return $this->getUTF8Sender();
        }
        return $this->getUTF8($this->reply, $this->replyName);
    }
    
    private function getUTF8Return()
    {
        if ($this->reply === '')
        {
            return $this->getUTF8Sender();
        }
        return $this->getUTF8($this->return, $this->returnName);
    }
    
    private function getUTF8($email, $name)
    {
        return $name === '' ? $email : '"'.$this->getUTF8Encoded($name)."\" <{$email}>";
    }
    
    private function getUTF8Sender()
    {
        return $this->getUTF8($this->sender, $this->senderName);
    }
    
    private function getUTF8Receiver()
    {
        return $this->getUTF8($this->receiver, $this->receiverName);
    }
    
    private function getUTF8Subject() { return $this->getUTF8Encoded($this->subject); }
    
    private function getUTF8Encoded($string) { return '=?UTF-8?B?'.base64_encode($string).'?='; }
    

    private static function br2nl($s, $nl=PHP_EOL)
    {
        return preg_replace('/< *br *\/? *>/i', $nl, $s);
    }
    
    public function nestedTextBody()
    {
        $body = $this->body;
        #$body = preg_replace('/<[^>]+>([^<]+)<[^>+]>/', '$1', $body);
        $body = preg_replace('/<[^>]+>/', '', $body);
        $body = self::br2nl($body);
        $body = html_entity_decode($body, ENT_QUOTES, 'UTF-8');
        return $body;
    }
    
    public function sendAsText($cc='', $bcc='')
    {
        return $this->send($cc, $bcc, $this->nestedTextBody());
    }
    
    public function send($cc, $bcc, $message)
    {
        $headers = '';
        $to = $this->getUTF8Receiver();
        $from = $this->getUTF8Sender();
        $subject = $this->getUTF8Subject();
        $contentType = 'text/plain';
        $headers .=
        "Content-Type: $contentType; charset=utf-8".self::HEADER_NEWLINE
        ."MIME-Version: 1.0".self::HEADER_NEWLINE
        ."Content-Transfer-Encoding: 8bit".self::HEADER_NEWLINE
        ."X-Mailer: PHP".self::HEADER_NEWLINE
        .'From: '.$from.self::HEADER_NEWLINE
        .'Reply-To: '.$this->getUTF8Reply().self::HEADER_NEWLINE
        .'Return-Path: '.$this->getUTF8Return();
        return mail($to, $subject, $message, $headers);
    }
    
}

if (MAILONE_LEVEL)
{
    error_reporting(MAILONE_LEVEL);
}

if (MAILONE_ENABLE)
{
    Debug::enable();
}

if (MAILONE_VERBOSE)
{
    while (ob_get_level())
    {
        ob_flush();
        ob_end_clean();
    }
}

