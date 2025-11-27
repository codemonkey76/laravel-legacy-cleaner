<?php

namespace Codemonkey76\LegacyCleaner;

use Illuminate\Support\ServiceProvider;

class LegacyCleanerServiceProvider extends ServiceProvider
{
  public function register()
  {
    $this->mergeConfigFrom(
      __DIR__ . '/../config/legacy-cleaner.php',
      'legacy-cleaner'
    );
  }
}
