<?php
/**
* @package     
* @subpackage  
* @author      Brice Tencé
* @copyright   2012 Brice Tencé
* @link        
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/

/**
* plugin for jResponseHTML, which processes Sass files
*/

require_once 'phpsass/SassParser.php';

define('PHPSASS_COMPILE_ALWAYS', 1 );
define('PHPSASS_COMPILE_ONCHANGE', 2 ); //default value : jphpsass default behaviour
define('PHPSASS_COMPILE_ONCE', 3 );

class jphpsassHTMLResponsePlugin implements jIHTMLResponsePlugin {

    protected $response = null;

    public function __construct(jResponse $c) {
        $this->response = $c;
    }

    /**
     * called just before the jResponseBasicHtml::doAfterActions() call
     */
    public function afterAction() {
    }

    /**
     * called when the content is generated, and potentially sent, except
     * the body end tag and the html end tags. This method can output
     * directly some contents.
     */
    public function beforeOutput() {
        if (!($this->response instanceof jResponseHtml))
            return;
        global $gJConfig;

        $compileFlag = PHPSASS_COMPILE_ONCHANGE;
        if( isset($gJConfig->jResponseHtml['phpsass_compile']) ) {
            switch($gJConfig->jResponseHtml['phpsass_compile']) {
            case 'always':
                $compileFlag = PHPSASS_COMPILE_ALWAYS;
                break;
            case 'onchange':
                $compileFlag = PHPSASS_COMPILE_ONCHANGE;
                break;
            case 'once':
                $compileFlag = PHPSASS_COMPILE_ONCE;
                break;
            }
        }

        $sassExtensions = array('sass', 'scss');
        if( isset($gJConfig->jResponseHtml['phpsass_extensions']) ) {
            $extString = $gJConfig->jResponseHtml['phpsass_extensions'];
            $sassExtensions = array_map( 'trim', explode(',', $extString) );
        }

        $optionsArray = array( 
                            'style' => 'nested', // nested (default), compact, compressed, or expanded
                            'debug' => false,
                            'watchdog' => false
                        );
        if( isset($gJConfig->jResponseHtml['phpsass_style']) ) {
            $optionsArray['style'] = $gJConfig->jResponseHtml['phpsass_style'];
        }
        if( isset($gJConfig->jResponseHtml['phpsass_debug']) ) {
            $optionsArray['debug'] = $gJConfig->jResponseHtml['phpsass_debug'];
        }
        if( isset($gJConfig->jResponseHtml['phpsass_watchdog']) ) {
            $optionsArray['watchdog'] = $gJConfig->jResponseHtml['phpsass_watchdog'];
        }

        $inputCSSLinks = $this->response->getCSSLinks();
        $outputCSSLinks = array();

        foreach( $inputCSSLinks as $inputCSSLinkUrl=>$CSSLinkParams ) {
            $CSSLinkUrl = $inputCSSLinkUrl;
            if( in_array( pathinfo($inputCSSLinkUrl, PATHINFO_EXTENSION), $sassExtensions ) ||
                (isset($CSSLinkParams['sass']) && $CSSLinkParams['sass']) ) {
                    //we suppose url starts with basepath ...
                    if( substr($CSSLinkUrl, 0, strlen($gJConfig->urlengine['basePath'])) != $gJConfig->urlengine['basePath'] ) {
                        throw new Exception("File $CSSLinkUrl seems not to be located in your basePath : it can not be processed with phpsass");
                    } else {
                        $filePath = jApp::wwwPath() . substr($CSSLinkUrl, strlen($gJConfig->urlengine['basePath']));

                        $outputSuffix = '.css';
                        $outputPath = $filePath . $outputSuffix;

                        try {
                            $compile = true;
                            if( is_file($outputPath) ) {
                                if( ($compileFlag == PHPSASS_COMPILE_ALWAYS) ) {
                                    unlink($outputPath);
                                } elseif( ($compileFlag == PHPSASS_COMPILE_ONCE) ) {
                                    $compile = false;
                                }
                                //PHPSASS_COMPILE_ONCHANGE is jphpsass's natural behaviour. So we let him do ...
                            }
                            if( $compile ) {
                                $this->compileSass($filePath, $outputPath, $optionsArray);
                            }
                            $CSSLinkUrl = $CSSLinkUrl . $outputSuffix;
                        } catch (exception $ex) {
                            trigger_error("sass fatal error on file $filePath:<br />".$ex->getMessage(), E_USER_ERROR);
                        }
                    }
                    unset($CSSLinkParams['sass']);
            }

            $outputCSSLinks[$CSSLinkUrl] = $CSSLinkParams;
        }

        $this->response->setCSSLinks( $outputCSSLinks );
    }



    private function compileSass( $filePath, $outputPath, $optionsArray ) {
        extract ($optionsArray);

        try {
            $options = array(
                'style' => $style,
                'cache' => FALSE,
                'syntax' => pathinfo($filePath, PATHINFO_EXTENSION),
                'debug' => FALSE,
                'debug_info' => $debug,
                'load_paths' => array(dirname($filePath)),
                'filename' => array('dirname'=>dirname($filePath), 'basename'=>basename($filePath)),
                'load_path_functions' => array(),//'sassy_load_callback'),
                'functions' => $this->getSassphpFunctions(),
                'callbacks' => array(
                    'warn' => $watchdog ? array($this, 'watchdog_warn') : NULL,
                    'debug' => $watchdog ? array($this, 'watchdog_debug') : NULL,
                )
            );
            // Execute the compiler.
            $parser = new SassParser($options);
            file_put_contents( $outputPath, $parser->toCss(file_get_contents($filePath), false) );
            return;
        }
        catch (Exception $e) {
            trigger_error( "Sass error for '$filePath' : " . $e->getMessage(), E_USER_ERROR );
        }

    }


    /**
     * Callback for @warn directive.
     */
    public function watchdog_warn($message, $context) {
        _watchdog($message, $context, 'warn', E_USER_WARNING);
    }

    /**
     * Callback for @debug directive.
     */
    public function watchdog_debug($message, $context) {
        _watchdog($message, $context, 'debug', E_USER_NOTICE);
    }

    /**
     * Handler for @warn/@debug directive callbacks.
     */
    private function _watchdog($message, $context, $level) {
        $line = $context->node->token->line;
        $filename = $context->node->token->filename;
        $message = "Line $line of $filename : %message";
        trigger_error( $message, $level );
    }

    /**
     * Returns all functions to be used inside the parser.
     */
    private function getSassphpFunctions() {

        //TODO : cache this
        $functions = array( 'truc' => array($this, 'truc') );
        return $functions;
    }

    public function truc() {
        return new SassString('truc');
    }





    /**
     * called just before the output of an error page
     */
    public function atBottom() {
    }

    /**
     * called just before the output of an error page
     */
    public function beforeOutputError() {
    }
}



















/**
 * Returns all functions to be used inside the parser.
 * @author Joon Park (dvessel), richthegeek, fubhy
 */
function sassy_get_functions() {
    return array();
  $functions =& drupal_static(__FUNCTION__);

  if (!isset($function)) {
    foreach (module_invoke_all('sassy_functions') as $info) {
      $info = (object) $info;
      $functions[$info->name] = $info->callback;
    }
    foreach (array_merge($GLOBALS['base_theme_info'], array($GLOBALS['theme_info'])) as $theme) {
      $function = $theme->name . '_sassy_functions';
      if (function_exists($function) && $data = $function()) {
        foreach ($data as $info) {
          $info = (object) $info;
          $functions[$info->name] = $info->callback;
        }
      }
    }
  }
  return $functions;
}

/**
 * Called from inside SassParser when a file is trying to be loaded.
 *
 * @param $file
 *    The file trying to be loaded, eg 'myfile/bla.scss'
 *
 * @return
 *    An array of 0 - n filenames to load.
 *    If no valid files are found return array() or FALSE
 */
function sassy_load_callback($file) {
  $file = explode('/', $file, 2);
  $namespace = preg_replace('/[^0-9a-z]+/', '_', strtolower(array_shift($file)));
  # check for implementing modules specific to namespace and invoke looking for a paths array.
  foreach (module_implements('sassy_resolve_path_' . $namespace) as $module) {
    $hook = $module . '_sassy_resolve_path_' . $namespace;
    if (function_exists($hook) && $paths = call_user_func($hook, $file[0])) {
      return (array) $paths;
    }
  }
  # check for implenting modules for the generic hook, looking for a path array.
  foreach (module_implements('sassy_resolve_path') as $module) {
    $hook = $module . '_sassy_resolve_path';
    if (function_exists($hook) && $paths = call_user_func($hook, $file)) {
      return (array) $paths;
    }
  }
  # check for modules or themes named $namespace and try directly finding a file.
  if (!($path = drupal_get_path('module', $namespace))) {
    $path = drupal_get_path('theme', $namespace);
  }
  if (!$path && $namespace == 'public') {
    $path = 'public:/';
  }
  if ($path) {
    $path = $path . '/' . $file[0];
    if (file_exists($path)) {
      $path = drupal_realpath($path);
      return array($path);
    }
  }
  return FALSE;
}

/**
 * Implementation of hook_sassy_resolve_path_NAMESPACE().
 */
function sassy_sassy_resolve_path_sassy($file) {
  return sassy_registered_includes(basename($file));
}

/**
 * Fetches, caches and returns all SASS / SCSS libraries from all enabled
 * modules and the theme trail.
 *
 * @return
 *   An array of all library files, sorted by their basename.
 */
function sassy_registered_includes($base = NULL) {
  $includes = &drupal_static(__FUNCTION__);
  if (!isset($includes)) {
    if ($cache = cache_get('sassy_libraries:' . $GLOBALS['theme_key'])) {
      $includes = $cache->data;
    }
    else {
      $includes = array();
      // Load libraries from all enabled modules and themes.
      foreach (array_merge(module_list(), $GLOBALS['base_theme_info'], array($GLOBALS['theme_info'])) as $info) {
        $type = is_object($info) ? 'theme' : 'module';
        $name = $type == 'theme' ? $info->name : $info;
        $info = $type == 'theme' ? $info->info : system_get_info('module', $name);
        if (!empty($info['sassy'])) {
          foreach ($info['sassy'] as $include) {
            $path = drupal_get_path($type, $name) . '/' . $include;
            if (is_file($path)) {
              $includes[basename($path)][] = $path;
            }
          }
        }
      }
      drupal_alter('sassy_includes', $includes);
      cache_set('sassy_includes:' . $GLOBALS['theme_key'], $includes);
    }
  }
  if (isset($base) && isset($includes[$base])) {
    return $includes[$base];
  }
  else if (!isset($base)) {
    return $includes;
  }
}
