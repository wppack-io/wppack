<?php

declare(strict_types=1);

// This file intentionally does NOT declare the class that ServiceDiscovery
// would expect based on directory/namespace mapping. The discovery process
// constructs the FQCN from the file path and namespace prefix, but since
// no such class exists, class_exists() returns false and the file is skipped.
