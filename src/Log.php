<?php
use Zend\Authentication\AuthenticationService;

namespace Geo6;

class Log {
  public static function write($fname, $text, $data = array(), $level = NULL) {
    if (is_null($level)) $level = Zend\Log\Logger::INFO;

    // ---------------------------------------------------------------------------------------------
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $data['_ip'] = $_SERVER['HTTP_X_FORWARDED_FOR'].(isset($_SERVER['REMOTE_ADDR']) ? ' ('.$_SERVER['REMOTE_ADDR'].')' : '');
    } else {
      $data['_ip'] = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL);
    }

    // ---------------------------------------------------------------------------------------------
    $data['_referer'] = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : NULL);

    // ---------------------------------------------------------------------------------------------
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
      $gb = get_browser();
      $data['_browser'] = ($gb->browser != 'Default Browser' ? $gb->platform.' '.$gb->parent : NULL);
    } else {
      $data['_browser'] = NULL;
    }

    // ---------------------------------------------------------------------------------------------
    $auth = new AuthenticationService();
    $login = $auth->getIdentity();

    $data['_login'] = NULL; if (isset($_SESSION['uid']) && !empty($_SESSION['uid'])) $data['_login'] = $login;

    // ---------------------------------------------------------------------------------------------
    $logger = new Zend\Log\Logger;
    $logger->addWriter(new Zend\Log\Writer\Stream(SITE_PATH.'/logs/'.$fname.'.log'));
    $logger->addProcessor(new Zend\Log\Processor\PsrPlaceholder);

    $logger->log($level, $text, $data);
  }

  public static function read($fname) {
    $logs = array();

    $fp = fopen(SITE_PATH.'/logs/'.$fname.'.log', 'r');
    if ($fp) {
      while (($r = fgets($fp, 10240)) !== FALSE) {
        // Zend\Log : %timestamp% %priorityName% (%priority%): %message% %extra%
        if (preg_match('/^(.+) (DEBUG|INFO|NOTICE|WARN|ERR|CRIT|ALERT|EMERG) \(([0-9])\): (.+) ({.+})$/', $r, $matches) == 1) {
          $logs[] = array(
            'timestamp'     => strtotime($matches[1]), // ISO 8601
            'priority_name' => $matches[2],
            'priority'      => $matches[3],
            'message'       => $matches[4],
            'extra'         => json_decode($matches[5], TRUE)
          );
        }
        // Other (olg) logs
        else {
          $logs[] = $r;
        }
      }
    }
    fclose($fp);

    return $logs;
  }
}
