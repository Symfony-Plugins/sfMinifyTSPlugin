<?php

/*
 * Extends the default symfony cache clear task to clear the minify cache too.
 * 
 * Also adds a new value for the 'type' option - minify.
 */

/**
 * Clears the symfony cache.
 *
 * @package    symfony
 * @subpackage sfMinifyPluginTS
 * @author     Al Bates
 */
class sfMinifyCacheClearTask extends sfCacheClearTask
{
  /**
   * @see sfCacheClearTask
   */
  protected function configure()
  {
    $this->detailedDescription = <<<EOF
The [minify:clear-cache|INFO] task clears the symfony cache, including the minify cache directory.

By default, it removes the cache for all available types, all applications,
and all environments.

You can restrict by type, application, or environment:

For example, to clear the [frontend|COMMENT] application cache:

  [./symfony minify:clear-cache --app=frontend|INFO]

To clear the cache for the [prod|COMMENT] environment for the [frontend|COMMENT] application:

  [./symfony minify:clear-cache --app=frontend --env=prod|INFO]

To clear the cache for all [prod|COMMENT] environments:

  [./symfony minify:clear-cache --env=prod|INFO]

To clear the [minify|COMMENT] cache for all [prod|COMMENT] environments:

  [./symfony minify:clear-cache --type=minify --env=prod|INFO]

The built-in types are: [config|COMMENT], [i18n|COMMENT], [routing|COMMENT], [module|COMMENT],
[template|COMMENT] and [minify|COMMENT].

EOF;

    parent::configure();
    
    $this->aliases = array('mcc', 'minify-clear-cache');
    $this->namespace = 'minify';
    $this->name = 'clear-cache';
  }
  
  /**
   * Clears the minify cache
   * @param $appConfiguration
   */
  protected function clearMinifyCache(sfApplicationConfiguration $appConfiguration)
  {
    $subDir = sfConfig::get('sf_web_dir') . '/' . sfMinifyTSFilter::CACHE_DIR;

    if (is_dir($subDir))
    {
      // remove cache files
      $this->getFilesystem()->remove(sfFinder::type('file')->discard('.css')->in($subDir));
      $this->getFilesystem()->remove(sfFinder::type('file')->discard('.js')->in($subDir));
      $this->getFilesystem()->remove(sfFinder::type('file')->discard('minify_*')->in($subDir));
    }
  }

  /**
   * Overrides the clearAllCache method to clear the minify cache too.
   * 
   * @see sfCacheClearTask
   */
  protected function clearAllCache(sfApplicationConfiguration $appConfiguration)
  {
    parent::clearAllCache($appConfiguration);
    $this->clearMinifyCache($appConfiguration);
  }
}
