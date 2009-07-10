<?php
/**
 * Fixes directory permissions for the minify cache.
 *
 * @package    symfony
 * @subpackage sfMinifyPluginTS
 * @author     Al Bates
 */
class sfMinifyPermissionsTask extends sfBaseTask
{
  /**
   * @see sfTask
   */
  protected function configure()
  {
    $this->aliases = array('minify-fix-perms');
    $this->namespace = 'minify';
    $this->name = 'permissions';
    $this->briefDescription = 'Fixes directory permissions for the minify cache';

    $this->detailedDescription = <<<EOF
The [minify:permissions|INFO] task fixes directory permissions for the minify cache:

  [./symfony minify:permissions|INFO]
EOF;
  }

  /**
   * @see sfProjectPermissionsTask
   */
  protected function execute($arguments = array(), $options = array())
  {
    if (file_exists(sfConfig::get('sf_web_dir') . '/' . sfMinifyTSFilter::CACHE_DIR))
    {
      $this->getFilesystem()->chmod(sfConfig::get('sf_web_dir') . '/' . sfMinifyTSFilter::CACHE_DIR, 0777);
    }
  }
}
