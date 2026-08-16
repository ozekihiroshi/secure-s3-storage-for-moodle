Bundled AWS SDK runtime
=======================

Official release ZIP files include the Composer dependencies locked by
composer.lock under vendor/. Source archives and release information for each
library are available from the package URLs recorded in composer.lock.

To rebuild the bundled runtime, install Docker and run:

    sh scripts/build-release.sh

The build runs Composer 2.8.12 with --no-dev, --classmap-authoritative,
--no-scripts, and the committed lock file. Do not install Composer dependencies
from the web server process or from Moodle itself.
