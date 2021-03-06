<?php
namespace SMGregsList;
class SMGrabber
{
    protected $useragent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_5) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.43 Safari/537.31 (CelloG\'s Similar Transaction Script (0.1.0))';
    protected $cookies = '';
    protected $downloadcount = 0;
    protected $loggedin = false;
    protected $login;
    protected $password;
    function __construct()
    {
        $info = json_decode(file_get_contents(__DIR__ . '/config.json'), 1);
        $this->login = $info['login'];
        $this->password = $info['loginpass'];
    }

    function setCookies($cookies)
    {
        $this->cookies = $cookies;
        $this->loggedin = true;
    }

    function retrieveCookies()
    {
        return $this->cookies;
    }

    function download($url, &$result = null, $failed = false)
    {
        if (!$this->loggedin) {
            $this->relogin();
        }
        $context = stream_context_create(array('http' => array(
            //'follow_location' => 0,
            'user_agent' => $this->useragent,
            'protocol_version' => 1.1,
            'timeout' => 60.0,
            'header' => array('Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                              'Accept-Charset:ISO-8859-1,utf-8;q=0.7,*;q=0.3',
                              'Accept-Language:en-US,en;q=0.8',
                              'Host:en.strikermanager.com',
                              'Referer:http://en.strikermanager.com/inicio.php',
                              'Cookie:' . $this->processCookies()
                             )
        )));
        $fp = fopen($url, 'r', false, $context);
        $info = stream_get_meta_data($fp);
        if (strpos($info['wrapper_data'][0], '302')) {
            if ($failed) {
                throw new \Exception("Error: we were unable to login, check to make sure account isn't blocked\n");
            }
            fclose($fp);
            $this->loggedin = false;
            return $this->download($url, $result, true); // try again, with a login
        }
        preg_match('/\d\d\d/', $info['wrapper_data'][0], $result);
        if (!$result) {
            $result = '404';
        } else {
            $result = $result[0];
        }
        $this->getCookies($info['wrapper_data']);
        $ret = stream_get_contents($fp);
        fclose($fp);
        return $ret;
    }

    protected function processCookies()
    {
        $cookie = array();
        foreach ($this->cookies as $name => $value) {
            $cookie[] = $name . '=' . $value;
        }
        return implode('; ', $cookie);
    }

    protected function getCookies($data)
    {
        foreach ($data as $line) {
            if (strpos($line, 'Set-Cookie:') === 0) {
                // extract the cookies
                $line = str_replace('Set-Cookie: ', '', $line);
                $line = explode(';', $line);
                $line = trim($line[0]);
                $line = explode('=', $line);
                //if (isset($this->cookies[$line[0]])) continue;
                $this->cookies[$line[0]] = $line[1];
            }
        }
    }

    function relogin()
    {
        // TODO: check to see if the database cookie is different from ours, and if so, use it instead
        $logout = array(
            'user_agent' => $this->useragent,
        );
        if ($this->cookies) {
            $logout['header'] = 'Cookie: ' . $this->cookies;
        }
        $context = stream_context_create(array('http' => $logout));
        file_get_contents('http://en.strikermanager.com/logout.php', false, $context);
        $this->cookies = array();
        $login = http_build_query(array(
                'alias' => $this->login,
                'pass' => $this->password,
                'dest' => ''
            ));
        $context = stream_context_create(array('http' => array(
            'method' => 'POST',
            //'follow_location' => 0,
            'user_agent' => $this->useragent,
            'protocol_version' => 1.1,
            'ignore_errors' => true,
            'header' => array(
                'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Charset:ISO-8859-1,utf-8;q=0.7,*;q=0.3',
                'Accept-Language:en-US,en;q=0.8',
                'Cache-Control:max-age=0',
                'Content-type: application/x-www-form-urlencoded',
                'Content-length: ' . strlen($login),
                'Host:en.strikermanager.com',
                'Origin:http://en.strikermanager.com',
                'Referer:http://en.strikermanager.com/',
            ),
            'content' => $login
        )));
        $fp = fopen('http://en.strikermanager.com/loginweb.php', 'r', false, $context);
        $info = stream_get_meta_data($fp);
        $this->getCookies($info['wrapper_data']);
        fclose($fp);
        $context = stream_context_create(array('http' => array(
            'user_agent' => $this->useragent,
            'protocol_version' => 1.1,
            'header' => array(
                'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Charset:ISO-8859-1,utf-8;q=0.7,*;q=0.3',
                'Accept-Language:en-US,en;q=0.8',
                'Cache-Control:max-age=0',
                'Host:en.strikermanager.com',
                'Referer:http://en.strikermanager.com/',
                'Cookie: ' . $this->processCookies()
            )
        )));
        $fp = fopen('http://en.strikermanager.com/inicio.php', 'r', false, $context);
        $info = stream_get_meta_data($fp);
        $this->getCookies($info['wrapper_data']);
        // TODO: update the database with new cookie somehow, so others can pull it in too.
        fclose($fp);
        $this->loggedin = true;
    }
}
?>