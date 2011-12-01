<?php
/**
 * Alternative gettext translator of PHPTAL
 * PHPTAL_GetTextTranslator depends on gettext extension.
 *
 * Usage:
 * <code>
 * $tr = new AlternativeGetTextTranslator('path/to/cache/dir'); // or OS default tmp if not specified.
 * $tr->setLanguage($lang);
 * $tr->addDomain('messages', 'path/to/locale/dir');
 * $tr->useDomain('messages');
 * $phptal->setTranslator($tr);
 * </code>
 *
 * <code>
 * <span i18n:translate="">Home</span>
 * </code>
 *
 * @see http://phptal.org/manual/en/split/gettext.html
 */
require_once 'PHPTAL.php';
require_once 'POParser.php';

class AlternativeGetTextTranslator implements PHPTAL_TranslationService
{
    private $_vars = array();
    private $_currentDomain;
    private $_encoding = 'UTF-8';
    private $_canonicalize = false;

    private $_cachePath = FALSE;
    private $_domainPathes = array();
    private $_languages = array("en_US.utf8", "en_US", "en");
    private $_translations = array();

    public function __construct($cachePath=FALSE)
    {
        $this->setCachePath($cachePath);
        $this->useDomain("messages"); // PHP bug #21965
    }

    public function setEncoding($enc)
    {
        $this->_encoding = $enc;
    }

    public function setCanonicalize($bool)
    {
        $this->_canonicalize = $bool;
    }

    public function setCachePath($path)
    {
        $this->_cachePath = $path;
    }

    public function setLanguage(/*...*/)
    {
        $this->_languages = func_get_args();
        /*
        $langCode = $this->trySettingLanguages(LC_ALL, $langs);
        if ($langCode) return $langCode;

        if (defined("LC_MESSAGES")) {
            $langCode = $this->trySettingLanguages(LC_MESSAGES, $langs);
            if ($langCode) return $langCode;
        }

        throw new PHPTAL_ConfigurationException('Language(s) code(s) "'.implode(', ', $langs).'" not supported by your system');
        */
    }

    private function trySettingLanguages($category, array $langs)
    {
        foreach ($langs as $langCode) {
            putenv("LANG=$langCode");
            putenv("LC_ALL=$langCode");
            putenv("LANGUAGE=$langCode");
            if (setlocale($category, $langCode)) {
                return $langCode;
            }
        }
        return null;
    }

    public function addDomain($domain, $path='./locale/')
    {
        $this->_domainPathes[$domain] = $path;
        $this->useDomain($domain);
    }

    public function useDomain($domain)
    {
        $old = $this->_currentDomain;
        $this->_currentDomain = $domain;
        return $old;
    }

    private function alt_gettext($key)
    {
        //var_dump($this);
        if(!array_key_exists($this->_currentDomain, $this->_domainPathes)) {
            throw new PHPTAL_ConfigurationException('Unknown domain "' . $this->_currentDomain . '".');
        }
        $values = FALSE;
        foreach($this->_languages as $lang) {
            if(isset($this->_translations[$this->_currentDomain][$lang])) {
                $values = $this->_translations[$this->_currentDomain][$lang];
            }
            else {
                $base = $this->_domainPathes[$this->_currentDomain] . '/';
                $pofile = $base . $lang . '/LC_MESSAGES/' . $this->_currentDomain . '.po';
                if(file_exists($pofile)) {
                    $parser = new POParser($this->_cachePath);
                    $values = $parser->parse($pofile);
                    if($values !== FALSE) {
                        $this->_translations[$this->_currentDomain][$lang] = $values;
                    }
                }
            }
            if($values !== FALSE) {
                break;
            }
        }
        if($values !== FALSE) {
            if(array_key_exists($key, $values)) {
                return $values[$key];
            }
        }
        return $key;
    }

    public function setVar($key, $value)
    {
        $this->_vars[$key] = $value;
    }

    public function translate($key, $htmlencode=true)
    {
        if ($this->_canonicalize) $key = self::_canonicalizeKey($key);

        //$value = gettext($key);
        $value = $this->alt_gettext($key);

        if ($htmlencode) {
            $value = htmlspecialchars($value, ENT_QUOTES);
        }
        while (preg_match('/\${(.*?)\}/sm', $value, $m)) {
            list($src, $var) = $m;
            if (!array_key_exists($var, $this->_vars)) {
                throw new PHPTAL_VariableNotFoundException('Interpolation error. Translation uses ${'.$var.'}, which is not defined in the template (via i18n:name)');
            }
            $value = str_replace($src, $this->_vars[$var], $value);
        }
        return $value;
    }

    private static function _canonicalizeKey($key_)
    {
        $result = "";
        $key_ = trim($key_);
        $key_ = str_replace("\n", "", $key_);
        $key_ = str_replace("\r", "", $key_);
        for ($i = 0; $i<strlen($key_); $i++) {
            $c = $key_[$i];
            $o = ord($c);
            if ($o < 5 || $o > 127) {
                $result .= 'C<'.$o.'>';
            } else {
                $result .= $c;
            }
        }
        return $result;
    }
}
