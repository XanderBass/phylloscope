<?php
  /* INFO
    @product     : phPhylloscope
    @type        : library
    @description : Библиотека для экспресс-разработки небольших веб-проектов
    @version     : 0.1.0.0
    @revision    : 08-07-2015 15:35:00 MSK
  */

  /* ================ СЕКЦИЯ ИНИЦИАЛИЗАЦИИ КОНСТАНТ ================ */
  if (!defined("PHPS_STARTTIME")) define("PHPS_STARTTIME",microtime(true));

  /* КОНСТАНТЫ ЛОГГЕРА */
  foreach (array('trigger','file','screen','user') as $ck => $cv)
    foreach (array('message','notice','warning','error') as $tk => $tv) {
      $c = strtoupper('phps_log_'.$tv.'_'.$cv);
      if (!defined($c)) define($c,1 << ($ck * 4 + $tk));
    }

  unset($ck,$cv,$c,$tv,$cv);

  $k = 0;
  $_ = array(
    'striptags' => 'strip_tags',
    'html'      => '',
    'escape'    => 'addslashes',
    'nl2br'     => '',
    'urlencode' => 'urlencode',
    'uuencode'  => 'convert_uuencode',
    'base64'    => 'base64_encode',
    'md5'       => 'md5',
    'bool'      => '',
    'intval'    => 'intval'
  );
  foreach ($_ as $p => $a) {
    $n = strtoupper("phps_hdlr_$p");
    if (!defined($n)) define($n,1 << $k);
    $n = strtoupper("phps_hdlr_$p"."_FUNC");
    if (!defined($n)) define($n,$a);
    $k++;
  }

  /* КОНСТАНТЫ БИБЛИОТЕКИ */
  $_ = array(
    'gso' => array(
      'ignore_case','whole_words','match_all','no_binary',
      'only_count','one_file','reserved6','reserved7',
      'regular','ereg'
    ),
    'pn' => array('chunk','constant','path','dir','language','setting','placeholder','debug','snippet')
  );
  foreach ($_ as $p => $a)
    foreach ($a as $k => $v) {
      $n = strtoupper("phps_$p"."_$v");
      if (!defined($n)) define($n,1 << $k);
    }

  $_ = array(
    'strict'  => -1,
    'default' => array('constant','chunk','setting','path','dir','snippet')
  );

  foreach ($_ as $k => $v) {
    $n = strtoupper("phps_pn_$k");
    if (!defined($n)) define($n,phPhylloscope::options($v,'phps_pn_'));
  }

  unset($_,$p,$a,$n,$k,$v);

  /* ================ СЕКЦИЯ БИБЛИОТЕКИ ================ */
  class phPhylloscope {
    const rexMail   = '#^([\w\.\-]+)\@([a-zA-Z0-9\.\-]+)\.([a-zA-Z0-9]{2,16})$#si';
    const rexURL    = '#^(http|https)\:\/\/([\w\-\.]+)\.([a-zA-Z0-9]{2,16})\/(\S*)$#si';
    const rexMailTo = '#^mailto\:([\w\.\-]+)\@([a-zA-Z0-9\.\-]+)\.([a-zA-Z0-9]{2,16})$#si';

    protected static $logger   = null;
    protected static $logLvl   = 0x0f;

    protected static $roots  = null;
    protected static $deploy = null;
    protected static $sdirs  = null;

    protected static $PLM         = 32;
    protected static $parserTags  = null;

    protected static $native      = 'en';
    protected static $language    = '';
    protected static $accepted    = array();
    protected static $languages   = array();
    protected static $dictionary  = array();

    protected static $cookieTime   = 86400;
    protected static $cookiePrefix = '';
    protected static $cookieHTTPS  = false;

    /* ================ СИСТЕМНЫЕ ФУНКЦИИ ================ */
    public static function c($c,$d=false) { return defined($c) ? constant($c) : $d; }

    public static function options($v,$c,$u=true) {
      if (is_array($v)) {
        $_ = 0;
        foreach ($v as $o) {
          $C  = $u ? strtoupper($c.$o) : $c.$o;
          if (defined($C)) $_ |= constant($C);
        }
        return $_;
      } else { return intval($v); }
    }

    public static function microTime() { return microtime(true) - PHPS_STARTTIME; }

    public static function microStep() {
      static $current = null;
      $mt = self::microTime();
      if (is_null($current)) $current = $mt;
      $ret     = $mt - $current;
      $current = $mt;
      return $ret;
    }

    /* ================ ФУНКЦИИ ДЛЯ РАБОТЫ С КУКАМИ ================ */
    public static function expires() { return (time() + self::$cookieTime); }

    public static function cookieTime($v=null) {
      if (!is_null($v)) self::$cookieTime = intval($v);
      return self::$cookieTime;
    }

    public static function cookiePrefix($v=null) {
      if (!is_null($v)) if (ctype_alnum($v)) self::$cookiePrefix;
      return self::$cookiePrefix;
    }

    public static function cookie($name,$value=null) {
      if (!is_null($value)) return setcookie(
        self::$cookiePrefix."_$name",$value,
        self::expires(),
        '/','.'.$_SERVER['SERVER_NAME'],self::$cookieHTTPS,
        true
      );
      if (isset($_COOKIE[self::$cookiePrefix."_$name"]))
        return $_COOKIE[self::$cookiePrefix."_$name"];
      return false;
    }

    /* ================ ФУНКЦИИ ПРИВЕДЕНИЯ ТИПОВ ================ */
    public static function getBool($v) {
      return ((strval($v) === 'true') || ($v === true) || (intval($v) > 0));
    }

    public static function pcre2js($r,$b='/') {
      return str_replace(array(
        $b.'si',$b,iconv('CP1251','UTF-8',chr(0x5c))
      ),array(
        '','',iconv('CP1251','UTF-8',chr(0x5c).chr(0x5c))
      ),$r);
    }

    public static function keyGen($c=32) {
      $s = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
      $R = '';
      for($_ = 0; $_ < $c; $_++) $R.= $s[rand(0,61)];
      return $R;
    }

    public static function now($tz=null,$ts=false) {
      $dto = new DateTime($tz);
      return $ts ? intval($dto->format('U')) : $dto->format('Y-m-d H:i:s');
    }

    /* ================ ФУНКЦИИ ЛОКАЛИЗАЦИИ ================ */
    public static function localized($v=null) {
      if (!is_null($v)) {
        if ($v) {
          if (is_array($v) || is_string($v)) self::languages($v);
          self::acceptedLanguages();
          self::language(self::$accepted[0]);
        } else {
          self::$language   = '';
          self::$languages  = array();
          self::$accepted   = array();
          self::$dictionary = array();
        }
      }
      return !empty(self::$language);
    }

    public static function language($v=null) {
      if (!is_null($v)) {
        if ($v) self::loadLanguage($v);
      }
      return self::$language;
    }

    public static function dictionary() { return self::$dictionary; }

    public function loadLanguage($value) {
      if (!empty(self::$languages)) if (!in_array($value,self::$languages)) return false;
      self::$language = $value;
      $roots = self::getPaths();
      $path   = explode('/',self::searchDir('language'));
      $path[] = $value;
      $path   = implode(DIRECTORY_SEPARATOR,$path).DIRECTORY_SEPARATOR;
      foreach ($roots as $loc) {
        if (is_dir($loc.$path))
          if ($_ = glob($loc.$path.'*.lng'))
            foreach ($_ as $f) self::addDictionary(file($f));
      }
      return true;
    }

    public static function addDictionary($data) {
      static $supported = null;
      if (is_null($supported)) $supported = array('caption','hint');

      $strings = is_array($data) ? $data : preg_split('~\\r\\n?|\\n~',$data);
      foreach ($strings as $s) {
        if (is_string($s)) {
          if (trim($s) != '') {
            $a = explode('|',$s);
            $k = trim(array_shift($a));
            foreach ($supported as $p => $e) {
              $d = isset($a[$p]) ? trim($a[$p]) : '';
              if (($d == '') && isset(self::$dictionary[$k][$e]))
                $d = self::$dictionary[$k][$e];
              self::$dictionary[$k][$e] = $d;
            }
          }
        }
      }
    }

    public static function acceptedLanguages() {
      self::$accepted = array();

      if ($s = $_SERVER['HTTP_ACCEPT_LANGUAGE']) {
        if ($l = explode(',',$s)) {
          foreach ($l as $li) {
            if ($_ = explode(';',$li)) {
              $key = $_[0];
              $key = explode('-',$key);
              $key = strtolower($key[1]);
              if (ctype_alnum($key)) {
                $val = floatval(isset($_[1])?$_[1]:1);
                self::$accepted[$key] = $val;
              }
            }
          }
          arsort(self::$accepted,SORT_NUMERIC);
          self::$accepted = array_keys(self::$accepted);
        }
      }

      if (empty(self::$accepted)) self::$accepted[] = self::$native;

      if (!empty(self::$languages)) {
        $tmp = self::$accepted;
        foreach (self::$accepted as $k => $l)
          if (!in_array($l,self::$languages)) unset($tmp[$k]);
        $tmp = array_values($tmp);
        self::$accepted = $tmp;
      }

      return self::$accepted;
    }

    public static function saveLanguage() {
      return self::localized() ? self::cookie('language',self::$language) : false;
    }

    public static function languages($v=null) {
      if (!is_null($v)) {
        $R = array(self::$native);
        $D = is_array($v) ? $v : explode(',',$v);
        foreach ($D as $l) {
          $L = strval($l);
          if (strlen($L) == 2) if (ctype_alnum($L)) if (!in_array($L,$R)) $R[] = $L;
        }
        if (count($R) > 1) self::$languages;
      }
      return self::$languages;
    }

    /* ================ ФУНКЦИИ ЛОГГИРОВАНИЯ ================ */
    public static function logLevel($v=null) {
      if (!is_null($v)) {
        self::$logLvl = self::options($v,'phps_log_');
        if (is_a(self::$logger,'phPSLogger'))
          self::$logger->debug = self::$logLvl;
      }
      return self::$logLvl;
    }

    public static function getLog($DTPL='') {
      if (method_exists(self::$logger,'dump')) return self::$logger->dump(false,$DTPL);
      return '';
    }

    public static function log($T,$A) {
      if (is_null(self::$logger)) self::$logger = new phPSLogger(self::$logLvl);
      return self::$logger->log($T,$A);
    }

    public static function message() { $A = func_get_args(); return self::log(0,$A); }
    public static function notice()  { $A = func_get_args(); return self::log(E_USER_NOTICE,$A); }
    public static function warning() { $A = func_get_args(); return self::log(E_USER_WARNING,$A); }
    public static function error()   { $A = func_get_args(); return self::log(E_USER_ERROR,$A); }

    /* ================ ФУНКЦИИ ДЛЯ РАБОТЫ С ПУТЯМИ ================ */
    protected static function _initPaths() {
      if (is_null(self::$roots)) {
        $root = realpath(dirname(__FILE__)).DIRECTORY_SEPARATOR;
        self::$roots = array($root);
      }
      if (is_null(self::$deploy))
        self::$deploy = realpath($_SERVER['DOCUMENT_ROOT']).DIRECTORY_SEPARATOR;
      if (is_null(self::$sdirs))
        self::$sdirs = array(
          'templates' => 'content/tpl/pages',
          'chunks'    => 'content/tpl/chunks',
          'snippets'  => 'content/tpl/snippets',
          'language'  => 'content/lang'
        );
    }

    public static function getPaths() {
      self::_initPaths();
      $ret   = self::$roots;
      $ret[] = realpath($_SERVER['DOCUMENT_ROOT']).DIRECTORY_SEPARATOR;
      return $ret;
    }

    public static function addRoot($path,$index='') {
      self::_initPaths();
      $P = empty($path) ? dirname($_SERVER['SCRIPT_FILENAME']) : $path;
      $P = realpath($P);
      if (!empty($P))
        if (!in_array($P,self::$roots) && ($P != self::$deploy)) {
          $K = $index;
          if (empty($K) || isset(self::$roots[$K])) $K = 'path'.count(self::$roots);
          self::$roots[$K] = $P;
          return true;
        }
      return false;
    }

    public static function searchDir($name,$value=null) {
      self::_initPaths();
      if (!is_null($value)) if (isset(self::$sdirs[$name])) self::$sdirs[$name] = $value;
      return isset(self::$sdirs[$name]) ? self::$sdirs[$name] : false;
    }

    /* ================ ФУНКЦИИ ДЛЯ РАБОТЫ С ФАЙЛОВОЙ СИСТЕМОЙ ================ */
    public static function search($path,$name,$exts='') {
      $EX    = is_array($exts) ? $exts : explode(',',strval($exts));
      $ret   = false;
      $roots = self::getPaths();
      foreach ($roots as $p) {
        $P = $p.(empty($path) ? '' : self::dir2path("$path/"));
        foreach ($EX as $ext) {
          $fn = $P.$name.(empty($ext) ? '' : '.'.$ext);
          if (is_file($fn)) $ret = $fn;
        }
        if (self::localized()) {
          $lang  = self::language();
          foreach ($EX as $ext) {
            $fn = $P.$lang.DIRECTORY_SEPARATOR.$name.(empty($ext) ? '' : '.'.$ext);
            if (is_file($fn)) $ret = $fn;
          }
        }
      }
      return $ret;
    }

    public static function path2dir($p) { return str_replace(chr(0x5c),'/',$p); }

    public static function dir2path($d) {
      if (DIRECTORY_SEPARATOR == '/') return self::path2dir($d);
      return str_replace('/',DIRECTORY_SEPARATOR,$d);
    }

    /* ================ ФУНКЦИИ ОБРАБОТКИ ПОЛЕЙ ================ */
    public static function fieldHandlers($fd,$fv) {
      $h = isset($fd['handler']) ? self::options($fd['handler'],'phps_hdlr_') : 0;
      $n = isset($fd['isnull'])  ? self::getBool($fd['isnull'])               : true;
      if ($n && is_null($fv)) return null;
      $v = $fv;
      if (isset($fd['regexp']) && isset($fd['replace']))
        $v = preg_replace($fd['regexp'],$fd['replace'],$v);
      if ($h != 0) {
        $hdlrs = array(
          'striptags' => 'strip_tags',
          'html'      => 'htmlspecialchars',
          'escape'    => 'addslashes',
          'nl2br'     => 'nl2br',
          'urlencode' => 'urlencode',
          'uuencode'  => 'convert_uuencode',
          'base64'    => 'base64_encode',
          'md5'       => 'md5',
          'bool'      => 'getVool',
          'intval'    => 'intval',
        );

        foreach ($hdlrs as $c => $f) {
          if ($h & self::c("hdlr_$c",0)) {
            $func = self::c("hdlr_$c"."_func",'');
            switch ($c) {
              case 'bool' : $v = is_callable($func) ? $func($v) : self::$f($v); break;
              case 'html' : $v = is_callable($func) ? $func($v) : htmlspecialchars($v,ENT_QUOTES); break;
              case 'nl2br': $v = is_callable($func) ? $func($v) : nl2br($v,true); break;
              default     : $v = is_callable($func) ? $func($v) : $f($v);
            }
          }
        }
      }
      return $v;
    }

    public static function fieldValue($fd,$fv,$sql=false) {
      $p = isset($fd['processing']) ? self::getBool($fd['processing']) : true;
      $d = isset($fd['default'])    ? $fd['default']                   : '';
      $t = isset($fd['type'])       ? $fd['type']                      : 'int';
      $n = isset($fd['isnull'])     ? self::getBool($fd['isnull'])     : true;
      if ($n && is_null($fv)) return $sql ? 'NULL' : null;
      $v = $p ? $fv : $d;
      $v = self::fieldHandlers($fd,$v);
      switch($t) {
        case 'datetime':
          if (($v === true) && $sql) return 'NOW()';
          $v = ($v === true) ? self::now() : $v;
          return $sql ? "'$v'" : $v;
        case 'tinyint': case 'byte': case 'smallint': case 'word': case 'int':
        case 'integer': return intval($v);
        default       : return $sql ? "'$v'" : $v;
      }
    }

    public static function requestData($fields,$pref='',$src='post') {
      $_ = array('success' => array(),'errors' => array());

      switch ($src) {
        case 'get': $SRC = $_GET; break;
        default   : $SRC = $_POST;
      }

      foreach ($fields as $fn => $fd) {
        $d = isset($fd['default'])    ? $fd['default']                   : '';
        $r = isset($fd['required'])   ? self::getBool($fd['required'])   : true;
        $p = isset($fd['processing']) ? self::getBool($fd['processing']) : true;
        if (!$p) continue;
        if (isset($SRC[$pref.$fn])) {
          $v = $SRC[$pref.$fn];
          if (isset($fd['strip'])) $v = preg_replace($fd['strip'],'',$v);
          if ($r && empty($v)) {
            $_['errors'][$fn] = 0;
            continue;
          }
          if (isset($fd['regexp'])) {
            if (preg_match($fd['regexp'],$v)) {
              // Здесь может быть Ваша реклама =)
            } else { $_['errors'][$fn] = 1; }
          }

          if (!isset($_['errors'][$fn])) {
            $v = self::fieldHandlers($fd,$v);
            $_['success'][$fn] = $v;
          }
        } else {
          if ($r) {
            $_['errors'][$fn] = 0;
          } else { $_['success'][$fn] = $d; }
        }
      }
      return $_;
    }

    public static function fieldsErrors($errors,$tpl='') {
      $T = !empty($tpl) ? $tpl : '<span class="error">[+message+]</span>';
      $_ = '';
      foreach ($errors as $en => $ed) {
        switch ($ed) {
          case 0 : $msg = "[%field_not_set.hint &field.name=`[%field.$en%]`%]"; break;
          default: $msg = "[%field_not_correct.hint &field.name=`[%field.$en%]`%]";
        }
        $_.= str_replace('[+message+]',$msg,$T);
      }
      return $_;
    }

    public static function requestVar($key,$def=false,$udd=null) {
      $_  = $def;
      $ud = $udd;
      if (isset($_POST[$key])) {
        if (is_null($udd)) $ud = false;
        $_ = $ud ? urldecode($_POST[$key]) : $_POST[$key];
      } elseif (isset($_GET[$key])) {
        if (is_null($udd)) $ud = true;
        $_ = $ud ? urldecode($_GET[$key]) : $_GET[$key];
      }
      return $_;
    }

    /* ================ ФУНКЦИИ ПАРСЕРОВ И ОБРАБОТКИ ТЕКСТА ================ */
    public static function parserDepth($c=null) {
      if (!is_null($c)) {
        $V = intval($c);
        self::$PLM = $V > 8 ? $V : 8;
      }
      return self::$PLM;
    }

    public static function lines($data) { return preg_split('~\\r\\n?|\\n~',$data); }

    public static function placeholders($tpl='',$data=array(),$clear=false) {
      if (!is_array($data)) return $tpl;
      if (empty($tpl))      return $tpl;
      $_ = $tpl;
      foreach ($data as $k => $v) $_ = str_replace("[+$k+]",$v,$_);
      if ($clear) return self::clearPlaceholders($_);
      return $_;
    }

    public static function clearPlaceholders($tpl='') {
      $t = '|\[\+([\w\.\-]+)\+\]|si';
      if (preg_match($t,$tpl)) return preg_replace($t,'',$tpl);
      return $tpl;
    }

    public static function rows($rows,$tpl,$pref="",$a=false) {
      $O = $a ? array() : '';
      $e = true;
      foreach ($rows as $fn => $fv) {
        $_ = str_replace(
          array('[+row.evenodd+]','[+row.key+]'),
          array(($e?'even':'odd'),$fn),
          self::placeholders($fv,$tpl,$pref)
        );
        if ($a) { $O[] = $_; } else { $O.= $_; }
        $e = !$e;
      }
      return $O;
    }

    public static function getSystemInfo($key) {
      $dd = explode('.',$key);
      $dk = $dd[0];

      $asz = array('kb' => 1000,'mb' => 1000000,'gb' => 1000000000);
      $atm = array('ms' => 1000,'us' => 1000000,'ns' => 1000000000);

      switch ($dk) {
        case 'memory':
        case 'mem':
          $v = memory_get_usage();
          if (count($dd) > 1) if (array_key_exists($dd[1],$asz)) $v /= $asz[$dd[1]];
          return strval(round($v,2));
        case 'time':
          $v = self::microTime();
          if (count($dd) > 1) if (array_key_exists($dd[1],$atm)) $v *= $atm[$dd[1]];
          return strval(round($v,2));
        case 'totalmem':
          $v = '<!-- XBLIB:TOTALMEM ';
          if (count($dd) > 1) if (array_key_exists($dd[1],$asz)) $v.= $dd[1];
          return "$v -->";
        case 'totaltime':
          $v = '<!-- XBLIB:TOTALTIME ';
          if (count($dd) > 1) if (array_key_exists($dd[1],$atm)) $v.= $dd[1];
          return "$v -->";
        case 'log'      : return '<!-- XBLIB:LOG -->';
        case 'logstatus': return '<!-- XBLIB:LOGSTATUS -->';
        default: return false;
      }
    }

    public static function getLanguageKey($key,&$prop,$supported=array('caption','hint')) {
      $_    = explode('.',$key);
      $l    = count($_) - 1;
      $prop = 'caption';
      if (($l > 0) && is_array($supported)) if (in_array($_[$l],$supported)) {
        $prop = $_[$l];
        unset($_[$l]);
      }
      $k = implode('.',$_);
      return $k;
    }

    public static function getChunk($k) {
      if ($fn = self::search(self::searchDir('chunks'),$k,'html,htm,tpl'))
        return strval(@file_get_contents($fn));
      return '';
    }

    public static function extractData($tpl,$def=null,$cln='') {
      $_tpl = $tpl;
      $out  = array('data' => array(),'body' => '','debug' => null);
      $rex  = '#\<\!--(?:\s+)DATA'
        . ($cln != '' ? '(?:\s+)'.$cln : '')
        . '\:[+key+](?:\s+)`([^\`]*)`(?:\s+)--\>#si';
      if (is_array($def)) {
        foreach ($def as $key => $val) {
          $t = str_replace('[+key+]',strtolower($key),$rex);
          $out['data'][$key] = $val;
          if ($r = preg_match_all($t,$_tpl,$_,PREG_PATTERN_ORDER)) {
            $out['data'][$key] = $_[1][0];
            $_tpl = preg_replace($t,'',$_tpl);
          }
        }
      } else {
        $rex = str_replace('[+key+]','([\w\-\.]+)',$rex);
        $out['rex'] = $rex;
        if (preg_match_all($rex,$_tpl,$am,PREG_SET_ORDER)) {
          foreach ($am as $d) $out['data'][$d[1]] = $d[2];
          $out['debug'] = $am;
        }
      }
      $out['body'] = $_tpl;
      return $out;
    }

    public static function parseArguments($data=null) {
      $arguments = array();
      if (!empty($data)) {
        if ($_ = preg_match_all('|\&([\w\-\.]+)\=`([^`]*)`|si',$data,$ms,PREG_SET_ORDER)) {
          foreach ($ms as $pr) $arguments[$pr[1]] = $pr[2];
        }
      }
      return $arguments;
    }

    public static function parseExtensions($value,$ext='',$parser=null) {
      $RET = $value;
      if (!empty($ext)) {
        if ($_ = preg_match_all('|\:([\w\-\.]+)((\=`([^`]*)`)?)|si',$ext,$ms,PREG_SET_ORDER)) {
          for($c = 0; $c < count($ms); $c++) {
            $a = $ms[$c][1];
            $v = $ms[$c][4];
            if (in_array($a,array('is','eq','isnot','neq','lt','lte','gt','gte'))) {
              $cond = false;
              switch ($a) {
                case 'is':
                case 'eq':  $cond = ($value == $v); break;
                case 'isnot':
                case 'neq': $cond = ($value != $v); break;
                case 'lt':  $cond = ($value <  $v); break;
                case 'lte': $cond = ($value <= $v); break;
                case 'gt':  $cond = ($value >  $v); break;
                case 'gte': $cond = ($value >= $v); break;
              }
              $cthen = $RET;
              if ($ms[$c+1][1] == 'then') {
                $c++;
                $cthen = $ms[$c][2];
              }
              $celse = $RET;
              if ($ms[$c+1][1] == 'else') {
                $c++;
                $celse = $ms[$c][2];
              }
              $RET = $cond ? $cthen : $celse;
            } else {
              $TRET = trim($RET);
              $EMP  = (empty($TRET) && ($TRET !== '0'));
              switch ($a) {
                case 'empty'   : $RET = $EMP ? $v : $TRET; break;
                case 'notempty': $RET = $EMP ? '' : str_replace('[+value+]',"$TRET",$v); break;
                case 'css': case 'import': case 'css-link': case 'js-link': case 'js': case 'file':
                  if (!empty($v)) {
                    $tpls = array(
                      'css-link' => '<link rel="stylesheet" type="text/css" href="[+content+]" />',
                      'css'      => '<style type="text/css">'."\r\n[+content+]</style>",
                      'js-link'  => '<script type="text/javascript" src="[+content]"></script>',
                      'js'       => '<script type="text/javascript">'."\r\n[+content+]</script>",
                      'import'   => '@import url("[+content+]");'
                    );
                    $tpl = isset($tpls[$a]) ? $tpls[$a] : "[+content+]\r\n\r\n";
                    if (in_array($a,array('css','js'))) {
                      $fn = phPhylloscope::dir2path("$TRET/$v");
                      $RET = '';
                      if ($_ = glob($fn)) foreach ($_ as $f) $RET.= @file_get_contents($f)."\r\n\r\n";
                      if (!empty($RET)) $RET = str_replace('[+content+]',$RET,$tpl);
                    } else { $RET = str_replace('[+content+]',"$TRET/$v",$tpl); }
                  }
                  break;
                case 'link': case 'link-external':
                  if (!empty($RET)) {
                    $tpls = array(
                      'link'          => '<a href="[+content+]">[+value+]</a>',
                      'link-external' => '<a href="[+content+]" target="_blank">[+value+]</a>'
                    );
                    $tpl = isset($tpls[$a]) ? $tpls[$a] : '[+content+]';
                    $val = empty($v) ? $RET : $v;
                    $RET = str_replace(array('[+content+]','[+value+]'),array($RET,$val),$tpl);
                  }
                  break;
                case 'links':
                  $RET = preg_replace(self::rexURL,'<a href="\1://\2.\3/\4"[+C+]>\1://\2.\3/\4</a>',$RET);
                  $RET = preg_replace(self::rexMailTo,'<a href="mailto:\1@\2.\3">\1@\2.\3</a>',$RET);
                  $RET = str_replace('[+C+]',(empty($v) ? '' : " $v"),$RET);
                  break;
                case 'include':
                  if (!empty($v)) {
                    $_   = phPhylloscope::dir2path("$TRET/$v");
                    $RET = '';
                    if (is_file($_)) $RET = include($_);
                  }
                  break;
                case 'list':
                  $tpl   = empty($v) ? '<li[+classes+]>[+item+]</li>' : $v;
                  $items = self::lines($RET);
                  $RET   = '<ul>';
                  for ($c = 0; $c < count($items); $c++) {
                    $CL = '';
                    if ($c == 0) $CL = ' classes="first"';
                    if ($c == (count($items) - 1)) $CL = ' classes="last"';
                    $IC  = explode('|',$items[$c]);
                    $_ = str_replace(array('[+classes+]','[+item+]'),array($CL,$items[$c]),$tpl);
                    for ($ic = 0; $ic < count($IC); $ic++)
                      $_ = str_replace("[+item.$ic+]",$IC[$ic],$_);
                    $RET.= $_;
                  }
                  $RET.= '</ul>';
                  break;
                default:
                  if (method_exists($parser,'onExtension'))
                    if ($_ = $parser->onExtension($a,$v,$RET)) $RET = $_;
              }
            }
          }
        }
      }
      return $RET;
    }

    public static function parseSystemInfo($data) {
      if (empty($data)) return $data;
      $st = self::microTime();
      $tm = memory_get_usage();
      $DTPL = <<<DATA
<div class="[+stype+] [+class+]">
  <ul>
    <li class="datetime">[+datetime+]</li>
    <li class="stype">[+stype.text+]</li>
    <li class="class">[+class.text+]</li>
    <li class="message">[+message.text+]</li>
    <li class="data"><ul>
      <li>[+data.0+]</li>
      <li>[+data.1+]</li>
      <li>[+data.2+]</li>
      <li>[+data.3+]</li>
      <li>[+data.4+]</li>
    </ul></li>
    <li class="line">[+line+]</li>
    <li class="file">[+file+]</li>
  </ul>
</div>
DATA;
      $ph = array(
        'TOTALTIME'    => round($st,2),
        'TOTALTIME ms' => round($st*1000,2),
        'TOTALTIME us' => round($st*1000000,2),
        'TOTALTIME ns' => round($st*1000000000,2),
        'TOTALMEM'     => round($tm,2),
        'TOTALMEM kb'  => round($tm/1000,2),
        'TOTALMEM mb'  => round($tm/1000000,2),
        'TOTALMEM gb'  => round($tm/1000000000,2),
        'LOG'          => self::getLog(array('parser' => $DTPL)),
        'LOGSTATUS'    => 'not-empty'
      );

      if (empty($ph['LOG'])) {
        $ph['LOGSTATUS'] = 'empty';
        $ph['LOG'] = '<span class="log-empty">'.(
          self::localized() ? '[%log_empty.hint%]' : 'Log is empty'
          ).'</span>';
      }

      $O = $data;
      foreach ($ph as $phk => $phv) $O = str_replace("<!-- XBLIB:$phk -->",$phv,$O);
      return $O;
    }

    public static function parserTags() {
      if (is_null(self::$parserTags)) {
        $map = array(
          'chunk'       => array('\{\{','\}\}'),
          'constant'    => array('\{\*','\*\}'),
          'path'        => array('\{\(','\)\}'),
          'dir'         => array('\{\/','\/\}'),
          'deploy'      => array('\{\:','\:\}'),
          'language'    => array('\[\%','\%\]'),
          'setting'     => array('\[\(','\)\]'),
          'placeholder' => array('\[\*','\*\]'),
          'debug'       => array('\[\^','\^\]'),
          'snippet'     => array('\[\!','\!\]'),
          'local'       => array('\[\+','\+\]'),
        );
        self::$parserTags = array();
        foreach ($map as $k => $d) {
          self::$parserTags[$k] = "#".$d[0]
            . '([\w\.\-]+)'                       // Alias
            . '((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)'    // Extensions
            . '((:?\s*\&([\w\-\.]+)=`([^`]*)`)*)' // Parameters
            . $d[1].'#si';
        }
      }

      return self::$parserTags;
    }

    public static function sanitize($data='',$rex=null) {
      self::parserTags();
      $O = $data;
      $_rex = is_null($rex) ? array_keys(self::$parserTags) : explode(',',$rex);
      foreach (self::$parserTags as $c => $t)
        if (in_array($c,$_rex))
          if (preg_match($t,$O)) $O = preg_replace($t,'',$O);
      return $O;
    }

    public static function parse($owner,$data='',$additional=null,$rex=null) {
      static $_lvl = -1;
      self::parserTags();
      $_rex = is_null($rex) ? array_keys(self::$parserTags) : explode(',',$rex);
      $O = strval($data);
      if (empty($O)) return $O;
      $_lvl++;
      if ($_lvl <= self::$PLM) {
        if (method_exists($owner,'onParserLevel')) $owner->onParserLevel($_lvl,$additional);
        foreach (self::$parserTags as $c => $t) if (in_array($c,$_rex)) {
          if (method_exists($owner,"parse_$c")) {
            if (preg_match($t,$O))
              $O = preg_replace_callback($t,array($owner,"parse_$c"),$O);
          } else {
            if (method_exists($owner,'onCustomParser'))
              if ($_ = $owner->onCustomParser($c,$O)) {
                $O  = $_;
              } else { self::error('parser:notimplemented',$c); }
          }
        }
      } else { $O = self::sanitize($O,$rex); }
      $_lvl--;
      if (method_exists($owner,'onParserLevel')) $owner->onParserLevel($_lvl,$additional);
      if ($_lvl == -1) $O = self::sanitize($O,$rex);
      return $O;
    }

    /* ================ ПРОЧИЕ ФУНКЦИИ ================ */
    public static function grepSearch($ph,$gfn='*',$sl=30,$so=0,$sm=4096,$o=0) {
      $op = self::options($o,'xbps_gso_');

      $cmd = 'grep "'.$ph.'" '.$gfn;
      foreach (array(
                 'ignore_case' => 'i',
                 'whole_words' => 'w',
                 'match_all'   => 'x',
                 'no_binary'   => 'I',
                 'only_count'  => 'c'
               ) as $k => $v) if (($op & self::c("gso_$k",0)) > 0) $cmd.= " -$v";
      if (($op & self::c('gso_regular',0)) > 0)
        $cmd.= (($op & self::c('gso_ereg',0)) > 0 ? ' -E':' -P');

      if (($op & self::c('gso_only_count',0)) > 0) return exec($cmd);

      $__c = 0;
      $__s = true;
      $__r = array();
      $_sp = popen($cmd,'r');
      $_OP_OF = ($op & self::c('gso_one_file',0)) > 0;
      while ($__s) {
        if ($_ = fgets($_sp,$sm)) {
          if ($__c >= ($sl * $so)) {
            if (!$_OP_OF) {
              $_  = explode(':',$_);
              $fn = array_shift($_);
              $_  = implode(':',$_);
              $__r[$fn][] = $_;
            } else { $__r[] = $_; }
          }
          $__c++;
          if ($__c >= ($sl * $so + $sl)) $__s = false;
        } else { $__s = false; }
      }
      return $__r;
    }
  }

  /* ================ СЕКЦИЯ КЛАССОВ БИБЛИОТЕКИ ================ */
  /**
   * Class phPSClass
   * @property-read string $GUID
   */
  class phPSClass {
    protected $_eventsMap  = array();

    function __construct() {}

    function __get($n) {
      if (method_exists($this,"get_$n"))       { $f = "get_$n"; return $this->$f();
      } elseif (property_exists($this,"_$n"))  { $f = "_$n";    return $this->$f;
      } elseif (method_exists($this,"set_$n")) { $e = 'obj_prop_write_only';
      } else {
        $_ = property_exists($this,$n);
        $e = $_?'obj_prop_protected':'obj_prop_not_exists';
      }
      return $this->error($e,$n);
    }

    function __set($n,$v) {
      if (method_exists($this,"set_$n"))       { $f = "set_$n"; return $this->$f($v);
      } elseif (method_exists($this,"get_$n")) { $e = 'obj_prop_read_only';
      } else {
        $_ = property_exists($this,$n);
        $e = $_?'obj_prop_protected':'obj_prop_not_exists';
      }
      return $this->error($e,$n);
    }

    function __call($n,$p) {
      if (isset($this->_eventsMap[$n])) return $this->_eventsMap[$n];
      if (preg_match('/^on(\w+)$/',$n)) return true;
      return $this->error('obj_method_not_exists',$n);
    }

    function __toString() { return $this->GUID; }

    protected function get_GUID()  { return strtoupper(md5(get_class($this))); }

    protected function _log($T,$args) {
      $A = $args; $A[0] = get_class($this).':'.$A[0];
      return phPhylloscope::log($T,$A);
    }

    public function errorIncompatible($e,$v) {
      return $this->error('obj_data_incompatible',$e,
        is_object($v) ? get_class($v) : gettype($v)
      );
    }

    public function message() { $A = func_get_args(); return $this->_log(0,$A); }
    public function notice()  { $A = func_get_args(); return $this->_log(E_USER_NOTICE,$A); }
    public function warning() { $A = func_get_args(); return $this->_log(E_USER_WARNING,$A); }
    public function error()   { $A = func_get_args(); return $this->_log(E_USER_ERROR,$A); }
  }

  /**
   * Class phPSLogger
   * @property      int    $debug
   * @property      string $file
   * @property      string $comma
   * @property      string $template
   * @property-read array  $data
   *
   * @method bool onLog(array $i)
   */
  class phPSLogger {
    protected $_debug      = 0;
    protected $_file       = '';
    protected $_comma      = ';';
    protected $_template   = '';
    protected $_data       = array();

    function __construct($o=0) {
      $this->file     = '';
      $this->debug    = $o == 0 ? 0x0f : $o;
      $this->template = <<<TPL
<div class="phps-logger [+stype+] [+class+]">
  <ul>
    <li class="datetime">[+datetime+]</li>
    <li class="stype">[+stype.text+]</li>
    <li class="class">[+class.text+]</li>
    <li class="message">[+message.text+]</li>
    <li class="data">[+data+]</li>
    <li class="line">[+line+]</li>
    <li class="file">[+file+]</li>
  </ul>
</div>
TPL;
    }

    function __toString() { return $this->dump(); }

    function __get($n) { $_n = "_$n"; return property_exists($this,$_n) ? $this->$_n : false; }

    function __set($n,$v) {
      switch ($n) {
        case 'debug': $this->_debug = phPhylloscope::options($v,'phps_log_'); return $this->_debug;
        case 'comma':
        case 'template':
          $_n = "_$n";
          $this->$_n = strval($v);
          return $this->$_n;
        case 'file':
          $fn = self::logFile();
          if (!empty($v)) if (is_file($v)) $fn = $v;
          $this->_file = $fn;
          return $this->_file;
      }
      return false;
    }

    function __call($n,$p) { if (preg_match('/^on(\w+)$/',$n)) return true; return false; }

    protected function log_screen($input,$tpl='') {
      $i = $input;
      $e = array();
      if (isset($i['data'])) $e = is_array($i['data']) ? $i['data'] : explode('|',strval($i['data']));
      if (!isset($i['datetime']) || !isset($i['microtime'])) {
        $dt = new DateTime('now');
        if (!isset($i['datetime']))  $i['datetime']  = $dt->format('Y-m-d H:i:s');
        if (!isset($i['microtime'])) $i['microtime'] = $dt->format('u');
      }

      $i['class.text'] = $i['class'];
      if (phPhylloscope::localized()) {
        $i['message.text'] = '[%'.$i['message'].'.hint%]';
        $i['stype.text']   = '[%'.$i['stype'].'%]';
      } else {
        $i['message.text'] = $i['message'];
        $i['stype.text']   = $i['stype'];
      }

      $_  = empty($tpl) ? $this->_template : $tpl;

      foreach ($i as $k => $v) {
        $_ = str_replace('[+'.$k.'+]',trim($v),$_);
        if ($k == 'message.text') {
          foreach ($i as $ki => $vi) $_ = str_replace('[+'.$ki.'+]',trim($vi),$_);
        }
      }
      if (count($e) > 1) {
        foreach ($e as $k => $v) $_ = str_replace('[+data.'.$k.'+]',trim($v),$_);
      }

      return $_;
    }

    protected function log_file($input) {
      if (is_file($this->_file)) {
        if ($f = fopen($this->_file,'a')) {
          fwrite($f,implode($this->_comma,$input)."\r\n");
          fclose($f);
          return true;
        }
      }
      return false;
    }

    public function dump($a=false,$tpl='') {
      $_ = array();
      foreach ($this->_data as $item) {
        $_tpl = is_array($tpl) ? '' : $tpl;
        if (is_array($tpl) && ($item['class'] != '')) {
          if (isset($tpl[$item['class']])) {
            if (is_array($tpl[$item['class']])) {
              if (isset($tpl[$item['class']][$item['stype']]))
                $_tpl = $tpl[$item['class']][$item['stype']];
            } else { $_tpl = strval($tpl[$item['class']]); }
          }
        }
        $_[] = $this->log_screen($item,$_tpl);
      }
      if (count($this->_data) < 1) return $a ? false : '';
      return $a ? $_ : implode("\r\n\r\n",$_);
    }

    public function log($mt,$args) {
      $md  = count($args) == 1 ? (is_array($args[0]) ? $args[0] : array($args[0])) : $args;
      $d   = array();

      $s = 'message';
      $t = $mt;
      switch ($mt) {
        case E_USER_NOTICE:  $s = 'notice';  break;
        case E_USER_WARNING: $s = 'warning'; break;
        case E_USER_ERROR:   $s = 'error';   break;
        default: $t = 0;
      }

      if (is_array($md[0])) {
        $d = $md[0];
      } else {
        $m = strval(array_shift($md));
        if (strpos($m,':')) {
          $_ = explode(':',$m);
          $m = $_[1];
          $_ = $_[0];
        } else { $_ = ''; }
        $d['message'] = $m;
        $d['class']   = $_;
        $d['data']    = count($md) > 0 ? implode(' | ',$md) : '';
      }

      $d['type']  = $t;
      $d['stype'] = $s;

      $b = debug_backtrace();
      $f = $b[count($b)-1];

      $i = array(
        'type'    => $t,
        'stype'   => $s,
        'class'   => isset($d['class'])   ? $d['class']   : '',
        'message' => isset($d['message']) ? $d['message'] : 'unknown',
        'data'    => isset($d['data'])    ? $d['data']    : '',
        'line'    => isset($f['line'])    ? $f['line']    : 0,
        'file'    => isset($f['file'])    ? $f['file']    : ''
      );

      $r  = true;
      $tm = 'on'.ucfirst($s);
      if (method_exists($this,$tm)) $r = $this->$tm($i);

      $this->_data[] = $i;
      foreach (array('trigger','file','screen','user') as $t) {
        $f = constant(strtoupper('phps_log_'.$s.'_'.$t));
        if ((($this->_debug & $f) != 0) && $r) switch ($t) {
          case 'file'   : $this->log_file($i); break;
          case 'trigger':
            trigger_error(str_replace(array(
                "\r\n","\r","\n"
              ),'',$this->log_screen($i)),
              $i['type'] != 0 ? $i['type'] : E_USER_NOTICE);
            break;
          case 'screen' : echo $this->log_screen($i); break;
          case 'user'   : if (function_exists('phps_user_log')) phps_user_log($i); break;
        }
      }

      return false;
    }

    public static function logFile() {
      $fn = dirname($_SERVER['SCRIPT_FILENAME']).DIRECTORY_SEPARATOR
          . basename($_SERVER['SCRIPT_FILENAME'],'.php').'.log';
      return $fn;
    }
  }

  /**
   * Class phPSParser
   * @property      string     $template
   * @property-read string     $templateName
   * @property      array      $data
   * @property      array      $settings
   * @property      array      $dictionary
   * @property      int        $notify
   * @property-read int        $level
   * @property-read array      $levels
   *
   * @method string onTemplate(string $s)
   * @method string onCustomParser(string $k,string $o)
   * @method string onParse(string $o)
   * @method string onDebug(string $k)
   * @method string onNoElement(string $e,string $k)
   * @method string onLocalPlaceholder(string $k)
   */
  class phPSParser extends phPSClass {
    protected $_template     = '';
    protected $_templateName = '';

    protected $_data       = array();
    protected $_settings   = array();
    protected $_dictionary = array();

    protected $_notify = -1;
    protected $_level  = -1;
    protected $_levels = array();

    function __construct($tpl) {
      parent::__construct();
      $this->template    = $tpl;
      $this->_dictionary = phPhylloscope::dictionary();
    }

    function __toString() { return $this->parse(); }

    function __call($n,$p) {
      switch ($n) {
        case 'onCustomParser'    :
        case 'onElement'         :
        case 'onNoElement'       : return '';
        case 'onTemplate'        :
        case 'onParse'           : return isset($p[0]) ? $p[0] : '';
        case 'onLocalPlaceholder':
          if (!isset($p[0])) return '';
          return isset($this->_data[$p[0]]) ? strval($this->_data[$p[0]]) : '';
      }
      if (preg_match('/^on(\w+)$/',$n)) return true;
      return $this->error('obj_method_not_exists',$n);
    }

    protected function set_template($name) {
      if (!empty($name)) {
        $this->_levels[-1] = array('element' => 'template','key' => $name);
        $fn = phPhylloscope::search(phPhylloscope::searchDir('templates'),$name,'html,htm,tpl');
        if (empty($fn)) return $this->noElement('template',$name);
        $content = @file_get_contents($fn);
        return $this->setTemplate($content,$name);
      }
      return false;
    }

    protected function set_data($d,$s='data') {
      $N = "_$s";
      if (is_array($d)) {
        if (!empty($this->$N)) {
          foreach ($d as $k => $v) $this->$N[$k] = $v;
        } else { $this->$N = $d; }
      }
      return $this->$N;
    }

    protected function set_settings($d) { return $this->set_data($d,'settings'); }

    protected function set_notify($d) {
      $this->_notify = phPhylloscope::options($d,'phps_pn_');
      return $this->_notify;
    }

    protected function onExtension($name,$arguments=array(),$input='') {
      $result = '';
      if ($fn = phPhylloscope::search(phPhylloscope::searchDir('snippets'),$name,'php,inc'))
        $result = include($fn);
      return strval($result);
    }

    public function onParserLevel($lvl,$data) {
      $this->_level = $lvl;
      foreach (array('element' => 'template','key' => $this->templateName) as $k => $v) {
        $this->_levels[$this->_level][$k] = $v;
        if (!empty($data[$k])) $this->_levels[$this->_level][$k] = $data[$k];
      }
    }

    public function parse_chunk($m)       { return $this->parse_element($m,'chunk'); }
    public function parse_constant($m)    { return $this->parse_element($m,'constant'); }
    public function parse_path($m)        { return $this->parse_element($m,'path'); }
    public function parse_dir($m)         { return $this->parse_element($m,'dir'); }
    public function parse_deploy($m)      { return $this->parse_element($m,'deploy'); }
    public function parse_language($m)    { return $this->parse_element($m,'language'); }
    public function parse_setting($m)     { return $this->parse_element($m,'setting'); }
    public function parse_placeholder($m) { return $this->parse_element($m,'placeholder'); }
    public function parse_debug($m)       { return $this->parse_element($m,'debug'); }
    public function parse_snippet($m)     { return $this->parse_element($m,'snippet'); }
    public function parse_local($m)       { return $this->parse_element($m,'local'); }

    public function parse_element($m,$etype='') {
      $arguments = isset($m[8]) ? phPhylloscope::parseArguments($m[8]) : array();

      $k = $m[1]; $v = ''; $ef = false;

      switch($etype) {
        case 'chunk': if ($v = phPhylloscope::getChunk($k)) $ef = true; break;
        case 'constant':
        case 'path':
        case 'dir':
        case 'deploy':
          $K = in_array($etype,array('dir','path','deploy')) ? strtoupper("phps_$etype"."_$k") : $k;
          if (!empty($K)) {
            $ef = defined($K);
            if ($ef) $v = strval(constant($K));
          }
          break;
        case 'language':
          $prop = 'caption';
          $K = phPhylloscope::getLanguageKey($k,$prop);
          if (isset($this->_dictionary[$K][$prop])) {
            $v = isset($this->_dictionary[$K][$prop]);
          } else { $v = ucfirst(str_replace(array('.','_','-'),' ',$k)); }
          break;
        case 'setting':
          $ef = isset($this->_settings[$k]);
          $v = $ef ? strval($this->_settings[$k]) : '';
          break;
        case 'local': if ($v = $this->onLocalPlaceholder($k)) $ef = true; break;
        case 'placeholder':
          $ef = isset($this->_data[$k]);
          $v = $ef ? strval($this->_data[$k]) : '';
          break;
        case 'debug':
          if ($v = phPhylloscope::getSystemInfo($k)) {
            $ef = true;
          } else { if ($v = $this->onDebug($k)) $ef = true; }
          break;
        case 'snippet':
          if ($_ = $this->onExtension($k,$arguments)) {
            $v  = strval($_);
            $ef = true;
          } else { $ef = ($_ !== false); }
          break;
      }

      if ($ef) {
        if (isset($m[2])) $v = phPhylloscope::parseExtensions($v,$m[2],$this);
        if (!in_array($etype,array('snippet')) && is_array($arguments))
          if (count($arguments) > 0) $v = phPhylloscope::placeholders($v,$arguments);
        if ($v != '') return $this->parse($v,$etype,$k); else return 'trace';
      } else { if ($_ = $this->noElement($etype,$k)) return $_; }

      return "<!-- EMPTY: $etype/$k -->";
    }

    public function parse($d='',$elt='',$key='') {
      if ($this->_level == -1) {
        $this->_levels = array(-1 => array('element' => 'template','key' => $this->templateName));
        $O = $this->_template;
        $P = 2;
      } else { $O = strval($d); $P = 1; }

      for ($c = 0; $c < $P; $c++)
        $O = phPhylloscope::parse($this,$O,array('element' => $elt,'key' => $key));

      if ($this->_level == -1) {
        $O = phPhylloscope::parseSystemInfo($O);
        $O = $this->onParse($O);
      }

      $O = phPhylloscope::clearPlaceholders($O);
      return $O;
    }

    public function setTemplate($f,$n='custom') {
      if ($_ = $this->onTemplate($f)) {
        $_ = phPhylloscope::extractData($_);
        $this->_data         = $_['data'];
        $this->_template     = $_['body'];
        $this->_templateName = $n;
      }
      return $this->_template;
    }

    public function noElement($c,$k) {
      $r = $this->onNoElement($c,$k,$this->_level);
      if ($r) return $r;
      if ($this->_notify != -1)
        if (($this->_notify & phPhylloscope::c('pn_'.$c,0)) == 0)
          return ($c == 'template') ? $this->notice('page_no_template',$k) : "";
      return $this->notice(
        'page_no_element',$c,$k,
        $this->_levels[$this->_level]['element'],
        $this->_levels[$this->_level]['key'],
        $this->_level
      );
    }
  }

  /**
   * Class phPSMailer
   * @property      array  $mailConfig
   * @property      array  $SMTPConfig
   * @property-read bool   $SMTPValid
   * @property      string $mailer
   * @property-read string $charset
   * @property-read string $lastError
   */
  class phPSMailer extends phPSClass {
    protected $_mailConfig = array();
    protected $_SMTPConfig = array();
    protected $_SMTPValid  = false;
    protected $_mailer     = 'mail';
    protected $_charset    = 'utf-8';
    protected $_lastError  = 'ok';
    protected $_debugInfo  = '';

    function __construct($options=0) {
      parent::__construct();
      $this->mailConfig = array();
    }

    protected function set_mailConfig($v) {
      $_ = is_array($v) ? $v : '';
      foreach (array(
                 'version'  => '1.0',
                 'type'     => 'plain',
                 'charset'  => $this->charset,
                 'from'     => 'No Reply',
                 'replyto'  => 'No reply@'.$_SERVER['SERVER_NAME']
               ) as $k => $d) if (!isset($_[$k])) $_[$k] = $d;
      $this->_mailConfig = $_;
      return $_;
    }

    protected function set_SMTPConfig($v) {
      if (!is_array($v)) return false;
      $_ = $v;
      foreach (array('host','user','pass') as $k) if (!isset($_[$k])) return false;
      foreach (array(
                 'port'     => '25',
                 'te'       => '8',
                 'priority' => '3'
               ) as $k => $d) if (!isset($_[$k])) $_[$k] = $d;
      $this->_SMTPConfig = $_;
      if (isset($_['from'])) $this->_mailConfig['replyto'] = $_['from'];
      $this->_SMTPValid  = true;
      return $_;
    }

    protected function set_mailer($v) {
      if ( !in_array($v,array('mail','smtp'))
        || (($v == 'smtp') && !$this->_SMTPValid)
      ) return false;
      $this->_mailer = $v;
      return $this->_mailer;
    }

    protected function mail_socket($socket,$resp,$str='') {
      $sresp = null;
      while (@substr($sresp,3,1) != ' ') {
        if (!($sresp = fgets($socket,256))) {
          $this->error('smtp_error',$resp);
          $this->_lastError = $str;
          return false;
        }
      }
      if (!(substr($sresp,0,3) == $resp)) {
        $this->error('smtp_error',$resp,$str);
        $this->_lastError = $str;
        return false;
      }
      return true;
    }

    protected function mail_encode($s) {
      return '=?'.$this->_mailConfig['charset'].'?B?'.base64_encode($s)."?=";
    }

    public function sendmail($mname,$mto,$subject,$message,$attach=false) {
      $dto = new DateTime('now');
      $bnd = "--".phPhylloscope::keyGen()."\r\n";

      $rec = array();
      if (is_array($mto)) {
        foreach ($mto as $k => $v) {
          if (is_array($mname)) {
            $n = isset($mname[$k]) ? $mname[$k] : 'unknown';
          } else { $n = $mname; }
          $rec[] = '"'.$this->mail_encode($n).'" <'.$v.'>';
        }
      } else { $rec[] = '"'.$this->mail_encode($mname).'" <'.$mto.'>'; }

      $headers = array(
        "MIME-Version" => $this->_mailConfig['version'],
        "Content-Type" => 'text/'.$this->_mailConfig['type'].'; charset="'.$this->_mailConfig['charset'].'"',
        "Content-Transfer-Encoding" => "8bit",
        "From"         => '"'.$this->mail_encode($this->_mailConfig['from']).'" <'.$this->_mailConfig['replyto'].'>',
        "Reply-To"     => '"'.$this->mail_encode($this->_mailConfig['from']).'" <'.$this->_mailConfig['replyto'].'>',
        "Subject"      => $this->mail_encode($subject),
        "To"           => implode(',',$rec),
        "X-Mailer"     => "FastLP ".$this->_mailer,
        "X-Priority"   => $this->_SMTPConfig['priority']
      );

      if (is_array($attach)) if (count($attach) > 0) {
        $headers["Content-Type"] = 'multipart/mixed; boundary="'.$bnd.'"';
        unset($headers["Content-Transfer-Encoding"]);
      }

      $_ = 'Date: '.$dto->format('D, d M Y H:i:s')." UT\r\n";
      foreach ($headers as $k => $v) $_.= "$k: $v\r\n";
      $headers = $_;

      $msg = "$message\r\n";
      if (is_array($attach)) if (count($attach) > 0) {
        $msg = $bnd;
        $msg.= 'Content-Type: text/'.$this->_mailConfig['type'].'; charset="'.$this->_mailConfig['charset'].'"'."\r\n";
        $msg.= "Content-Transfer-Encoding: 8bit\r\n\r\n$message\r\n\r\n$bnd";
        foreach ($attach as $fn) {
          $msg.= 'Content-Type: application/octet-stream; name="'.basename($fn).'"'."\r\n";
          $msg.= "Content-transfer-encoding: base64\r\n";
          $msg.= 'Content-Disposition: attachment; filename="'.basename($fn).'"'."\r\n\r\n";
          $f   = fopen($fn,"rb");
          $msg.= chunk_split(base64_encode(fread($f,filesize($fn))));
          fclose($f);
          $msg.= "\r\n$bnd";
        }
      }

      switch ($this->_mailer) {
        case 'smtp':
          if(!$socket = fsockopen($this->_SMTPConfig['host'],$this->_SMTPConfig['port'],$en,$es,30)) {
            $this->error('smtp_error',$en,$es);
            return false;
          }

          if (!$this->mail_socket($socket,"220",'no_socket')) return false;

          $lines = array(
            array("250","EHLO ".$this->_SMTPConfig['host'],"no_ehlo"),
            array("334","AUTH LOGIN","no_auth"),
            array("334",base64_encode($this->_SMTPConfig['user']),"no_login"),
            array("235",base64_encode($this->_SMTPConfig['pass']),"no_pass"),
            array("250","MAIL FROM: <".$this->_mailConfig['replyto'].">","no_from")
          );

          if (is_array($mto)) {
            foreach ($mto as $mtoi)
              $lines[] = array("250","RCPT TO: <".$mtoi.">","no_to");
          } else { $lines[] = array("250","RCPT TO: <".$mto.">","no_to"); }

          $lines[] = array("354","DATA","no_data");
          $lines[] = array("250","$headers\r\n$msg\r\n.","no_mail");

          foreach ($lines as $line) {
            fputs($socket,$line[1]."\r\n");
            if (!$this->mail_socket($socket,$line[0],$line[2])) {
              fclose($socket);
              return false;
            }
          }

          $this->_lastError = 'ok';
          fputs($socket,"QUIT\r\n");
          fclose($socket);
          return true;
        default:
          $ret = true;
          $nst = array();
          foreach ($rec as $to) {
            if (!mail($to,$subject,$msg,$headers)) { $ret = false; $nst[] = $to; }
          }
          $this->_lastError = $ret ? 'ok' : 'not sent by semdmail to: '.implode(',',$nst);
          return $ret;
      }
    }
  }

  /**
   * Class phPSMap
   * @property      string $prefix
   * @property      string $context
   * @property-read array  $consts
   * @property-read string $root
   * @property-read string $ds
   */
  class phPSMap {
    protected $_prefix  = 'phps';
    protected $_context = 'path';
    protected $_consts  = array();
    protected $_root    = '';
    protected $_ds      = '/';

    function __construct($prefix='phps') { $this->prefix = $prefix; }
    function __get($name) { $N = "_$name"; return property_exists($this,$N) ? $this->$N : false; }
    function __set($name,$value) {
      switch ($name) {
        case 'context':
          switch ($value) {
            case 'path'  :
              $this->_root = realpath(dirname(__FILE__));
              if ($this->_prefix != 'phps') {
                $CN = strtoupper($this->_prefix.'_path_root');
                if (defined($CN)) {
                  $_ = constant($CN);
                  if (is_dir($_)) $this->_root = $_;
                }
              }
              break;
            case 'dir'   : $this->_root = ''; break;
            case 'deploy': $this->_root = realpath($_SERVER['DOCUMENT_ROOT']); break;
            default: return false;
          }
          $this->_ds = $value == 'dir' ? '/' : DIRECTORY_SEPARATOR;
          $this->_root.= $this->_ds;
          $this->_context = $value;
          return $this->_context;
        case 'prefix':
          if (ctype_alnum($value))       $this->_prefix = $value;
          if ($this->_context == 'path') $this->context = 'path';
          return $this->_prefix;
      }
      return false;
    }

    function __toString() { return $this->_prefix; }

    protected function mapping($cp,$map,$root='') {
      if (!is_array($map)) return false;
      $ret = true;
      foreach ($map as $rdirk => $rdirv) {
        $name  = is_array($rdirv) ? $rdirk : $rdirv;
        if ($this->_context == 'dir')
          if (in_array($name,array('classes','lib','plugins'))) continue;
        $cname = strtoupper($cp.$name);
        $cs    = $name == 'classes' ? 'CL_' : '';
        $this->_consts[$cname] = $root.$name.$this->_ds;
        if (!is_array($rdirv)) continue;
        $ret &= $this->mapping($cp.$cs,$rdirv,$this->_consts[$cname]);
      }
      return $ret;
    }

    public function map($context) {
      if ($this->context = $context) {
        $CP = strtoupper($this->_prefix.'_'.$this->_context.'_');
      } else { return false; }
      if (($this->_prefix != 'phps') && ($this->_context != 'path')) return true;
      $this->_consts[$CP."ROOT"] = $this->_root;
      $map = array(
        'content' => array(
          'lang','cache',
          'tpl' => array('pages','chunks','snippets'),
          'css','js','fonts'
        ),
        'system' => array(
          'classes' => array('common','db','cms','parsers','tools'),
          'lib','tools','controllers','plugins','config'
        )
      );
      return $this->mapping($CP,$map,$this->_root);
    }

    public function constants($fix=false) {
      $contexts = $this->_prefix == 'phps' ? array('path','deploy','dir') : array('path');
      $CP = strtoupper($this->_prefix.'_');
      $this->_consts = array($CP."MAPPED" => true);
      foreach ($contexts as $context) $this->map($context);
      if ($fix) {
        $ret = array('<?php');
        foreach ($this->_consts as $cn => $path) {
          $_ = str_replace(iconv("CP1251","UTF-8",chr(0x5c)),'/',$path);
          $ret[] = '  if (!defined("'.$cn.'")) define("'.$cn.'","'.$_.'");';
        }
        $ret[] = '?>';
        $D  = realpath(dirname(__FILE__)).$this->_ds.'.fix';
        $DF = true;
        if (!is_dir($D)) if (mkdir($D,0666,true)) $DF = false;
        if ($DF) {
          $fname = $D.$this->_ds.$this->_prefix.'.path.config.fix.php';
          file_put_contents($fname,implode("\r\n",$ret));
        }
      }
      return $this->_consts;
    }
  }

  /* ================ СЕКЦИЯ ИНИЦИАЛИЗАЦИИ БИБЛИОТЕКИ ================ */
  if (!defined("PHPS_MAP_FIX"))      define("PHPS_MAP_FIX",false);
  if (!defined("PHPS_MAP_PREFIXES")) define("PHPS_MAP_PREFIXES","phps");

  $phps_path_maps = explode(',',PHPS_MAP_PREFIXES);
  if (!in_array('phps',$phps_path_maps)) {
    $_ = array('phps');
    foreach ($phps_path_maps as $v) $_[] = $v;
    $phps_path_maps = $_;
    unset($_);
  }

  $map = new phPSMap();

  foreach ($phps_path_maps as $V) {
    $_ = strtoupper($V."_MAPPED");
    if (!defined($_)) {
      $map->prefix = $V;
      $_ = $map->constants(PHPS_MAP_FIX);
      foreach ($_ as $k => $v) if (!defined($k)) define($k,$v);
    }
  }

  unset($map,$_,$K,$V,$k,$v,$C);

?>
