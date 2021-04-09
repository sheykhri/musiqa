<?php

class i18n {

    protected $filePath = './translate/lang_{LANGUAGE}.ini';
    protected $cachePath = './translate/cache/';
    protected $fallbackLang = 'ru';
    protected $mergeFallback = false;
    protected $prefix = 'L';
    protected $forcedLang = NULL;
    protected $sectionSeparator = '_';
    protected $userLangs = array();
    protected $appliedLang = NULL;
    protected $langFilePath = NULL;
    protected $cacheFilePath = NULL;
    protected $isInitialized = false;
    
    public function __construct($filePath = NULL, $cachePath = NULL, $fallbackLang = NULL, $prefix = NULL) {
        if ($filePath != NULL) {
            $this->filePath = $filePath;
        }

        if ($cachePath != NULL) {
            $this->cachePath = $cachePath;
        }

        if ($fallbackLang != NULL) {
            $this->fallbackLang = $fallbackLang;
        }

        if ($prefix != NULL) {
            $this->prefix = $prefix;
        }
    }

    public function init() {
        if ($this->isInitialized()) {
            throw new BadMethodCallException('This object from class ' . __CLASS__ . ' is already initialized. It is not possible to init one object twice!');
        }

        $this->isInitialized = true;

        $this->userLangs = $this->getUserLangs();

        $this->appliedLang = NULL;
        foreach ($this->userLangs as $priority => $langcode) {
            $this->langFilePath = $this->getConfigFilename($langcode);
            if (file_exists($this->langFilePath)) {
                $this->appliedLang = $langcode;
                break;
            }
        }
        if ($this->appliedLang == NULL) {
            throw new RuntimeException('No language file was found.');
        }

        $this->cacheFilePath = $this->cachePath . '/php_i18n_' . md5_file(__FILE__) . '_' . $this->prefix . '_' . $this->appliedLang . '.cache.php';

        $outdated = !file_exists($this->cacheFilePath) ||
            filemtime($this->cacheFilePath) < filemtime($this->langFilePath) || // the language config was updated
            ($this->mergeFallback && filemtime($this->cacheFilePath) < filemtime($this->getConfigFilename($this->fallbackLang))); // the fallback language config was updated

        if ($outdated) {
            $config = $this->load($this->langFilePath);
            if ($this->mergeFallback)
                $config = array_replace_recursive($this->load($this->getConfigFilename($this->fallbackLang)), $config);

            $compiled = "<?php class " . $this->prefix . " {\n"
            	. $this->compile($config)
            	. 'public static function __callStatic($string, $args) {' . "\n"
            	. '    return vsprintf(constant("self::" . $string), $args);'
            	. "\n}\n}\n"
            	. "function ".$this->prefix .'($string, $args=NULL) {'."\n"
            	. '    $return = constant("'.$this->prefix.'::".$string);'."\n"
            	. '    return $args ? vsprintf($return,$args) : $return;'
            	. "\n}";

			if( ! is_dir($this->cachePath))
				mkdir($this->cachePath, 0755, true);

            if (file_put_contents($this->cacheFilePath, $compiled) === FALSE) {
                throw new Exception("Could not write cache file to path '" . $this->cacheFilePath . "'. Is it writable?");
            }
            chmod($this->cacheFilePath, 0755);

        }

        require_once $this->cacheFilePath;
    }

    public function isInitialized() {
        return $this->isInitialized;
    }

    public function getAppliedLang() {
        return $this->appliedLang;
    }

    public function getCachePath() {
        return $this->cachePath;
    }

    public function getFallbackLang() {
        return $this->fallbackLang;
    }

    public function setFilePath($filePath) {
        $this->fail_after_init();
        $this->filePath = $filePath;
    }

    public function setCachePath($cachePath) {
        $this->fail_after_init();
        $this->cachePath = $cachePath;
    }

    public function setFallbackLang($fallbackLang) {
        $this->fail_after_init();
        $this->fallbackLang = $fallbackLang;
    }

    public function setMergeFallback($mergeFallback) {
        $this->fail_after_init();
        $this->mergeFallback = $mergeFallback;
    }

    public function setPrefix($prefix) {
        $this->fail_after_init();
        $this->prefix = $prefix;
    }

    public function setForcedLang($forcedLang) {
        $this->fail_after_init();
        $this->forcedLang = $forcedLang;
    }

    public function setSectionSeparator($sectionSeparator) {
        $this->fail_after_init();
        $this->sectionSeparator = $sectionSeparator;
    }

    public function setSectionSeperator($sectionSeparator) {
        $this->setSectionSeparator($sectionSeparator);
    }

    public function getUserLangs() {
        $userLangs = array();

        if ($this->forcedLang != NULL) {
            $userLangs[] = $this->forcedLang;
        }

        if (isset($_GET['lang']) && is_string($_GET['lang'])) {
            $userLangs[] = $_GET['lang'];
        }

        if (isset($_SESSION['lang']) && is_string($_SESSION['lang'])) {
            $userLangs[] = $_SESSION['lang'];
        }

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $part) {
                $userLangs[] = strtolower(substr($part, 0, 2));
            }
        }

        $userLangs[] = $this->fallbackLang;

        $userLangs = array_unique($userLangs);

        $userLangs2 = array();
        foreach ($userLangs as $key => $value) {
            if (preg_match('/^[a-zA-Z0-9_-]*$/', $value) === 1)
                $userLangs2[$key] = $value;
        }

        return $userLangs2;
    }

    protected function getConfigFilename($langcode) {
        return str_replace('{LANGUAGE}', $langcode, $this->filePath);
    }

    protected function load($filename) {
        $ext = substr(strrchr($filename, '.'), 1);
        switch ($ext) {
            case 'properties':
            case 'ini':
                $config = parse_ini_file($filename, true);
                break;
            case 'yml':
            case 'yaml':
                $config = spyc_load_file($filename);
                break;
            case 'json':
                $config = json_decode(file_get_contents($filename), true);
                break;
            default:
                throw new InvalidArgumentException($ext . " is not a valid extension!");
        }
        return $config;
    }

    protected function compile($config, $prefix = '') {
        $code = '';
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $code .= $this->compile($value, $prefix . $key . $this->sectionSeparator);
            } else {
                $fullName = $prefix . $key;
                if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $fullName)) {
                    throw new InvalidArgumentException(__CLASS__ . ": Cannot compile translation key " . $fullName . " because it is not a valid PHP identifier.");
                }
                $code .= 'const ' . $fullName . ' = "' . str_replace('"', '\"', $value) . "\";\n";
            }
        }
        return $code;
    }

    protected function fail_after_init() {
        if ($this->isInitialized()) {
            throw new BadMethodCallException('This ' . __CLASS__ . ' object is already initalized, so you can not change any settings.');
        }
    }
}