<?php

namespace Drupal\social_demo\Yaml;

/*
 * Social Demo Content Generator
 */
use Symfony\Component\Yaml\Yaml;

/**
 * Implements the SocialDemoParser to pass the demo content yml files.
 */
class SocialDemoParser extends Yaml {

  /**
   * Returns the path for the given file.
   *
   * @param string $file
   *   The filename string.
   *
   * @return string
   *   String with the full pathname including the file.
   */
  private function getPath($file) {
    // @todo Fix this for other file paths?!.
    return '/root/dev-scripts/content/' . $file;
  }

  /**
   * {@inheritdoc}
   */
  public function parseFile($file) {
    return $this->parse(file_get_contents($this->getPath($file)));
  }

}
