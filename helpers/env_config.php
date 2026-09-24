<?php

/* Production-safe error handling: itago ang mga PHP error sa
   browser/audience, pero i-log pa rin sila sa server para
   makita mo pa rin kapag may nangyaring error. */

ini_set("display_errors", "0");
ini_set("display_startup_errors", "0");
error_reporting(E_ALL);
ini_set("log_errors", "1");
