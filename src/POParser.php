<?php
/**
 * Gettext's po file parser.
 * The parsed result would be cached into your file instead of Apache's memory.
 *
 * Gettext is chached in the process memory, so PHP's gettext is cached in Apache,
 * then we can't flush it.
 * In the other hand, it can be flushed simply by deleting file. But you don't
 * do it because the cache file is updated by saving corresponding *.po file.
 *
 * <code>
 * $parser = new POParser('path/to/cache/dir');
 * $translations = $parser->parse('path/to/LC_MESSAGES/messages.po');
 *
 * $text = isset($translations[$key]) ? $translations[$key] : $key;
 * </code>
 *
 * Plural message entries are stored as Array.
 * <code>
 * if(is_array($text)) {
 *     $text = $number == 1 ? $text[0] : $text[1];
 * }
 * </code>
 */

class POParser {

    private $_cachePath;

    public function __construct($cachePath=false)
    {
        if($cachePath !== FALSE) {
            $this->setCachePath($cachePath);
        }
        else {
            if (function_exists('sys_get_temp_dir')) {
                $this->setCachePath(sys_get_temp_dir());
            } elseif (substr(PHP_OS, 0, 3) == 'WIN') {
                if (file_exists('c:\\WINNT\\Temp\\')) {
                    $this->setCachePath('c:\\WINNT\\Temp\\');
                } else {
                    $this->setCachePath('c:\\WINDOWS\\Temp\\');
                }
            } else {
                $this->setCachePath('/tmp/');
            }
        }
    }

    public function setCachePath($path)
    {
        $this->_cachePath = rtrim($path, '/') .'/';
    }

    public function getCachePath()
    {
        return $this->_cachePath;
    }

    public function parse($pofile, $reparse=FALSE)
    {
        if(!file_exists($pofile)) {
            return FALSE;
        }
        if(!$reparse) {
            $result = $this->_tryLoadParsedCache($pofile);
            if($result !== FALSE) {
                return $result;
            }
        }
        $result = $this->parseFromString(file_get_contents($pofile));
        if($result !== FALSE) {
            $caceh = $this->_cacheFilePathFor($pofile);
            file_put_contents($caceh, serialize($result));
        }
        return $result;
    }

    private function parseFromString($str)
    {
        $result = array();
        $msgid = NULL;
        $is_plural = FALSE;
        $plural_id = NULL;
        $msgstr = NULL;
        $lines = explode("\n", $str);
        foreach($lines as $n=>$line) {
            if(preg_match('/^\s*#/', $line, $match)) {
                continue;
            }
            elseif(preg_match('/^\s*msgid\s*"(.*)"/', $line, $match)) {
                if(is_null($msgid) || !is_null($msgstr)) {
                    $result[$msgid] = $msgstr;
                }
                $msgid = stripcslashes($match[1]);
                $is_plural = FALSE;
                $msgstr = NULL;
            }
            elseif(preg_match('/^\s*msgid_plural\s*"(.*)"/', $line, $match)) {
                if(is_null($msgid) || !is_null($msgstr)) {
                    $result[$msgid] = $msgstr;
                }
                $msgid = stripcslashes($match[1]);
                $is_plural = TRUE;
                $msgstr = NULL;
            }
            elseif(preg_match('/^\s*msgstr\s*"(.*)"/', $line, $match)) {
                if(is_null($msgid) || !is_null($msgstr) || $is_plural) {
                    trigger_error('Illegal format at ' . $n, E_USER_WARNING);
                    return FALSE;
                }
                if(isset($result[$msgid])) {
                    trigger_error('Illegal format at ' . $n, E_USER_WARNING);
                    return FALSE;
                }
                $plural_id = NULL;
                $msgstr = stripcslashes($match[1]);
            }
            elseif(preg_match('/^\s*msgstr\[([0-9]+)\]\s*"(.*)"/', $line, $match)) {
                if(is_null($msgid) || !(is_null($msgstr) || is_array($msgstr)) || !$is_plural) {
                    trigger_error('Illegal format at ' . $n, E_USER_WARNING);
                    return FALSE;
                }
                if(isset($result[$msgid])) {
                    trigger_error('Illegal format at ' . $n, E_USER_WARNING);
                    return FALSE;
                }
                if(is_null($msgstr)) {
                    $msgstr = array();
                }
                $plural_id = $match[1];
                if(isset($msgstr[$plural_id])) {
                    return FALSE;
                }
                $msgstr[$plural_id] = stripcslashes($match[1]);
            }
            elseif(preg_match('/^\s*"(.*)"/', $line, $match)) {
                if(is_null($msgid)) {
                    trigger_error('Illegal format at ' . $n, E_USER_WARNING);
                    return FALSE;
                }
                if(is_null($msgstr)) {
                    $msgid .= stripcslashes($match[1]);
                }
                elseif(!$is_plural) {
                    $msgstr .= stripcslashes($match[1]);
                }
                else {
                    $msgstr[$plural_id] .= stripcslashes($match[1]);
                }
            }
        }
        if(!is_null($msgid)) {
            $result[$msgid] = $msgstr;
        }

        return $result;
    }

    private function _cacheFilePathFor($pofile)
    {
        $uid = md5(realpath($pofile)); // TODO more unique name
        return $this->getCachePath() . 'pocache-' . $uid . '.ser';
    }

    private function _tryLoadParsedCache($pofile)
    {
        $stat = lstat($pofile);
        $lastupd = max($stat['mtime'], $stat['ctime']);

        $cache = $this->_cacheFilePathFor($pofile);
        if(!file_exists($cache)) {
            return FALSE;
        }

        $stat = lstat($cache);
        if($stat['mtime'] < $lastupd || $stat['ctime'] < $lastupd) {
            return FALSE;
        }

        return unserialize(file_get_contents($cache));
    }
}

