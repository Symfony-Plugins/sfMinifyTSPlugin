<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfMinifyFilterTS automatically adds javascripts and stylesheets information in the 
 * sfResponse content. It names the generated files based on the modification times of
 * each component file.
 * 
 * Based on sfMinifyPlugin by Gordon Franke
 *
 * @package    symfony
 * @subpackage sfMinifyPluginTS
 * @author     Al Bates
 * @version    SVN: $Id: sfMinifyFilterTS.class.php 3244 2009-04-24 16:46:11Z al $
 */
class sfMinifyTSFilter extends sfFilter
{
  /**
   * @var Regular expressions for finding local javascript files
   */
  const JAVASCRIPT_TAG_REGEXP = '!<\s*script(.*?)>\s*</script>!i';
  const JAVASCRIPT_SRC_REGEXP = '!src="(/[^"]+)"!i';
  
  /**
   * @var Regular expressions for finding local stylesheets
   */
  const CSS_TAG_REGEXP = '!<\s*link rel="stylesheet"(.*?)\s*/>!i';
  const CSS_SRC_REGEXP = '!href="(/[^"]+)"!i';
  
  /**
   * @var string The name of the cache directory relative to the web root
   */
  const CACHE_DIR = '/sfMinifyTSPlugin/cache/';
  
  /**
   * Executes this filter.
   *
   * @param sfFilterChain A sfFilterChain instance
   */
  public function execute($filterChain)
  {
    // execute next filter
    $filterChain->execute();

    // execute this filter only once
    $response = $this->getContext()->getResponse();

    // include javascripts and stylesheets
    $content = $response->getContent();
    if (false !== ($pos = strpos($content, '</head>')))
    {
      sfContext::getInstance()->getConfiguration()->loadHelpers(array('Tag', 'Asset', 'SfMinifyTS'));

      $html = '';
      if (!sfConfig::get('symfony.asset.javascripts_included', false))
      {
        // don't compress if the symfony 'compressed' setting is false
        $html .= (sfConfig::get('sf_compressed')) ? minify_get_javascripts($response, $this->getParameter('javascripts', true))
         : get_javascripts($response);
      }
      if (!sfConfig::get('symfony.asset.stylesheets_included', false))
      {
        $html .= (sfConfig::get('sf_compressed')) ? minify_get_stylesheets($response, $this->getParameter('stylesheets', true))
         : get_stylesheets($response);
      }

      if ($html)
      {
        $response->setContent(substr($content, 0, $pos).$html.substr($content, $pos));
      }
    }

    sfConfig::set('symfony.asset.javascripts_included', false);
    sfConfig::set('symfony.asset.stylesheets_included', false);

    // return here if we don't need to compress output
    if (!sfConfig::get('sf_compressed'))
    {
      return;
    }

    // Get the names of referenced local javascript and css files
    $javascripts = $this->getAttributesOfTag($response->getContent(), self::JAVASCRIPT_TAG_REGEXP, 
      self::JAVASCRIPT_SRC_REGEXP);

    // Before searching for stylesheets, strip out links to stylesheets in
    // IE conditional comments
    if (preg_match('/<!--\[if.*endif\]-->/sim', $response->getContent()))
    {
        $content = preg_replace('/<!--\[if.*endif\]-->/sim', '', $response->getContent());
    }
    else
    {
      $content = $response->getContent();
    }

    $css = $this->getAttributesOfTag($content, self::CSS_TAG_REGEXP, 
      self::CSS_SRC_REGEXP);

    if ($javascripts && $this->getParameter('javascripts', true))
    {
      // loop through all js/css files and create a unique file name based on 
      // modification times of each file
      list($jsFullPaths, $javascriptMD5) = $this->parseFileList($javascripts);
      $javascriptMD5 .= '.js';

      // invoke Minify to compress the js and css
      $this->minify($jsFullPaths, sfConfig::get('sf_web_dir') . $javascriptMD5);
      
      // correct the path to minified files if we've been configured to use a 
      // cache path alias
      $cachePath = $this->getParameter('cache_dir', self::CACHE_DIR);
      $javascriptMD5 = str_replace(self::CACHE_DIR, $cachePath, $javascriptMD5);
      
      // replace the new names back into the response content
      $response->setContent(str_replace($javascripts, $javascriptMD5, $response->getContent()));
    }
    
    if ($css && $this->getParameter('stylesheets', true))
    {
      list($cssFullPaths, $cssMD5) = $this->parseFileList($css);
      $cssMD5 .= '.css';
      
      $this->minify($cssFullPaths, sfConfig::get('sf_web_dir') . $cssMD5);
      
      $cachePath = $this->getParameter('cache_dir', self::CACHE_DIR);
      $cssMD5 = str_replace(self::CACHE_DIR, $cachePath, $cssMD5);
      
      $response->setContent(str_replace($css, $cssMD5, $response->getContent()));
    }  
  }
  
  /**
   * Minifies $inputFiles and names the combined file $outFile
   * @param $inputFiles
   * @param $outFile
   * @return unknown_type
   */
  private function minify($inputFiles, $outFile)
  {
    if (file_exists($outFile))
    {
      return;
    }
    
    // initialise the library
    $this->initMinify();
    
    $files = explode(',', $inputFiles);

    // we need to capture the output ourselves, and write our own cache files...
    ob_start();
    Minify::serve('Files', array('files' => $files, 'encodeOutput' => false));
    $contents = ob_get_contents();
    ob_end_clean();
    
    // write output to the cache file
    $fh = fopen($outFile, 'w');
    fwrite($fh, $contents);
    fclose($fh);
  }
  
  /**
   * Initialises Minify
   */
  private function initMinify()
  {
    static $initialised;
    
    if (isset($initialised))
    {
      return;
    }
    
    ini_set('include_path', realpath(dirname(__FILE__) . '/../../minify/lib') . PATH_SEPARATOR . 
      ini_get('include_path'));
    
    require('Minify.php');
    
    /**
     * Set $minifyCachePath to a PHP-writeable path to enable server-side caching
     * in all examples and tests.
     */
    if (sfConfig::get('sf_cache'))
    {
      $minifyCachePath = sfConfig::get('sf_web_dir') . self::CACHE_DIR;
      if(!is_dir($minifyCachePath))
      {
        mkdir($minifyCachePath);
      }
      
      Minify::setCache($minifyCachePath);
    }
    
    $initialised = true;
  }
  
  /**
   * Returns an array of full paths for each file given, along with a unique string 
   * that is computed based on the name of each file with its modification time.
   * 
   * @param string $files A comma-separated string of files to check mtimes for
   * @return array
   */
  private function parseFileList($files)
  {
    $basePath = sfConfig::get('sf_web_dir');
    $files = explode(',', $files);
    
    $filePaths = array();
    $string = '';
    
    foreach ($files as $file)
    {
      if (file_exists($basePath . DIRECTORY_SEPARATOR . $file))
      {
        $string .= $file . filemtime($basePath . DIRECTORY_SEPARATOR . $file);
        $filePaths[] = $basePath . DIRECTORY_SEPARATOR . $file;
      }
    }
    
    $filePaths = implode(',', $filePaths);

    return array($filePaths, self::CACHE_DIR . md5($string));
  }
  
  /**
   * Returns an attribute of the first tag that matches $tagRexExp and $attrRegExp in $content
   * @param string $content The content to search
   * @param string $tagRegExp The regular expression to find the desired tag
   * @param string $attrRegExp The regular expression to find the desired attribute
   * @return string
   */
  private function getAttributesOfTag($content, $tagRegExp, $attrRegExp)
  {
    if (preg_match($tagRegExp, $content, $matches))
    {
      if (preg_match($attrRegExp, $matches[1], $attr))
      {
        return $attr[1];
      }
    }
  }
}
